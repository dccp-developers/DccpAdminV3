<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ClassEnrollment
 *
 * @property-read \App\Models\Classes|null $class
 * @property-read \App\Models\Student|null $student
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class ClassEnrollment extends Model
{
    use SoftDeletes;

    protected $table = 'class_enrollments';

    protected $casts = [
        'class_id' => 'int',
        'student_id' => 'float',
        'completion_date' => 'datetime',
        'status' => 'bool',
        'prelim_grade' => 'float',
        'midterm_grade' => 'float',
        'finals_grade' => 'float',
        'total_average' => 'float',
        'is_grades_finalized' => 'boolean',
        'is_grades_verified' => 'boolean',
        'verified_by' => 'integer',
        'verified_at' => 'datetime',
    ];

    protected $fillable = [
        'class_id',
        'student_id',
        'completion_date',
        'status',
        'remarks',
        'prelim_grade',
        'midterm_grade',
        'finals_grade',
        'total_average',
        'is_grades_finalized',
        'is_grades_verified',
        'verified_by',
        'verified_at',
        'verification_notes',
    ];

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id', 'id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }
}
