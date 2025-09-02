<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ShsStudent
 *
 * @property-read \App\Models\Account|null $account
 * @property-read \App\Models\ShsStrand|null $strand
 * @property-read \App\Models\ShsTrack|null $track
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent query()
 *
 * @mixin \Eloquent
 */
final class ShsStudent extends Model
{
    use HasFactory;

    protected $table = 'shs_students';

    protected $casts = [
        'strand_id' => 'int',
        'track_id' => 'int',
        'birthdate' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'student_lrn',
        'fullname',
        'civil_status',
        'religion',
        'nationality',
        'birthdate',
        'guardian_name',
        'guardian_contact',
        'student_contact',
        'complete_address',
        'grade_level',
        // 'track', // This field is deprecated in favor of track_id relationship
        'gender',
        'email',
        'remarks',
        'strand_id',
        'track_id',
    ];

    /**
     * Get the SHS strand that the student is enrolled in.
     */
    public function strand(): BelongsTo
    {
        return $this->belongsTo(ShsStrand::class, 'strand_id');
    }

    /**
     * Get the SHS track that the student is enrolled in.
     * A student must belong to a track. If they are in a strand,
     * this track_id should match the strand's track_id.
     * If the track has no strands (e.g., Sports and Arts), strand_id can be null.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(ShsTrack::class, 'track_id');
    }

    /**
     * Get the account associated with this SHS student
     */
    public function account()
    {
        return $this->hasOne(Account::class, 'person_id', 'student_lrn');
    }
}
