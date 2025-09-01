<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PendingUserEmail
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingUserEmail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingUserEmail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingUserEmail query()
 * @mixin \Eloquent
 */
final class PendingUserEmail extends Model
{
    public $timestamps = false;

    protected $table = 'pending_user_emails';

    protected $casts = [
        'user_id' => 'float',
    ];

    protected $hidden = [
        'token',
    ];

    protected $fillable = [
        'user_type',
        'user_id',
        'email',
        'token',
    ];
}
