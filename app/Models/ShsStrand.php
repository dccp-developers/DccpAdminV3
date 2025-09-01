<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ShsStrand
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ShsStudent> $students
 * @property-read int|null $students_count
 * @property-read \App\Models\ShsTrack|null $track
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStrand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStrand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStrand query()
 * @mixin \Eloquent
 */
final class ShsStrand extends Model
{
    use HasFactory;

    protected $table = 'shs_strands';

    protected $fillable = [
        'strand_name',
        'description',
        'track_id',
    ];

    protected $casts = [
        'track_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the track that this strand belongs to.
     */
    public function track()
    {
        return $this->belongsTo(ShsTrack::class, 'track_id');
    }

    /**
     * Get the students enrolled in this strand.
     */
    public function students()
    {
        return $this->hasMany(ShsStudent::class, 'strand_id');
    }
}
