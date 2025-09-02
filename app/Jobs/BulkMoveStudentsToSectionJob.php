<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\User;
use App\Services\StudentSectionTransferService;
use App\Services\StudentTransferEmailService;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background job for moving multiple students to a different section
 * Supports batch processing with progress tracking
 */
final class BulkMoveStudentsToSectionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes timeout

    public int $tries = 2;

    public int $maxExceptions = 3;

    private string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $classEnrollmentIds,
        private int $newClassId,
        private ?int $initiatedByUserId = null,
        private bool $notifyStudents = true,
        ?string $jobId = null
    ) {
        $this->jobId = $jobId ?? uniqid('bulk_move_', true);
        $this->onQueue('bulk-student-transfers');

        Log::info('BulkMoveStudentsToSectionJob created', [
            'job_id' => $this->jobId,
            'student_count' => count($this->classEnrollmentIds),
            'new_class_id' => $this->newClassId,
            'initiated_by' => $this->initiatedByUserId,
            'notify_students' => $this->notifyStudents,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(StudentSectionTransferService $transferService, StudentTransferEmailService $emailService): void
    {
        if ($this->batch()?->cancelled()) {
            Log::info('Bulk transfer job cancelled', ['job_id' => $this->jobId]);

            return;
        }

        try {
            Log::info('Starting bulk student section transfer job', [
                'job_id' => $this->jobId,
                'student_count' => count($this->classEnrollmentIds),
                'new_class_id' => $this->newClassId,
            ]);

            // Get class enrollments with related data
            $classEnrollments = ClassEnrollment::with(['student', 'class'])
                ->whereIn('id', $this->classEnrollmentIds)
                ->get();

            if ($classEnrollments->isEmpty()) {
                throw new Exception('No valid class enrollments found for bulk transfer');
            }

            // Get target class information
            $targetClass = Classes::find($this->newClassId);
            if (! $targetClass) {
                throw new Exception("Target class not found: {$this->newClassId}");
            }

            // Perform bulk transfer with progress tracking
            $results = $this->transferWithProgress($transferService, $classEnrollments, $this->newClassId);

            // Send email notifications for successful transfers
            $emailResults = [];
            if (! empty($results['successful_transfers'])) {
                $emailResults = $emailService->sendBulkTransferNotifications($results, $this->newClassId, $this->notifyStudents);

                Log::info('Bulk email notifications processed', [
                    'job_id' => $this->jobId,
                    'student_emails_sent' => $emailResults['student_emails_sent'] ?? 0,
                    'faculty_email_sent' => $emailResults['faculty_email_sent'] ?? false,
                    'student_email_errors' => count($emailResults['student_email_errors'] ?? []),
                ]);
            }

            // Send completion notification
            $this->sendCompletionNotification($results, $targetClass, $emailResults);

            Log::info('Bulk student section transfer job completed', [
                'job_id' => $this->jobId,
                'total_students' => $results['total_students'],
                'success_count' => $results['success_count'],
                'error_count' => $results['error_count'],
                'email_results' => $emailResults,
            ]);

        } catch (Exception $e) {
            Log::error('Bulk student section transfer job failed', [
                'job_id' => $this->jobId,
                'student_count' => count($this->classEnrollmentIds),
                'new_class_id' => $this->newClassId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->sendFailureNotification($e);
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Throwable $exception): void
    {
        Log::error('BulkMoveStudentsToSectionJob permanently failed', [
            'job_id' => $this->jobId,
            'student_count' => count($this->classEnrollmentIds),
            'new_class_id' => $this->newClassId,
            'attempts' => $this->attempts(),
            'exception' => $exception->getMessage(),
        ]);

        $this->sendFailureNotification($exception);
    }

    /**
     * Send completion notification with results
     */
    private function sendCompletionNotification(array $results, Classes $targetClass, array $emailResults = []): void
    {
        try {
            // Get users to notify
            $usersToNotify = collect();

            if ($this->initiatedByUserId) {
                $initiator = User::find($this->initiatedByUserId);
                if ($initiator) {
                    $usersToNotify->push($initiator);
                }
            }

            // Add super admins
            $superAdmins = User::role('super_admin')->get();
            $usersToNotify = $usersToNotify->merge($superAdmins)->unique('id');

            foreach ($usersToNotify as $user) {
                $notificationTitle = $results['error_count'] > 0
                    ? 'Bulk Student Move Completed with Issues'
                    : 'Bulk Student Move Completed Successfully';

                $notificationColor = $results['error_count'] > 0 ? 'warning' : 'success';
                $notificationIcon = $results['error_count'] > 0
                    ? 'heroicon-o-exclamation-triangle'
                    : 'heroicon-o-check-circle';

                $emailStatus = '';
                if (! empty($emailResults)) {
                    $emailStatus = "\n**Email Notifications:**";
                    $emailStatus .= " ✅ {$emailResults['student_emails_sent']} student(s) notified";
                    $emailStatus .= $emailResults['faculty_email_sent'] ? ' | ✅ Faculty notified' : ' | ❌ Faculty email failed/not applicable';
                    if (! empty($emailResults['student_email_errors'])) {
                        $emailStatus .= ' | ⚠️ '.count($emailResults['student_email_errors']).' student email(s) failed';
                    }
                }

                $body = "
                    **Operation:** Bulk Student Move
                    **Subject:** {$targetClass->subject_code}
                    **Target Section:** {$targetClass->section}
                    **Total Students:** {$results['total_students']}
                    **Successfully Moved:** {$results['success_count']}
                    **Failed Moves:** {$results['error_count']}
                    **Updated Records:** Class Enrollments & Subject Enrollments{$emailStatus}
                ";

                if ($results['error_count'] > 0) {
                    $body .= "\n\n**Failed Students:**\n";
                    foreach (array_slice($results['failed_transfers'], 0, 5) as $failure) {
                        $body .= "• {$failure['student_name']}: {$failure['error']}\n";
                    }
                    if (count($results['failed_transfers']) > 5) {
                        $remaining = count($results['failed_transfers']) - 5;
                        $body .= "• ... and {$remaining} more (check logs for details)\n";
                    }
                }

                $notification = Notification::make()
                    ->title($notificationTitle)
                    ->body($body)
                    ->color($notificationColor)
                    ->icon($notificationIcon)
                    ->duration($results['error_count'] > 0 ? 15000 : 10000);

                if ($results['error_count'] > 0) {
                    $notification->persistent();
                }

                $notification->sendToDatabase($user);
            }

            Log::info('Completion notifications sent for bulk transfer', [
                'job_id' => $this->jobId,
                'notification_count' => $usersToNotify->count(),
                'success_count' => $results['success_count'],
                'error_count' => $results['error_count'],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send completion notification for bulk transfer', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send failure notification
     */
    private function sendFailureNotification(Throwable $exception): void
    {
        try {
            // Get users to notify
            $usersToNotify = collect();

            if ($this->initiatedByUserId) {
                $initiator = User::find($this->initiatedByUserId);
                if ($initiator) {
                    $usersToNotify->push($initiator);
                }
            }

            // Add super admins
            $superAdmins = User::role('super_admin')->get();
            $usersToNotify = $usersToNotify->merge($superAdmins)->unique('id');

            foreach ($usersToNotify as $user) {
                Notification::make()
                    ->title('Bulk Student Transfer Failed')
                    ->body('
                        **Operation:** Bulk Student Move
                        **Students Count:** '.count($this->classEnrollmentIds)."
                        **Target Class ID:** {$this->newClassId}
                        **Error:** {$exception->getMessage()}
                        **Job ID:** {$this->jobId}

                        The bulk transfer operation failed completely. Please check the system logs for detailed error information or contact the system administrator.
                    ")
                    ->danger()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->duration(15000)
                    ->persistent()
                    ->sendToDatabase($user);
            }

            Log::info('Failure notifications sent for bulk transfer', [
                'job_id' => $this->jobId,
                'notification_count' => $usersToNotify->count(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send failure notification for bulk transfer', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'bulk-student-transfer',
            'student-count:'.count($this->classEnrollmentIds),
            'target-class:'.$this->newClassId,
            'job-id:'.$this->jobId,
        ];
    }

    /**
     * Transfer students with progress tracking
     */
    private function transferWithProgress(
        StudentSectionTransferService $transferService,
        $classEnrollments,
        int $newClassId
    ): array {
        $results = [
            'total_students' => $classEnrollments->count(),
            'successful_transfers' => [],
            'failed_transfers' => [],
            'success_count' => 0,
            'error_count' => 0,
        ];

        $processed = 0;
        $total = $classEnrollments->count();

        foreach ($classEnrollments as $classEnrollment) {
            if ($this->batch()?->cancelled()) {
                Log::info('Bulk transfer cancelled during processing', ['job_id' => $this->jobId]);
                break;
            }

            try {
                $transferResult = $transferService->transferStudent($classEnrollment, $newClassId);
                $results['successful_transfers'][] = $transferResult;
                $results['success_count']++;

                Log::info('Student transferred in bulk operation', [
                    'job_id' => $this->jobId,
                    'student_id' => $transferResult['student_id'],
                    'progress' => ++$processed.'/'.$total,
                ]);

            } catch (Exception $e) {
                $results['failed_transfers'][] = [
                    'student_id' => $classEnrollment->student_id,
                    'student_name' => $classEnrollment->student?->full_name ?? 'Unknown',
                    'error' => $e->getMessage(),
                ];
                $results['error_count']++;

                Log::error('Student transfer failed in bulk operation', [
                    'job_id' => $this->jobId,
                    'student_id' => $classEnrollment->student_id,
                    'error' => $e->getMessage(),
                    'progress' => ++$processed.'/'.$total,
                ]);
            }

            // Send progress notification every 10 students or at completion
            if ($processed % 10 === 0 || $processed === $total) {
                $this->sendProgressNotification($processed, $total, $results['success_count'], $results['error_count']);
            }
        }

        return $results;
    }

    /**
     * Send progress notification
     */
    private function sendProgressNotification(int $processed, int $total, int $successCount, int $errorCount): void
    {
        if (! $this->initiatedByUserId) {
            return;
        }

        try {
            $user = User::find($this->initiatedByUserId);
            if (! $user) {
                return;
            }

            $progressPercentage = round(($processed / $total) * 100);
            $isComplete = $processed === $total;

            $title = $isComplete ? 'Bulk Transfer Complete' : 'Bulk Transfer Progress';
            $body = "
                **Progress:** {$processed}/{$total} ({$progressPercentage}%)
                **Successful:** {$successCount}
                **Failed:** {$errorCount}
            ";

            if ($isComplete) {
                $body .= "\n**Status:** Transfer operation completed";
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->info()
                ->icon($isComplete ? 'heroicon-o-check-circle' : 'heroicon-o-clock')
                ->duration($isComplete ? 8000 : 3000)
                ->sendToDatabase($user);

        } catch (Exception $e) {
            Log::error('Failed to send progress notification', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60]; // Wait 30 seconds, then 60 seconds before retrying
    }
}
