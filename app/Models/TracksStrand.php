<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TracksStrand
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TracksStrand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TracksStrand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TracksStrand query()
 *
 * @mixin \Eloquent
 */
final class TracksStrand extends Model
{
    protected $table = 'tracks_strands';

    protected $casts = [
        'track_id' => 'int',
    ];

    protected $fillable = [
        'code',
        'title',
        'description',
        'track_id',
    ];
}
