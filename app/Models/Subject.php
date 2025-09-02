<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Enums\SubjectEnrolledEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Subject
 *
 * @property SubjectEnrolledEnum $classification
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Classes> $classes
 * @property-read int|null $classes_count
 * @property-read \App\Models\Course|null $course
 * @property-read mixed $all_pre_requisites
 * @property-read int|float $laboratory_fee
 * @property-read int|float $lecture_fee
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubjectEnrollment> $subjectEnrolleds
 * @property-read int|null $subject_enrolleds_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject credited()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject nonCredited()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject query()
 *
 * @mixin \Eloquent
 */
final class Subject extends Model
{
    protected $table = 'subject';

    protected $casts = [
        'classification' => SubjectEnrolledEnum::class,
        'units' => 'int',
        'lecture' => 'int',
        'laboratory' => 'int',
        'academic_year' => 'int',
        'semester' => 'int',
        'course_id' => 'int',
        'is_credited' => 'bool',
        'pre_riquisite' => 'array',
    ];

    protected $fillable = [
        'classification',
        'code',
        'title',
        'units',
        'lecture',
        'laboratory',
        'pre_riquisite',
        'academic_year',
        'semester',
        'course_id',
        'group',
        'is_credited',
    ];

    public static function boot(): void
    {
        parent::boot();
        // self::observe(SubjectObserver::class);

        self::creating(function ($model): void {
            $model->id = max(self::all()->pluck('id')->toArray()) + 1;
        });
    }

    public static function getSubjectsDetailsByYear($subjects, $year)
    {
        return $subjects->where('academic_year', $year)->map(fn ($subject): string => "{$subject->title} (Code: {$subject->code}, Units: {$subject->units})")->join(', ');
    }

    public static function getAvailableSubjects($selectedCourse, $academicYear, $selectedSemester, $schoolYear, $type, $selectedSubjects)
    {
        $classes = Classes::where('school_year', $schoolYear)
            ->when($type !== 'transferee', fn ($query) => $query->where('academic_year', $academicYear)
                ->where('semester', $selectedSemester))
            ->whereJsonContains('course_codes', (string) $selectedCourse)
            ->get(['subject_code']);

        // Create a map of trimmed subject codes for matching
        $classSubjectCodes = [];
        foreach ($classes as $class) {
            $trimmedCode = mb_trim($class->subject_code);
            $classSubjectCodes[] = $trimmedCode;
        }

        $availableSubjects = self::query()
            ->where('course_id', $selectedCourse)
            ->where(function ($query) use ($classSubjectCodes) {
                foreach ($classSubjectCodes as $code) {
                    $query->orWhereRaw('TRIM(code) = ?', [$code]);
                }
            })
            ->whereNotIn('id', $selectedSubjects);

        if ($type !== 'transferee') {
            $availableSubjects->where('academic_year', $academicYear)
                ->where('semester', $selectedSemester);
        }

        return $availableSubjects->pluck('code', 'id');
    }

    public function scopeCredited($query)
    {
        return $query->where('is_credited', true);
    }

    public function isCredited(): bool
    {
        return $this->classification === SubjectEnrolledEnum::CREDITED->value;
    }

    public function isNonCredited(): bool
    {
        return $this->classification === SubjectEnrolledEnum::NON_CREDITED->value;
    }

    public function isInternal(): bool
    {
        return $this->classification === SubjectEnrolledEnum::INTERNAL->value;
    }

    public function scopeNonCredited($query)
    {
        return $query->where('is_credited', false);
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }

    public function subjectEnrolleds()
    {
        return $this->hasMany(SubjectEnrollment::class, 'subject_id');
    }

    // GEt pre requisites
    public function getAllPreRequisitesAttribute()
    {
        return $this->pre_riquisite;
    }

    public function getLectureFeeAttribute(): int|float
    {
        return $this->lecture * $this->course->lab_per_unit;
    }

    public function getLaboratoryFeeAttribute(): int|float
    {
        return $this->laboratory * $this->course->lab_per_unit;
    }

    public function classes()
    {
        return $this->hasMany(Classes::class, 'subject_code', 'code');
    }
}
