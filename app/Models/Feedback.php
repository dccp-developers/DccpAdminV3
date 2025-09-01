<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Feedback
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback query()
 * @mixin \Eloquent
 */
final class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $casts = [
        'reviewed' => 'bool',
    ];

    protected $fillable = [
        'type',
        'message',
        'user_info',
        'reviewed',
    ];
}
