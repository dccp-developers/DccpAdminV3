<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StudentEnrollment;
use App\Models\User;
use App\Notifications\MigrateToStudent;
use App\Services\BrowsershotService;
use App\Services\GeneralSettingsService;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SendAssessmentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes timeout

    public int $tries = 3;

    private string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private StudentEnrollment $enrollmentRecord,
        ?string $jobId = null
    ) {
        $this->jobId = $jobId ?? uniqid('assessment_', true);

        // Set queue name
        $this->onQueue('assessments');

        Log::info('SendAssessmentNotificationJob created', [
            'enrollment_id' => $this->enrollmentRecord->id,
            'job_id' => $this->jobId,
            'student_email' => $this->enrollmentRecord->student?->email,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting assessment notification job', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
            ]);

            if (! $this->enrollmentRecord->student?->email) {
                throw new Exception(
                    'Student email not found for enrollment ID: '.
                        $this->enrollmentRecord->id
                );
            }

            // Ensure PDF is generated and available
            $this->ensurePdfIsAvailable();

            // Send the notification
            NotificationFacade::route(
                'mail',
                $this->enrollmentRecord->student->email
            )->notify(new MigrateToStudent($this->enrollmentRecord));

            // Send database notification to super admins using Filament notifications
            try {
                $admins = User::role('super_admin')->get();
                Log::info('Preparing to send success notification', [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->enrollmentRecord->id,
                    'admin_count' => $admins->count(),
                ]);

                foreach ($admins as $admin) {
                    Notification::make()
                        ->title('Assessment Resent Successfully')
                        ->body("Assessment notification successfully sent to {$this->enrollmentRecord->student->first_name} {$this->enrollmentRecord->student->last_name} ({$this->enrollmentRecord->student->email}) for enrollment #{$this->enrollmentRecord->id}")
                        ->success()
                        ->icon('heroicon-o-check-circle')
                        ->persistent()
                        ->sendToDatabase($admin);
                }

                Log::info(
                    'Success notification sent to database via Filament notifications',
                    [
                        'job_id' => $this->jobId,
                        'enrollment_id' => $this->enrollmentRecord->id,
                        'admin_count' => $admins->count(),
                    ]
                );
            } catch (Exception $notifException) {
                Log::error(
                    'Exception occurred while sending success notification',
                    [
                        'job_id' => $this->jobId,
                        'enrollment_id' => $this->enrollmentRecord->id,
                        'error' => $notifException->getMessage(),
                        'trace' => $notifException->getTraceAsString(),
                    ]
                );
            }

            Log::info('Assessment notification job completed successfully', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
            ]);
        } catch (Exception $e) {
            Log::error('Assessment notification job failed', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send database notification to super admins using Filament notifications
            try {
                $admins = User::role('super_admin')->get();
                Log::info('Preparing to send failure notification', [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->enrollmentRecord->id,
                    'admin_count' => $admins->count(),
                    'error_message' => $e->getMessage(),
                ]);

                foreach ($admins as $admin) {
                    Notification::make()
                        ->title('Assessment Resend Failed')
                        ->body("Assessment notification failed for enrollment #{$this->enrollmentRecord->id}: {$e->getMessage()}")
                        ->danger()
                        ->icon('heroicon-o-x-circle')
                        ->persistent()
                        ->sendToDatabase($admin);
                }

                Log::info('Failure notification sent to admins', [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->enrollmentRecord->id,
                ]);
            } catch (Exception $notifException) {
                Log::error(
                    'Exception occurred while sending failure notification',
                    [
                        'job_id' => $this->jobId,
                        'enrollment_id' => $this->enrollmentRecord->id,
                        'error' => $notifException->getMessage(),
                        'trace' => $notifException->getTraceAsString(),
                    ]
                );
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Assessment notification job failed permanently', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->enrollmentRecord->id,
            'error' => $exception->getMessage(),
        ]);

        // Send database notification to super admins using Filament notifications
        try {
            $admins = User::role('super_admin')->get();
            Log::info('Preparing to send permanent failure notification', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
                'admin_count' => $admins->count(),
                'exception_message' => $exception->getMessage(),
            ]);

            foreach ($admins as $admin) {
                Notification::make()
                    ->title('Assessment Resend Failed Permanently')
                    ->body("Assessment notification job for enrollment #{$this->enrollmentRecord->id} failed permanently after all retries: {$exception->getMessage()}")
                    ->danger()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->persistent()
                    ->sendToDatabase($admin);
            }

            Log::info('Permanent failure notification sent to admins', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
            ]);
        } catch (Exception $notifException) {
            Log::error(
                'Exception occurred while sending permanent failure notification',
                [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->enrollmentRecord->id,
                    'error' => $notifException->getMessage(),
                    'trace' => $notifException->getTraceAsString(),
                ]
            );
        }
    }

    /**
     * Get the job ID for tracking
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Ensure PDF is available, generate if needed
     */
    private function ensurePdfIsAvailable(): void
    {
        // Check if PDF already exists and is recent
        $existingResource = $this->enrollmentRecord
            ->resources()
            ->where('type', 'assessment')
            ->where('created_at', '>', now()->subHours(1))
            ->first();

        if ($existingResource && file_exists($existingResource->file_path)) {
            Log::info('PDF already exists, using existing file', [
                'job_id' => $this->jobId,
                'existing_path' => $existingResource->file_path,
            ]);

            return;
        }

        Log::info('PDF not found or expired, generating new PDF', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->enrollmentRecord->id,
        ]);

        // Generate PDF synchronously
        $this->generatePdfSynchronously();
    }

    /**
     * Generate PDF synchronously
     */
    private function generatePdfSynchronously(): void
    {
        $settingsService = new GeneralSettingsService();

        // Load additional fees relationship if not already loaded
        if (!$this->enrollmentRecord->relationLoaded('additionalFees')) {
            $this->enrollmentRecord->load('additionalFees');
        }

        // Prepare data for the view
        $data = [
            'student' => $this->enrollmentRecord,
            'subjects' => $this->enrollmentRecord->SubjectsEnrolled,
            'school_year' => mb_convert_encoding(
                $settingsService->getCurrentSchoolYearString() ?? '',
                'UTF-8',
                'auto'
            ),
            'semester' => mb_convert_encoding(
                $settingsService->getAvailableSemesters()[$settingsService->getCurrentSemester()] ?? '',
                'UTF-8',
                'auto'
            ),
            'tuition' => $this->enrollmentRecord->studentTuition,
            'general_settings' => $settingsService->getGlobalSettingsModel(),
        ];

        // Generate unique filename
        $randomChars = mb_substr(
            str_shuffle(
                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
            ),
            0,
            10
        );
        $assessmentFilename = "assmt-{$this->enrollmentRecord->id}-{$randomChars}.pdf";

        // Ensure directories exist
        $privateDir = storage_path('app/private');
        if (! File::exists($privateDir)) {
            File::makeDirectory(
                $privateDir,
                0755,
                true
            );
        }

        $assessmentPath =
            $privateDir.DIRECTORY_SEPARATOR.$assessmentFilename;

        Log::info('Generating PDF synchronously', [
            'job_id' => $this->jobId,
            'path' => $assessmentPath,
        ]);

        // Render HTML
        $html = view('pdf.assesment-form', $data)->render();

        // Generate PDF using BrowsershotService with proper Nix path detection
        $pdfOptions = [
            'format' => 'A4',
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'margin_right' => 10,
            'print_background' => true,
            'landscape' => true,
            'wait_until_network_idle' => true,
            'timeout' => 120,
        ];

        Log::info('Generating PDF with BrowsershotService', [
            'job_id' => $this->jobId,
            'path' => $assessmentPath,
            'options' => $pdfOptions,
        ]);

        $success = BrowsershotService::generatePdf(
            $html,
            $assessmentPath,
            $pdfOptions
        );

        if ($success === false || ($success === '' || $success === '0')) {
            throw new Exception('BrowsershotService failed to generate PDF');
        }

        // Verify file was created
        if (! file_exists($assessmentPath) || filesize($assessmentPath) === 0) {
            throw new Exception('PDF was not generated or is empty');
        }

        // Try to upload to public storage
        try {
            Storage::disk('public')->putFileAs(
                '',
                $assessmentPath,
                $assessmentFilename
            );
            Log::info('PDF uploaded to public storage', [
                'filename' => $assessmentFilename,
            ]);
        } catch (Exception $e) {
            Log::warning(
                'Failed to upload PDF to public storage: '.$e->getMessage()
            );
        }

        // Save resource record to database
        try {
            // Delete any existing assessment resources for this enrollment to avoid conflicts
            $this->enrollmentRecord
                ->resources()
                ->where('type', 'assessment')
                ->delete();

            $resource = \App\Models\Resource::create([
                'resourceable_id' => $this->enrollmentRecord->id,
                'resourceable_type' => $this->enrollmentRecord::class,
                'type' => 'assessment',
                'file_path' => $assessmentPath,
                'file_name' => $assessmentFilename,
                'mime_type' => 'application/pdf',
                'disk' => 'local',
                'file_size' => filesize($assessmentPath),
                'metadata' => [
                    'school_year' => mb_convert_encoding(
                        $settingsService->getCurrentSchoolYearString() ?? '',
                        'UTF-8',
                        'auto'
                    ),
                    'semester' => mb_convert_encoding(
                        $settingsService->getAvailableSemesters()[$settingsService->getCurrentSemester()] ?? '',
                        'UTF-8',
                        'auto'
                    ),
                    'generation_method' => 'browsershot_sync',
                    'generated_at' => now()->toISOString(),
                ],
            ]);

            Log::info('Resource record created successfully', [
                'job_id' => $this->jobId,
                'resource_id' => $resource->id,
                'enrollment_id' => $this->enrollmentRecord->id,
                'file_name' => $assessmentFilename,
                'resource_table_check' => \App\Models\Resource::where(
                    'resourceable_id',
                    $this->enrollmentRecord->id
                )
                    ->where('type', 'assessment')
                    ->count(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to save resource record to database', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_path' => $assessmentPath,
            ]);

            // Don't throw exception here, as the PDF was generated successfully
            // Just log the error and continue
        }

        Log::info('PDF generated and saved successfully', [
            'job_id' => $this->jobId,
            'path' => $assessmentPath,
            'size' => filesize($assessmentPath),
        ]);
    }
}
