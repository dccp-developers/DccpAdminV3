<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Resource
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource query()
 *
 * @mixin \Eloquent
 */
final class Resource extends Model
{
    protected $table = 'resources';

    protected $casts = [
        'resourceable_id' => 'int',
        'file_size' => 'int',
        'metadata' => 'array',
    ];

    protected $fillable = [
        'resourceable_type',
        'resourceable_id',
        'type',
        'file_path',
        'file_name',
        'mime_type',
        'disk',
        'file_size',
        'metadata',
    ];
}
