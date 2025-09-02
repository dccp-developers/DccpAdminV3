<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class StrandSubject
 *
 * @property-read \App\Models\ShsStrand|null $strand
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject query()
 *
 * @mixin \Eloquent
 */
final class StrandSubject extends Model
{
    protected $table = 'strand_subjects';

    protected $casts = [
        'strand_id' => 'int',
    ];

    protected $fillable = [
        'code',
        'title',
        'description',
        'grade_year',
        'semester',
        'strand_id',
    ];

    /**
     * Get the strand that this subject belongs to.
     */
    public function strand()
    {
        return $this->belongsTo(ShsStrand::class, 'strand_id');
    }
}
