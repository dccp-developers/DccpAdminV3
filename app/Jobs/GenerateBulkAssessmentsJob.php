<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StudentEnrollment;
use App\Services\GeneralSettingsService;
use App\Services\BrowsershotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use App\Models\User;
use Filament\Actions\Action;

final class GenerateBulkAssessmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes timeout for bulk operations
    public int $tries = 2;

    private string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $filters,
        private int $userId,
        ?string $jobId = null
    ) {
        $this->jobId = $jobId ?? uniqid('bulk_assessment_', true);
        
        // Use default queue for bulk operations
        $this->onQueue('default');

        Log::info('GenerateBulkAssessmentsJob created', [
            'job_id' => $this->jobId,
            'filters' => $this->filters,
            'user_id' => $this->userId,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting bulk assessment generation', [
                'job_id' => $this->jobId,
                'filters' => $this->filters,
            ]);

            // Get filtered and sorted enrollments
            $enrollments = $this->getFilteredAndSortedEnrollments();

            if ($enrollments->isEmpty()) {
                $this->sendNotification(
                    'No Enrollments Found',
                    'No enrollments match the selected criteria.',
                    'warning'
                );
                return;
            }

            Log::info('Found enrollments for bulk generation', [
                'job_id' => $this->jobId,
                'count' => $enrollments->count(),
            ]);

            // Generate combined PDF
            $pdfPath = $this->generateCombinedAssessmentPdf($enrollments);

            if ($pdfPath && file_exists($pdfPath)) {
                $this->sendSuccessNotification($pdfPath, $enrollments->count());
            } else {
                throw new \Exception('Failed to generate combined assessment PDF');
            }

        } catch (\Exception $e) {
            Log::error('Bulk assessment generation failed', [
                'job_id' => $this->jobId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->sendNotification(
                'Assessment Generation Failed',
                'Error: ' . $e->getMessage(),
                'danger'
            );

            throw $e;
        }
    }

    /**
     * Get filtered enrollments sorted alphabetically by student last name
     */
    private function getFilteredAndSortedEnrollments()
    {
        $settingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();
        $currentSemester = $settingsService->getCurrentSemester();

        $query = StudentEnrollment::where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->where('status', 'Verified By Cashier')
            ->with(['student', 'course', 'subjectsEnrolled.subject', 'studentTuition']);

        // Include deleted records if requested
        if ($this->filters['include_deleted'] ?? true) {
            $query->withTrashed();
        }

        // Apply course filter with proper type casting
        if (isset($this->filters['course_filter']) && $this->filters['course_filter'] !== 'all') {
            $query->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('courses')
                    ->whereRaw('CAST(student_enrollment.course_id AS BIGINT) = courses.id')
                    ->where('courses.code', 'LIKE', $this->filters['course_filter'] . '%');
            });
        }

        // Apply year level filter
        if (isset($this->filters['year_level_filter']) && $this->filters['year_level_filter'] !== 'all') {
            $query->where('academic_year', $this->filters['year_level_filter']);
        }

        // Apply student limit
        if (isset($this->filters['student_limit']) && $this->filters['student_limit'] !== 'all') {
            $query->limit((int) $this->filters['student_limit']);
        }

        // Get enrollments and sort by student last name alphabetically
        $enrollments = $query->get();
        
        return $enrollments->sortBy(function ($enrollment) {
            return $enrollment->student->last_name ?? '';
        })->values(); // Reset array keys after sorting
    }

    /**
     * Generate combined assessment PDF with proper school title
     */
    private function generateCombinedAssessmentPdf($enrollments): ?string
    {
        try {
            $settingsService = app(GeneralSettingsService::class);
            $compiledPath = storage_path('app/public/bulk_assessments_' . date('Y-m-d_H-i-s') . '.pdf');

            // Generate combined HTML for all assessments
            $combinedHtml = $this->generateCombinedAssessmentHtml($enrollments, $settingsService);

            // Generate single PDF with all assessments
            $success = BrowsershotService::generatePdf($combinedHtml, $compiledPath, [
                'format' => 'A4',
                'landscape' => true,
                'print_background' => true,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_left' => 10,
                'margin_right' => 10,
                'timeout' => 600, // 10 minutes for large batches
                'wait_until_network_idle' => false,
            ]);

            if (!$success || !file_exists($compiledPath)) {
                throw new \Exception('BrowsershotService failed to generate combined PDF');
            }

            Log::info('Combined assessment PDF created successfully', [
                'job_id' => $this->jobId,
                'path' => $compiledPath,
                'enrollments_count' => $enrollments->count(),
                'file_size' => filesize($compiledPath),
            ]);

            return $compiledPath;

        } catch (\Exception $e) {
            Log::error('Combined PDF generation failed', [
                'job_id' => $this->jobId,
                'exception' => $e->getMessage(),
                'enrollments_count' => $enrollments->count(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate combined HTML for all assessments with corrected school title
     */
    private function generateCombinedAssessmentHtml($enrollments, $settingsService): string
    {
        $combinedHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            .page-break { page-break-before: always; }
            .first-page { page-break-before: auto; }
        </style></head><body>';

        foreach ($enrollments as $index => $enrollment) {
            // Override the school title to ensure correct display
            $generalSettings = $settingsService->getGlobalSettingsModel();
            if ($generalSettings) {
                $generalSettings->school_portal_title = 'Data Center College of the Philippines of Baguio City, Inc.';
            }

            $data = [
                'student' => $enrollment,
                'subjects' => $enrollment->SubjectsEnrolled,
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
                'tuition' => $enrollment->studentTuition,
                'general_settings' => $generalSettings,
            ];

            $pageClass = $index === 0 ? 'first-page' : 'page-break';
            $assessmentHtml = view('pdf.assesment-form', $data)->render();
            
            // Remove DOCTYPE and html/body tags from individual assessments
            $assessmentHtml = preg_replace('/<!DOCTYPE[^>]*>/', '', $assessmentHtml);
            $assessmentHtml = preg_replace('/<\/?html[^>]*>/', '', $assessmentHtml);
            $assessmentHtml = preg_replace('/<\/?head[^>]*>/', '', $assessmentHtml);
            $assessmentHtml = preg_replace('/<\/?body[^>]*>/', '', $assessmentHtml);
            
            $combinedHtml .= "<div class=\"{$pageClass}\">{$assessmentHtml}</div>";
        }

        $combinedHtml .= '</body></html>';
        return $combinedHtml;
    }

    /**
     * Send success notification with download link
     */
    private function sendSuccessNotification(string $pdfPath, int $count): void
    {
        $publicPath = str_replace(storage_path('app/public/'), '', $pdfPath);
        $downloadUrl = Storage::url($publicPath);

        $this->sendNotification(
            'Assessment Generation Complete',
            "Successfully generated {$count} assessments (sorted alphabetically by last name).",
            'success',
            $downloadUrl
        );
    }

    /**
     * Send notification to the user who initiated the job
     */
    private function sendNotification(string $title, string $body, string $type, ?string $downloadUrl = null): void
    {
        try {
            $user = User::find($this->userId);
            if (!$user) {
                Log::warning('User not found for notification', [
                    'job_id' => $this->jobId,
                    'user_id' => $this->userId,
                ]);
                return;
            }

            $notification = Notification::make()
                ->title($title)
                ->body($body)
                ->persistent();

            // Set notification type
            match ($type) {
                'success' => $notification->success(),
                'warning' => $notification->warning(),
                'danger' => $notification->danger(),
                default => $notification->info(),
            };

            // Add download action if URL provided
            if ($downloadUrl) {
                $notification->actions([
                    Action::make('download')
                        ->label('Download PDF')
                        ->url($downloadUrl)
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-arrow-down-tray'),
                ]);
            }

            $notification->sendToDatabase($user);

        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'job_id' => $this->jobId,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
