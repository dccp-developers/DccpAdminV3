<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Attendance
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance query()
 *
 * @mixin \Eloquent
 */
final class Attendance extends Model
{
    protected $table = 'attendances';

    protected $casts = [
        'class_enrollment_id' => 'int',
        'student_id' => 'int',
        'date' => 'datetime',
    ];

    protected $fillable = [
        'class_enrollment_id',
        'student_id',
        'date',
        'status',
    ];
}
