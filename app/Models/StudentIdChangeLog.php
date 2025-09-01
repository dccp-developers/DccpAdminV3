<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Account|null $changedByUser
 * @property-read \App\Models\Account|null $undoneByUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog undoable()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog undone()
 * @mixin \Eloquent
 */
class StudentIdChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'old_student_id',
        'new_student_id',
        'student_name',
        'changed_by',
        'affected_records',
        'backup_data',
        'is_undone',
        'undone_at',
        'undone_by',
        'reason',
    ];

    protected $casts = [
        'affected_records' => 'array',
        'backup_data' => 'array',
        'is_undone' => 'boolean',
        'undone_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who made the change
     */
    public function changedByUser()
    {
        return $this->belongsTo(Account::class, 'changed_by', 'email');
    }

    /**
     * Get the user who undid the change
     */
    public function undoneByUser()
    {
        return $this->belongsTo(Account::class, 'undone_by', 'email');
    }

    /**
     * Scope to get only changes that can be undone
     */
    public function scopeUndoable($query)
    {
        return $query->where('is_undone', false);
    }

    /**
     * Scope to get only undone changes
     */
    public function scopeUndone($query)
    {
        return $query->where('is_undone', true);
    }
}
