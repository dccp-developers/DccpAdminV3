<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class StudentParentsInfo
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentParentsInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentParentsInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentParentsInfo query()
 * @mixin \Eloquent
 */
final class StudentParentsInfo extends Model
{
    protected $table = 'student_parents_info';

    protected $fillable = [
        'fathers_name',
        'mothers_name',
    ];
}
