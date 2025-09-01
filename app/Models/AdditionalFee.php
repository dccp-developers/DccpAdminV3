<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\StudentEnrollment|null $enrollment
 * @property-read string $formatted_amount
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee query()
 * @mixin \Eloquent
 */
final class AdditionalFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'fee_name',
        'amount',
        'is_separate_transaction',
        'transaction_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_separate_transaction' => 'boolean',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }

    /**
     * Get the formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return '₱ ' . number_format((float) $this->amount, 2);
    }
}
