<?php

namespace App\Jobs;

use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class GenerateStudentListPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Classes $class,
        public ?int $userId = null
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting student list PDF generation', [
                'class_id' => $this->class->id,
                'user_id' => $this->userId,
            ]);

            // Efficiently load enrolled students with necessary relationships
            $enrolledStudents = $this->class->class_enrollments()
                ->with([
                    'student:id,student_id,first_name,last_name,middle_name,course_id,academic_year',
                    'student.course:id,code',
                ])
                ->where('status', true) // Only active enrollments
                ->get()
                ->sortBy([
                    ['student.last_name', 'asc'],
                    ['student.first_name', 'asc'],
                ]);

            // Prepare data for PDF
            $data = [
                'class' => $this->class,
                'students' => $enrolledStudents,
                'generated_at' => now()->format('F j, Y \a\t g:i A'),
                'total_students' => $enrolledStudents->count(),
            ];

            // Generate HTML content
            $html = view('exports.student-list-pdf', $data)->render();

            // Generate filename
            $filename = sprintf(
                'student_list_%s_%s_%s_%s.pdf',
                str_replace(' ', '_', $this->class->subject_code ?? 'Unknown'),
                str_replace(' ', '_', $this->class->section ?? 'Unknown'),
                str_replace(' ', '_', $this->class->semester ?? 'Unknown'),
                str_replace('-', '_', $this->class->school_year ?? 'Unknown')
            );

            // Ensure directory exists
            $directory = 'exports/student-lists';
            Storage::disk('public')->makeDirectory($directory);

            // Full path for the PDF
            $path = $directory.'/'.$filename;
            $fullPath = Storage::disk('public')->path($path);

            // Calculate scaling based on number of students
            $studentCount = $enrolledStudents->count();
            $scale = $this->calculateScale($studentCount);

            // Generate PDF using Browsershot with auto-scaling
            $browsershot = Browsershot::html($html)
                ->format('A4')
                ->margins(8, 8, 8, 8) // Smaller margins for more space
                ->scale($scale)
                ->waitUntilNetworkIdle()
                ->timeout(120)
                ->showBackground() // Ensure background colors are printed
                ->emulateMedia('print') // Use print media styles
                ->setOption('args', [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--no-first-run',
                    '--disable-background-timer-throttling',
                    '--disable-backgrounding-occluded-windows',
                    '--disable-renderer-backgrounding',
                    '--print-to-pdf-no-header',
                    '--run-all-compositor-stages-before-draw',
                    '--disable-extensions',
                ]);

            // Set Chrome path if available
            $chromePath = $this->getChromePath();
            if ($chromePath) {
                $browsershot->setChromePath($chromePath);
            }

            $browsershot->save($fullPath);

            Log::info('Student list PDF generated successfully', [
                'class_id' => $this->class->id,
                'filename' => $filename,
                'student_count' => $studentCount,
                'scale' => $scale,
                'file_size' => filesize($fullPath),
            ]);

            // Send success notification to user
            if ($this->userId) {
                $this->sendSuccessNotification($filename, $path, $enrolledStudents->count());
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate student list PDF', [
                'class_id' => $this->class->id,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send error notification to user
            if ($this->userId) {
                $this->sendErrorNotification($e->getMessage());
            }

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Calculate appropriate scale based on number of students
     */
    private function calculateScale(int $studentCount): float
    {
        // Balanced scaling for readability and single-page fitting
        if ($studentCount <= 20) {
            return 1.0; // Full scale for small lists
        } elseif ($studentCount <= 30) {
            return 0.9; // Slightly smaller for medium lists
        } elseif ($studentCount <= 40) {
            return 0.8; // More compact for larger lists
        } elseif ($studentCount <= 50) {
            return 0.75; // Moderate compression for 40+ students
        } elseif ($studentCount <= 70) {
            return 0.7; // More compression for 50+ students
        } elseif ($studentCount <= 100) {
            return 0.65; // Strong compression for very large lists
        } else {
            return 0.6; // Maximum compression for 100+ students
        }
    }

    /**
     * Get Chrome path for different environments
     */
    private function getChromePath(): ?string
    {
        // Common Chrome paths for different systems
        $paths = [
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/snap/bin/chromium',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Return null to use system default
        return null;
    }

    /**
     * Send success notification to user
     */
    private function sendSuccessNotification(string $filename, string $path, int $studentCount): void
    {
        $downloadUrl = route('download.student-list', ['filename' => $filename]);

        Notification::make()
            ->title('Student List PDF Generated Successfully')
            ->body("PDF generated with {$studentCount} students. Click the download button below to get your file.")
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url($downloadUrl)
                    ->openUrlInNewTab(),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->color('gray')
                    ->close(),
            ])
            ->persistent()
            ->sendToDatabase(\App\Models\User::find($this->userId))
            ->send();
    }

    /**
     * Send error notification to user
     */
    private function sendErrorNotification(string $error): void
    {
        Notification::make()
            ->title('PDF Generation Failed')
            ->body("Failed to generate student list PDF. Error: {$error}")
            ->danger()
            ->persistent()
            ->sendToDatabase(\App\Models\User::find($this->userId));
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Student list PDF job failed permanently', [
            'class_id' => $this->class->id,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        if ($this->userId) {
            $this->sendErrorNotification($exception->getMessage());
        }
    }
}
