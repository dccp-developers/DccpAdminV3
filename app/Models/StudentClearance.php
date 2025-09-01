<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $formatted_semester
 * @property-read \App\Models\Student|null $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance query()
 * @mixin \Eloquent
 */
final class StudentClearance extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year',
        'semester',
        'is_cleared',
        'remarks',
        'cleared_by',
        'cleared_at',
    ];

    protected $casts = [
        'is_cleared' => 'boolean',
        'semester' => 'integer',
        'cleared_at' => 'datetime',
    ];

    /**
     * Create a new clearance record for the current semester.
     *
     * @param  mixed  $student
     */
    public static function createForCurrentSemester($student, ?GeneralSetting $settings = null): self
    {
        $settings ??= GeneralSetting::first();

        return self::create([
            'student_id' => $student->id,
            'academic_year' => $settings->getSchoolYear(),
            'semester' => $settings->semester,
            'is_cleared' => false,
        ]);
    }

    /**
     * Get the student that owns the clearance.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Mark the clearance as cleared.
     */
    public function markAsCleared(?string $clearedBy = null, ?string $remarks = null): bool
    {
        $this->is_cleared = true;
        $this->cleared_by = $clearedBy;
        $this->cleared_at = now();

        if ($remarks !== null && $remarks !== '' && $remarks !== '0') {
            $this->remarks = $remarks;
        }

        return $this->save();
    }

    /**
     * Mark the clearance as not cleared.
     */
    public function markAsNotCleared(?string $remarks = null): bool
    {
        $this->is_cleared = false;
        $this->cleared_by = null;
        $this->cleared_at = null;

        if ($remarks !== null && $remarks !== '' && $remarks !== '0') {
            $this->remarks = $remarks;
        }

        return $this->save();
    }

    /**
     * Get formatted semester name.
     */
    public function getFormattedSemesterAttribute(): string
    {
        return match ($this->semester) {
            1 => '1st Semester',
            2 => '2nd Semester',
            default => 'Unknown Semester',
        };
    }
}
