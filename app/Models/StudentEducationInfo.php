<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class StudentEducationInfo
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo query()
 *
 * @mixin \Eloquent
 */
final class StudentEducationInfo extends Model
{
    public $timestamps = false;

    protected $table = 'student_education_info';

    protected $casts = [
        'elementary_graduate_year' => 'int',
        'senior_high_graduate_year' => 'int',
    ];

    protected $fillable = [
        'elementary_school',
        'elementary_graduate_year',
        'senior_high_name',
        'senior_high_graduate_year',
        'elementary_school_address',
        'senior_high_address',
        'junior_high_school_name',
        'junior_high_school_address',
        'junior_high_graduation_year',
    ];
}
