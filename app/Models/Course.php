<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class Course
 *
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property int $units
 * @property string|null $lec_per_unit
 * @property string|null $lab_per_unit
 * @property int $year_level
 * @property int $semester
 * @property string|null $school_year
 * @property string|null $miscellaneous
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $course_code
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Schedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Student> $students
 * @property-read int|null $students_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subject> $subjects
 * @property-read int|null $subjects_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereLabPerUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereLecPerUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereMiscellaneous($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereSchoolYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereUnits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereYearLevel($value)
 * @mixin \Eloquent
 */
final class Course extends Model
{
    protected $table = 'courses';

    // protected $casts = [
    // 	'lec_per_unit' => 'int',
    // 	'lab_per_unit' => 'int',
    // 	'miscelaneous' => 'int'
    // ];

    protected $fillable = [
        'code',
        'title',
        'description',
        'department',
        'lec_per_unit',
        'lab_per_unit',
        'remarks',
        'curriculum_year',
        'miscelaneous',
    ];

    protected $primaryKey = 'id';

    public static function getCourseDetails($courseId): string
    {
        $course = self::find($courseId);

        return $course ? "{$course->title} (Code: {$course->code}, Department: {$course->department})" : 'Course details not available';
    }

    /**
     * Get all courses with their student counts
     */
    public static function getCoursesWithStudentCount()
    {
        return self::select([
            'courses.id',
            'courses.code',
            'courses.title',
            'courses.department',
            DB::raw('COUNT(students.id) as student_count'),
        ])
            ->leftJoin('students', function ($join): void {
                $join->on('courses.id', '=', 'students.course_id')
                    ->whereNull('students.deleted_at');
            })
            ->groupBy('courses.id', 'courses.code', 'courses.title', 'courses.department')
            ->orderBy('courses.code')
            ->get();
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class, 'course_id', 'id');
    }

    public function getCourseCodeAttribute(): string
    {
        return mb_strtoupper((string) $this->attributes['code']);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'course_id', 'id');
    }

    /**
     * Get all students enrolled in this course
     */
    public function students()
    {
        return $this->hasMany(Student::class, 'course_id', 'id');
    }

    /**
     * Get the count of students enrolled in this course
     */
    public function studentCount()
    {
        return $this->hasMany(Student::class, 'course_id', 'id')
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Get the appropriate miscellaneous fee based on curriculum year
     */
    public function getMiscellaneousFee(): int
    {
        // If the course has a specific miscellaneous fee set, use it
        if ($this->miscelaneous !== null) {
            return (int) $this->miscelaneous;
        }

        // Determine fee based on curriculum year
        return $this->getMiscellaneousFeeBasedOnCurriculumYear();
    }

    /**
     * Get miscellaneous fee based on curriculum year
     * New curriculum (2024-2025): 3700
     * Old curriculum (2018-2019): 3500
     */
    public function getMiscellaneousFeeBasedOnCurriculumYear(): int
    {
        if (empty($this->curriculum_year)) {
            return 3500; // Default to old curriculum fee
        }

        $curriculumYear = mb_strtolower(mb_trim($this->curriculum_year));

        // Check for new curriculum patterns
        if (str_contains($curriculumYear, '2024') && str_contains($curriculumYear, '2025')) {
            return 3700;
        }

        // Default to old curriculum fee for unrecognized patterns
        return 3500;
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($model): void {
            $model->code = mb_strtoupper((string) $model->code);
        });

        self::deleting(function ($model): void {
            $model->subjects()->delete();
        });
    }
}
