<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\GeneralSettingsService;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory; // Import Builder
use Illuminate\Database\Eloquent\Model; // Import Cache
// Import GeneralSetting
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

// Add this import

/**
 * @property-read \App\Models\Classes|null $class
 * @property-read string $formatted_end_time
 * @property-read string $formatted_start_time
 * @property-read mixed $subject
 * @property-read string $time_range
 * @property-read \App\Models\Room|null $room
 * @method static Builder<static>|Schedule currentAcademicPeriod()
 * @method static Builder<static>|Schedule newModelQuery()
 * @method static Builder<static>|Schedule newQuery()
 * @method static Builder<static>|Schedule onlyTrashed()
 * @method static Builder<static>|Schedule query()
 * @method static Builder<static>|Schedule withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Schedule withoutTrashed()
 * @mixin \Eloquent
 */
final class Schedule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'schedule';

    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
        'room_id',
        'class_id',
    ];

    protected $casts = [
        'id' => 'integer',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'room_id' => 'integer',
        'class_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getFormattedStartTimeAttribute(): string
    {
        return $this->start_time->format('h:i A');
    }

    public function getFormattedEndTimeAttribute(): string
    {
        return $this->end_time->format('h:i A');
    }

    public function getTimeRangeAttribute(): string
    {
        return $this->formatted_start_time.' - '.$this->formatted_end_time;
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function getSubjectAttribute()
    {
        return $this->class->subject->title;
    }

    public function getSchedulesByClass($classId)
    {
        return $this->where('class_id', $classId)->get();
    }

    /**
     * Scope a query to only include schedules for the current school year and semester,
     * based on the associated class.
     */
    public function scopeCurrentAcademicPeriod(Builder $query): Builder
    {
        // Use the GeneralSettingsService to get effective settings
        $settingsService = app(GeneralSettingsService::class);
        $schoolYear = $settingsService->getCurrentSchoolYearString();
        $semester = $settingsService->getCurrentSemester();

        // Use whereHas to filter based on the related class's properties
        return $query->whereHas('class', function (Builder $classQuery) use ($schoolYear, $semester): void {
            $classQuery->where('school_year', $schoolYear)
                ->where('semester', $semester);
        });
    }
}
