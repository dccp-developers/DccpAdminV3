<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Services\GeneralSettingsService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model; // Import Builder
use Illuminate\Database\Eloquent\SoftDeletes; // Import Cache
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

// Add this import

/**
 * Class StudentEnrollment
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AdditionalFee> $additionalFees
 * @property-read int|null $additional_fees_count
 * @property-read \App\Models\Course|null $course
 * @property-read string $assessment_path
 * @property-read string $assessment_url
 * @property-read string $certificate_path
 * @property-read string $certificate_url
 * @property-read string $student_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Resource> $resources
 * @property-read int|null $resources_count
 * @property-read \App\Models\Student|null $student
 * @property-read \App\Models\StudentTuition|null $studentTuition
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubjectEnrollment> $subjectsEnrolled
 * @property-read int|null $subjects_enrolled_count
 * @method static Builder<static>|StudentEnrollment currentAcademicPeriod()
 * @method static Builder<static>|StudentEnrollment newModelQuery()
 * @method static Builder<static>|StudentEnrollment newQuery()
 * @method static Builder<static>|StudentEnrollment onlyTrashed()
 * @method static Builder<static>|StudentEnrollment query()
 * @method static Builder<static>|StudentEnrollment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudentEnrollment withoutTrashed()
 * @mixin \Eloquent
 */
