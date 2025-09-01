<?php

namespace App\Jobs;

use App\Services\StudentReportingService;
use App\Services\GeneralSettingsService;
use App\Models\User;
use App\Models\ExportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

class ExportStudentDataJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected int $exportJobId;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(int $exportJobId)
    {
        $this->exportJobId = $exportJobId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $exportJob = ExportJob::find($this->exportJobId);

        if (!$exportJob) {
            Log::error('Export job not found', ['export_job_id' => $this->exportJobId]);
            return;
        }

        try {
            // Mark as processing
            $exportJob->markAsProcessing();

            // Get services
            $settingsService = app(GeneralSettingsService::class);
            $reportingService = new StudentReportingService($settingsService);

            // Generate the export content
            $fileContent = $reportingService->generateFilteredExportContent($exportJob->filters, $exportJob->format);
            $fileName = $this->generateFileName($exportJob);

            // Mark as completed with file content
            $exportJob->markAsCompleted($fileContent, $fileName);

            // Send notification to user
            $this->sendCompletionNotification($exportJob);

            Log::info('Export job completed successfully', [
                'export_job_id' => $this->exportJobId,
                'user_id' => $exportJob->user_id,
                'format' => $exportJob->format,
            ]);

        } catch (\Exception $e) {
            // Mark as failed
            $exportJob->markAsFailed($e->getMessage());

            // Log the error
            Log::error('Export job failed: ' . $e->getMessage(), [
                'export_job_id' => $this->exportJobId,
                'user_id' => $exportJob->user_id,
                'filters' => $exportJob->filters,
                'format' => $exportJob->format,
                'exception' => $e,
            ]);

            // Send failure notification
            $this->sendFailureNotification($exportJob, $e->getMessage());
        }
    }

    private function generateFileName(ExportJob $exportJob): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filterSuffix = '';

        if ($exportJob->filters['course_filter'] !== 'all') {
            $filterSuffix .= '_' . $exportJob->filters['course_filter'];
        }

        if ($exportJob->filters['year_level_filter'] !== 'all') {
            $filterSuffix .= '_year' . $exportJob->filters['year_level_filter'];
        }

        $extension = $exportJob->format === 'pdf' ? 'html' : 'csv';

        return "student_export_{$timestamp}{$filterSuffix}.{$extension}";
    }

    private function sendCompletionNotification(ExportJob $exportJob): void
    {
        $user = $exportJob->user;

        if ($user) {
            Notification::make()
                ->title('Export Completed')
                ->body("Your {$exportJob->format} export is ready for download. Filters: {$exportJob->filters_display}")
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Download')
                        ->url(route('export.download', $exportJob->id))
                        ->openUrlInNewTab()
                ])
                ->sendToDatabase($user);
        }
    }

    private function sendFailureNotification(ExportJob $exportJob, string $errorMessage): void
    {
        $user = $exportJob->user;

        if ($user) {
            Notification::make()
                ->title('Export Failed')
                ->body("Your export failed: {$errorMessage}")
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
