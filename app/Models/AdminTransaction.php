<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AdminTransaction
 *
 * @property-read \App\Models\Transaction|null $transaction
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminTransaction query()
 *
 * @mixin \Eloquent
 */
final class AdminTransaction extends Model
{
    protected $table = 'admin_transactions';

    protected $casts = [
        'admin_id' => 'int',
        'transaction_id' => 'int',
    ];

    protected $fillable = [
        'admin_id',
        'transaction_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
