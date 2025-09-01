<?php

declare(strict_types=1);

namespace App\Services;


use Filament\Actions\Action;
use Filament\Notifications\Notification;

final readonly class TimetableNotificationService
{
    public function __construct(private TimetableConflictService $conflictService) {}

    /**
     * Send conflict notifications based on detected conflicts
     */
    public function sendConflictNotifications(array $conflicts): void
    {
        $summary = $this->conflictService->getConflictSummary($conflicts);

        if ($summary['total_conflicts'] > 0) {
            $this->sendConflictSummaryNotification($summary);

            // Send detailed notifications for high-severity conflicts
            $this->sendHighSeverityConflictNotifications($conflicts);
        }
    }

    /**
     * Send success notification when conflicts are resolved
     */
    public function sendConflictResolvedNotification(string $conflictType): void
    {
        Notification::make()
            ->success()
            ->title('Conflict Resolved')
            ->body("The {$conflictType} conflict has been successfully resolved.")
            ->icon('heroicon-o-check-circle')
            ->duration(5000)
            ->send();
    }

    /**
     * Send warning notification for potential conflicts
     */
    public function sendPotentialConflictWarning(array $potentialConflicts): void
    {
        if ($potentialConflicts === []) {
            return;
        }

        $count = count($potentialConflicts);
        $title = 'Potential Schedule Issues Detected';
        $body = "Found {$count} potential scheduling ".($count === 1 ? 'issue' : 'issues').' that may need attention.';

        Notification::make()
            ->warning()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-exclamation-circle')
            ->duration(6000)
            ->actions([
                Action::make('review')
                    ->label('Review Issues')
                    ->button()
                    ->emit('reviewPotentialConflicts', ['conflicts' => $potentialConflicts]),
            ])
            ->send();
    }

    /**
     * Send notification when schedule is successfully saved without conflicts
     */
    public function sendScheduleSavedNotification(): void
    {
        Notification::make()
            ->success()
            ->title('Schedule Saved Successfully')
            ->body('The schedule has been saved without any conflicts.')
            ->icon('heroicon-o-check-circle')
            ->duration(3000)
            ->send();
    }

    /**
     * Send summary notification for all conflicts
     */
    private function sendConflictSummaryNotification(array $summary): void
    {
        $title = $this->getConflictSummaryTitle($summary);
        $body = $this->getConflictSummaryBody($summary);
        $color = $this->getNotificationColor($summary);

        Notification::make()
            ->title($title)
            ->body($body)
            ->color($color)
            ->icon($this->getConflictIcon($summary))
            ->duration(8000)
            ->actions([
                Action::make('view_details')
                    ->label('View Details')
                    ->button()
                    ->emit('openConflictModal'),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->close(),
            ])
            ->send();
    }

    /**
     * Send notifications for high-severity conflicts
     */
    private function sendHighSeverityConflictNotifications(array $conflicts): void
    {
        foreach ($conflicts as $type => $typeConflicts) {
            foreach ($typeConflicts as $conflict) {
                if ($conflict['severity'] === 'high') {
                    $this->sendIndividualConflictNotification($type, $conflict);
                }
            }
        }
    }

    /**
     * Send notification for individual conflict
     */
    private function sendIndividualConflictNotification(string $type, array $conflict): void
    {
        $title = $this->getIndividualConflictTitle($type);
        $body = $this->getIndividualConflictBody($type, $conflict);

        Notification::make()
            ->danger()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-exclamation-triangle')
            ->duration(12000)
            ->actions([
                Action::make('resolve')
                    ->label('Resolve Conflict')
                    ->button()
                    ->color('warning')
                    ->emit('resolveConflict', ['type' => $type, 'conflict' => $conflict]),
                Action::make('view_schedule')
                    ->label('View Schedule')
                    ->button()
                    ->color('gray')
                    ->emit('viewConflictingSchedule', ['conflict' => $conflict]),
            ])
            ->persistent()
            ->send();
    }

    /**
     * Get conflict summary title
     */
    private function getConflictSummaryTitle(array $summary): string
    {
        $total = $summary['total_conflicts'];
        $high = $summary['high_severity'];

        if ($high > 0) {
            return 'Critical Schedule Conflicts Detected';
        }

        return "Schedule Conflicts Found ({$total})";
    }

    /**
     * Get conflict summary body
     */
    private function getConflictSummaryBody(array $summary): string
    {
        $parts = [];

        if ($summary['high_severity'] > 0) {
            $parts[] = "{$summary['high_severity']} critical conflicts";
        }

        if ($summary['medium_severity'] > 0) {
            $parts[] = "{$summary['medium_severity']} medium priority conflicts";
        }

        if ($summary['low_severity'] > 0) {
            $parts[] = "{$summary['low_severity']} low priority conflicts";
        }

        $body = 'Found '.implode(', ', $parts).'.';

        // Add breakdown by type
        $typeBreakdown = [];
        foreach ($summary['by_type'] as $type => $count) {
            if ($count > 0) {
                $typeLabel = match ($type) {
                    'time_room' => 'room/time',
                    'faculty' => 'faculty',
                    'student' => 'student',
                    default => $type
                };
                $typeBreakdown[] = "{$count} {$typeLabel}";
            }
        }

        if ($typeBreakdown !== []) {
            $body .= ' Breakdown: '.implode(', ', $typeBreakdown).'.';
        }

        return $body;
    }

    /**
     * Get notification color based on severity
     */
    private function getNotificationColor(array $summary): string
    {
        if ($summary['high_severity'] > 0) {
            return 'danger';
        }

        if ($summary['medium_severity'] > 0) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * Get conflict icon based on severity
     */
    private function getConflictIcon(array $summary): string
    {
        if ($summary['high_severity'] > 0) {
            return 'heroicon-o-exclamation-triangle';
        }

        if ($summary['medium_severity'] > 0) {
            return 'heroicon-o-exclamation-circle';
        }

        return 'heroicon-o-information-circle';
    }

    /**
     * Get individual conflict title
     */
    private function getIndividualConflictTitle(string $type): string
    {
        return match ($type) {
            'time_room' => 'Room Double-Booking Detected',
            'faculty' => 'Faculty Schedule Conflict',
            'student' => 'Student Schedule Overlap',
            default => 'Schedule Conflict'
        };
    }

    /**
     * Get individual conflict body
     */
    private function getIndividualConflictBody(string $type, array $conflict): string
    {
        if (empty($conflict['conflicts'])) {
            return 'A scheduling conflict has been detected.';
        }

        $firstConflict = $conflict['conflicts'][0];

        return match ($type) {
            'time_room' => $this->getRoomConflictBody($firstConflict),
            'faculty' => $this->getFacultyConflictBody($firstConflict),
            'student' => $this->getStudentConflictBody($firstConflict),
            default => 'A scheduling conflict has been detected.'
        };
    }

    /**
     * Get room conflict body text
     */
    private function getRoomConflictBody(array $conflict): string
    {
        $overlap = $conflict['overlap_details'];

        return "Room conflict: {$overlap['overlap_duration']} minute overlap from {$overlap['overlap_start']} to {$overlap['overlap_end']}.";
    }

    /**
     * Get faculty conflict body text
     */
    private function getFacultyConflictBody(array $conflict): string
    {
        $overlap = $conflict['overlap_details'];

        return "Faculty double-booked: {$overlap['overlap_duration']} minute overlap from {$overlap['overlap_start']} to {$overlap['overlap_end']}.";
    }

    /**
     * Get student conflict body text
     */
    private function getStudentConflictBody(array $conflict): string
    {
        $overlap = $conflict['overlap_details'];

        return "Student schedule conflict: {$overlap['overlap_duration']} minute overlap from {$overlap['overlap_start']} to {$overlap['overlap_end']}.";
    }
}
