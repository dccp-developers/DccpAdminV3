<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StudentEnrollment;
use App\Services\BrowsershotService;
use App\Services\GeneralSettingsService;
use Exception;
use App\Models\User;
use App\Notifications\PdfGenerationCompleted;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateAssessmentPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180; // 3 minutes timeout

    public int $tries = 2;

    private string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private StudentEnrollment $enrollmentRecord,
        ?string $jobId = null,
        private bool $createNewFile = false
    ) {
        $this->jobId = $jobId ?? uniqid('pdf_', true);

        // Set queue name
        $this->onQueue('pdf-generation');

        Log::info('GenerateAssessmentPdfJob created', [
            'enrollment_id' => $this->enrollmentRecord->id,
            'job_id' => $this->jobId,
            'create_new_file' => $this->createNewFile,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting PDF generation job', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
            ]);

            $this->updateProgress(10, 'Initializing PDF generation...');

            // Check if PDF already exists and is recent
            $existingResource = $this->enrollmentRecord
                ->resources()
                ->where('type', 'assessment')
                ->where('created_at', '>', now()->subHours(1)) // Consider PDFs from last hour as fresh
                ->first();

            if (
                $existingResource &&
                file_exists($existingResource->file_path)
            ) {
                Log::info('Using existing recent PDF', [
                    'job_id' => $this->jobId,
                    'existing_path' => $existingResource->file_path,
                ]);
                $this->updateProgress(100, 'Using existing PDF');

                return;
            }

            $this->updateProgress(25, 'Preparing data...');

            // Generate PDF
            $pdfPath = $this->generatePdf();

            $this->updateProgress(90, 'Saving PDF record...');

            // Save resource record
            $this->saveResourceRecord($pdfPath);

            $this->updateProgress(100, 'PDF generated successfully');

            // Send success notification to super_admin users
            $this->sendNotificationToSuperAdmins(false, 'PDF generated successfully');

            Log::info('PDF generation job completed successfully', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
                'pdf_path' => $pdfPath,
            ]);
        } catch (Exception $e) {
            $this->updateProgress(100, 'Failed: '.$e->getMessage(), true);

            // Send failure notification to super_admin users
            $this->sendNotificationToSuperAdmins(true, 'PDF generation failed', $e->getMessage());

            Log::error('PDF generation job failed', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('PDF generation job failed permanently', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->enrollmentRecord->id,
            'error' => $exception->getMessage(),
        ]);

        $this->updateProgress(
            100,
            'Failed permanently: '.$exception->getMessage(),
            true
        );

        // Send failure notification to super_admin users
        $this->sendNotificationToSuperAdmins(true, 'PDF generation failed permanently', $exception->getMessage());
    }

    /**
     * Get the job ID for tracking
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Generate the PDF using optimized Browsershot
     */
    private function generatePdf(): string
    {
        $settingsService = new GeneralSettingsService();

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

        // Add timestamp if creating new file to ensure uniqueness
        $timestamp = $this->createNewFile ? '-'.now()->format('YmdHis') : '';
        $assessmentFilename = "assmt-{$this->enrollmentRecord->id}{$timestamp}-{$randomChars}.pdf";

        // Ensure directories exist
        $privateDir = storage_path('app/private');
        if (! File::exists($privateDir)) {
            File::makeDirectory($privateDir, 0755, true);
        }

        $assessmentPath =
            $privateDir.DIRECTORY_SEPARATOR.$assessmentFilename;

        $this->updateProgress(50, 'Rendering HTML...');

        // Render HTML
        $html = view('pdf.assesment-form', $data)->render();

        $this->updateProgress(70, 'Converting to PDF...');

        // Use BrowsershotService for consistent configuration
        $success = BrowsershotService::generatePdf($html, $assessmentPath, [
            'format' => 'A4',
            'landscape' => true,
            'print_background' => true,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'margin_right' => 10,
            'timeout' => 120,
            'wait_until_network_idle' => false,
        ]);

        if ($success === false || ($success === '' || $success === '0')) {
            throw new Exception('BrowsershotService failed to generate PDF');
        }

        // Verify file was created
        if (! file_exists($assessmentPath) || filesize($assessmentPath) === 0) {
            throw new Exception('PDF was not generated or is empty');
        }

        Log::info('PDF generated successfully', [
            'job_id' => $this->jobId,
            'path' => $assessmentPath,
            'size' => filesize($assessmentPath),
        ]);

        return $assessmentPath;
    }

    /**
     * Save the resource record in database
     */
    private function saveResourceRecord(string $pdfPath): void
    {
        $settingsService = new GeneralSettingsService();
        $filename = basename($pdfPath);

        // Try to upload to public storage (Supabase) but don't fail if it doesn't work
        try {
            Storage::disk('public')->putFileAs('', $pdfPath, $filename);
            Log::info('PDF uploaded to public storage', [
                'filename' => $filename,
            ]);
        } catch (Exception $e) {
            Log::warning(
                'Failed to upload PDF to public storage: '.$e->getMessage()
            );
            // Continue anyway, we have the local file
        }

        // Save resource record - create new or update existing based on createNewFile flag
        if ($this->createNewFile) {
            // Always create a new resource record
            $this->enrollmentRecord->resources()->create([
                'resourceable_id' => $this->enrollmentRecord->id,
                'resourceable_type' => $this->enrollmentRecord::class,
                'type' => 'assessment',
                'file_path' => $pdfPath,
                'file_name' => $filename,
                'mime_type' => 'application/pdf',
                'disk' => 'local',
                'file_size' => filesize($pdfPath),
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
                    'generation_method' => 'browsershot_job',
                    'generated_at' => now()->toISOString(),
                    'is_new_version' => true,
                ],
            ]);
        } else {
            // Update or create existing resource record (original behavior)
            $this->enrollmentRecord->resources()->updateOrCreate(
                [
                    'resourceable_id' => $this->enrollmentRecord->id,
                    'resourceable_type' => $this->enrollmentRecord::class,
                    'type' => 'assessment',
                ],
                [
                    'file_path' => $pdfPath,
                    'file_name' => $filename,
                    'mime_type' => 'application/pdf',
                    'disk' => 'local',
                    'file_size' => filesize($pdfPath),
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
                        'generation_method' => 'browsershot_job',
                        'generated_at' => now()->toISOString(),
                    ],
                ]
            );
        }

        Log::info('Resource record saved', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->enrollmentRecord->id,
        ]);
    }

    /**
     * Update job progress
     */
    private function updateProgress(
        int $percentage,
        string $message,
        bool $failed = false
    ): void {
        $progressData = [
            'percentage' => $percentage,
            'message' => $message,
            'failed' => $failed,
            'updated_at' => now()->toISOString(),
            'enrollment_id' => $this->enrollmentRecord->id,
            'type' => 'pdf_generation',
        ];

        // Store in Redis with 1 hour expiration
        cache()->put("pdf_job_progress:{$this->jobId}", $progressData, 3600);

        Log::info('PDF job progress updated', [
            'job_id' => $this->jobId,
            'progress' => $progressData,
        ]);
    }

    /**
     * Send notification to all super_admin users
     */
    private function sendNotificationToSuperAdmins(bool $failed, string $message, ?string $errorMessage = null): void
    {
        try {
            // Get all super_admin users
            $superAdmins = User::role('super_admin')->get();

            if ($superAdmins->isEmpty()) {
                Log::warning('No super_admin users found to notify about PDF generation completion', [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->enrollmentRecord->id,
                ]);
                return;
            }

            // Send notification to all super_admin users
            LaravelNotification::send(
                $superAdmins,
                new PdfGenerationCompleted(
                    $this->enrollmentRecord,
                    $failed,
                    $message,
                    $errorMessage
                )
            );

            Log::info('Notification sent to super_admin users', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
                'failed' => $failed,
                'recipients_count' => $superAdmins->count(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send notification to super_admin users', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentRecord->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
