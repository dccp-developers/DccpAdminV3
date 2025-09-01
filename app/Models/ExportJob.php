<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $filters_display
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob query()
 * @mixin \Eloquent
 */
class ExportJob extends Model
{
    protected $fillable = [
        'job_id',
        'user_id',
        'export_type',
        'filters',
        'format',
        'status',
        'file_content',
        'file_name',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $fileContent, string $fileName): void
    {
        $this->update([
            'status' => 'completed',
            'file_content' => $fileContent,
            'file_name' => $fileName,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function getFiltersDisplayAttribute(): string
    {
        $filters = $this->filters;
        $display = [];

        if (isset($filters['course_filter']) && $filters['course_filter'] !== 'all') {
            $display[] = 'Course: ' . $filters['course_filter'];
        }

        if (isset($filters['year_level_filter']) && $filters['year_level_filter'] !== 'all') {
            $display[] = 'Year: ' . $filters['year_level_filter'];
        }

        return empty($display) ? 'All Students' : implode(', ', $display);
    }
}