final class StudentEnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "student_enrollment";

    protected $fillable = [
        "student_id",
        "course_id",
        "status",
        "semester",
        "academic_year",
        "school_year",
        "downpayment",
        "remarks",
    ];

    protected $casts = [
        "id" => "integer",
        "student_id" => "string", // Explicitly cast to string for consistency
        "semester" => "integer",
        "academic_year" => "integer",
        "downpayment" => "float",
        "created_at" => "datetime",
        "updated_at" => "datetime",
        "deleted_at" => "datetime",
    ];

    private array $dates = ["deleted_at"];

    public static function boot(): void
    {
        parent::boot();

        self::creating(function (self $model): void {
            $settings = GeneralSetting::first();
            $model->status = "Pending";
            $model->school_year = $settings->getSchoolYearString();
            $model->semester = $settings->semester;
        });

        // delete also the subjects enrolled
        self::forceDeleted(function (self $model): void {
            $model->subjectsEnrolled()->forceDelete();
            $model->studentTuition()->forceDelete();
        });
    }

    public function signature()
    {
        return $this->morphOne(EnrollmentSignature::class, "enrollment");
    }

    public function student()
    {
        return $this->belongsTo(Student::class, "student_id", "id")
            ->withoutGlobalScopes()
            ->withDefault();
    }

    public function getStudentNameAttribute(): string
    {
        return $this->student->full_name;
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function subjectsEnrolled()
    {
        return $this->hasMany(SubjectEnrollment::class, "enrollment_id", "id");
    }

    public function studentTuition()
    {
        return $this->hasOne(StudentTuition::class, "enrollment_id", "id");
    }

    public function resources()
    {
        return $this->morphMany(Resource::class, "resourceable");
    }

    public function additionalFees()
    {
        return $this->hasMany(AdditionalFee::class, 'enrollment_id');
    }

    /**
     * Get transactions for this specific enrollment period
     * Shows transactions from the current academic year based on school calendar
     */
    public function enrollmentTransactions()
    {
        // Get the actual school calendar dates
        $generalSettingsService = new GeneralSettingsService();
        $schoolStartingDate = $generalSettingsService->getGlobalSchoolStartingDate();
        $schoolEndingDate = $generalSettingsService->getGlobalSchoolEndingDate();

        if (!$schoolStartingDate || !$schoolEndingDate) {
            // If no school dates are set, show all transactions
            return $this->student->Transaction()->orderBy('transactions.created_at', 'desc');
        }

        // Use the actual school calendar dates
        // From school starting date to school ending date
        $startDate = $schoolStartingDate->format('Y-m-d 00:00:00');
        $endDate = $schoolEndingDate->format('Y-m-d 23:59:59');

        return $this->student->Transaction()
            ->whereBetween('transactions.created_at', [$startDate, $endDate])
            ->orderBy('transactions.created_at', 'desc');
    }

    /**
     * Get student transactions for this specific enrollment period
     */
    public function enrollmentStudentTransactions()
    {
        // Use the same logic as enrollmentTransactions
        $generalSettingsService = new GeneralSettingsService();
        $schoolStartingDate = $generalSettingsService->getGlobalSchoolStartingDate();
        $schoolEndingDate = $generalSettingsService->getGlobalSchoolEndingDate();

        if (!$schoolStartingDate || !$schoolEndingDate) {
            // If no school dates are set, show all transactions
            return $this->student->StudentTransactions()->orderBy('student_transactions.created_at', 'desc');
        }

        // Use the actual school calendar dates
        $startDate = $schoolStartingDate->format('Y-m-d 00:00:00');
        $endDate = $schoolEndingDate->format('Y-m-d 23:59:59');

        return $this->student->StudentTransactions()
            ->whereHas('transaction', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('transactions.created_at', [$startDate, $endDate]);
            })
            ->orderBy('student_transactions.created_at', 'desc');
    }

    /**
     * Get the start date for the current academic period
     */
    private function getCurrentAcademicPeriodStartDate(): string
    {
        $generalSettingsService = new GeneralSettingsService();
        $schoolStartingDate = $generalSettingsService->getGlobalSchoolStartingDate();

        if ($schoolStartingDate) {
            // Use the actual school starting date for the current academic year
            $currentYear = $generalSettingsService->getCurrentSchoolYearStart();
            $startMonth = $schoolStartingDate->month;
            $startDay = $schoolStartingDate->day;

            return sprintf("%d-%02d-%02d 00:00:00", $currentYear, $startMonth, $startDay);
        } else {
            // Fallback to August 1st of current academic year
            $currentYear = $generalSettingsService->getCurrentSchoolYearStart();
            return "{$currentYear}-08-01 00:00:00";
        }
    }

    /**
     * Calculate the start date for an academic period using GeneralSettings
     */
    private function getAcademicPeriodStartDate(string $schoolYear, ?int $semester): string
    {
        // Default to semester 1 if null
        $semester = $semester ?? 1;

        // Parse school year (e.g., "2024-2025" -> start year is 2024)
        $years = explode('-', $schoolYear);
        if (count($years) < 2) {
            // Fallback for invalid school year format
            $currentYear = date('Y');
            $years = [$currentYear, $currentYear + 1];
        }

        $startYear = (int) $years[0];
        $endYear = (int) $years[1];

        // Get the actual school starting date from GeneralSettings
        $generalSettingsService = new GeneralSettingsService();
        $schoolStartingDate = $generalSettingsService->getGlobalSchoolStartingDate();

        if ($schoolStartingDate) {
            // Use the actual school starting date
            $startMonth = $schoolStartingDate->month;
            $startDay = $schoolStartingDate->day;

            if ($semester == 1) {
                // First semester starts on the school starting date
                return sprintf("%d-%02d-%02d 00:00:00", $startYear, $startMonth, $startDay);
            } else {
                // Second semester - calculate based on school calendar
                // Typically starts in January after the first semester ends
                return "{$endYear}-01-01 00:00:00";
            }
        } else {
            // Fallback to default dates if no school starting date is set
            if ($semester == 1) {
                return "{$startYear}-08-01 00:00:00";
            } else {
                return "{$endYear}-01-01 00:00:00";
            }
        }
    }

    /**
     * Calculate the end date for an academic period using GeneralSettings
     */
    private function getAcademicPeriodEndDate(string $schoolYear, ?int $semester): string
    {
        // Default to semester 1 if null
        $semester = $semester ?? 1;

        // Parse school year (e.g., "2024-2025" -> end year is 2025)
        $years = explode('-', $schoolYear);
        if (count($years) < 2) {
            // Fallback for invalid school year format
            $currentYear = date('Y');
            $years = [$currentYear, $currentYear + 1];
        }

        $startYear = (int) $years[0];
        $endYear = (int) $years[1];

        // Get the actual school ending date from GeneralSettings
        $generalSettingsService = new GeneralSettingsService();
        $schoolEndingDate = $generalSettingsService->getGlobalSchoolEndingDate();

        if ($schoolEndingDate) {
            // Use the actual school ending date
            $endMonth = $schoolEndingDate->month;
            $endDay = $schoolEndingDate->day;

            if ($semester == 1) {
                // First semester ends around December (mid-year)
                // Calculate based on school calendar - typically December
                return "{$startYear}-12-31 23:59:59";
            } else {
                // Second semester ends on the school ending date
                return sprintf("%d-%02d-%02d 23:59:59", $endYear, $endMonth, $endDay);
            }
        } else {
            // Fallback to default dates if no school ending date is set
            if ($semester == 1) {
                return "{$startYear}-12-31 23:59:59";
            } else {
                return "{$endYear}-06-30 23:59:59";
            }
        }
    }

    /**
     * Get transactions for this enrollment through the student (legacy method)
     * @deprecated Use enrollmentTransactions() instead
     */
    public function transactions()
    {
        return $this->enrollmentTransactions();
    }

    /**
     * Get student transactions for this enrollment through the student (legacy method)
     * @deprecated Use enrollmentStudentTransactions() instead
     */
    public function studentTransactions()
    {
        return $this->enrollmentStudentTransactions();
    }

    public function getAssessmentPathAttribute(): string
    {
        return $this->resources()
            ->where("type", "assessment")
            ->latest()
            ->first()->file_path;
    }

    public function getCertificatePathAttribute(): string
    {
        return $this->resources()
            ->where("type", "certificate")
            ->latest()
            ->first()->file_path;
    }

    public function getAssessmentUrlAttribute(): string
    {
        $resource = $this->resources()
            ->where("type", "assessment")
            ->latest()
            ->first();
        if (!$resource) {
            return "";
        }

        // Use asset helper instead of Storage::url
        try {
            return asset("storage/" . mb_ltrim($resource->file_path, "/"));
        } catch (Exception) {
            return "";
        }
    }

    public function getCertificateUrlAttribute(): string
    {
        $resource = $this->resources()
            ->where("type", "certificate")
            ->latest()
            ->first();
        if (!$resource) {
            return "";
        }

        // Use asset helper instead of Storage::url
        try {
            return asset("storage/" . mb_ltrim($resource->file_path, "/"));
        } catch (Exception) {
            return "";
        }
    }

    /**
     * Scope a query to only include enrollments for the current school year and semester.
     */
    public function scopeCurrentAcademicPeriod(Builder $query): Builder
    {
        // Use the GeneralSettingsService to get effective settings
        /** @var GeneralSettingsService $settingsService */
        $settingsService = app(GeneralSettingsService::class);

        $schoolYear = $settingsService->getCurrentSchoolYearString(); // e.g., "2024 - 2025"
        $semester = $settingsService->getCurrentSemester(); // integer (1 or 2)

        return $query
            ->where("school_year", $schoolYear)
            ->where("semester", $semester);
    }
}
