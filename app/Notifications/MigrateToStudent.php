<?php



namespace App\Notifications;

use App\Models\GeneralSetting;
use App\Services\BrowsershotService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class MigrateToStudent extends Notification
{
    use Queueable;

    private ?string $generatedPdfPath = null;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $record) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $assessmentPath = null;
        $pdfGenerationError = null;

        try {
            // Attempt to generate PDF and get the local path
            if (
                $this->generatedPdfPath &&
                file_exists($this->generatedPdfPath)
            ) {
                $assessmentPath = $this->generatedPdfPath;
                Log::info('Using cached PDF path for email attachment.', [
                    'path' => $assessmentPath,
                ]);
            } else {
                $pdfContents = $this->generatePdf();
                if (isset($pdfContents['assessment']['path'])) {
                    $assessmentPath = $pdfContents['assessment']['path'];
                    $this->generatedPdfPath = $assessmentPath;
                    Log::info(
                        'PDF generated successfully for email attachment.',
                        [
                            'assessment_path' => $assessmentPath,
                            'file_exists' => file_exists($assessmentPath),
                            'file_size' => file_exists($assessmentPath)
                                ? filesize($assessmentPath)
                                : 'N/A',
                        ]
                    );
                } else {
                    Log::error(
                        'PDF generation method did not return a valid path.'
                    );
                    $pdfGenerationError =
                        'PDF generation process completed but did not return a valid file path.';
                }
            }
        } catch (Exception $e) {
            $pdfGenerationError = $e->getMessage();
            Log::error(
                'PDF Generation Failed During Email Preparation: '.
                    $pdfGenerationError
            );
            Log::error('Stack trace: '.$e->getTraceAsString());
        }

        $message = new MailMessage;
        $message->subject(
            'Enrollment Confirmation - Important Information Enclosed'
        );
        $message->greeting(
            'Dear '.
                mb_convert_encoding(
                    $this->record->first_name ?? 'Student',
                    'UTF-8',
                    'auto'
                ).
                ','
        );
        $message->line(
            'We are pleased to inform you that your enrollment has been successfully verified and processed.'
        );
        $message->line(
            'You are now officially enrolled as a student at our institution for the upcoming academic term.'
        );

        if ($assessmentPath && file_exists($assessmentPath)) {
            $message->line(
                'Please find attached to this email the following important documents:'
            );
            $message->line(
                '1. Class Schedule (Details within Assessment Form)'
            );
            $message->line(
                '2. Assessment Form (including payment information)'
            );
            $message->attach($assessmentPath, [
                'as' => 'Assessment_Form.pdf',
                'mime' => 'application/pdf',
            ]);
            Log::info('Successfully attached PDF to email.', [
                'path' => $assessmentPath,
            ]);
        } else {
            Log::warning(
                'Assessment PDF file not found or generation failed for attachment.',
                [
                    'path_expected' => $assessmentPath,
                    'generation_error' => $pdfGenerationError,
                ]
            );
            $message->line(
                'Please find below your enrollment confirmation details.'
            );
            $message->line(
                'Note: There was an issue generating or attaching your Assessment Form PDF.'
            );
            if ($pdfGenerationError !== null && $pdfGenerationError !== '' && $pdfGenerationError !== '0') {
                $message->line('Error details: '.$pdfGenerationError);
            }
            $message->line(
                'Please contact the Student Services office if you require the Assessment Form.'
            );
        }

        $message->line(
            'We kindly request that you review attached documents (if any) carefully and keep them for your records.'
        );
        $message->line(
            'Should you have any questions or concerns regarding your enrollment, class schedule, or payment details, please do not hesitate to contact our Student Services office.'
        );
        $message->line(
            'We are excited to welcome you to our academic community and look forward to supporting you in your educational journey.'
        );
        $message->line('Best wishes for a successful semester ahead.');
        $message->salutation('Sincerely,');
        $message->salutation(
            'Data Center College of the Philippines - Baguio City INC.'
        );

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $assessmentPath = $this->generatedPdfPath;
        if ($assessmentPath === null || $assessmentPath === '' || $assessmentPath === '0') {
            $assessmentPath = $this->record
                ->resources()
                ->where('type', 'assessment')
                ->first()?->file_path;
        }

        return [
            'title' => 'Enrollment Confirmation',
            'message' => 'Your enrollment has been successfully verified and processed.',
            'assessment_path' => $assessmentPath,
        ];
    }

    /**
     * Get the Filament representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        if ($this->generatedPdfPath === null || $this->generatedPdfPath === '' || $this->generatedPdfPath === '0') {
            try {
                $pdfContents = $this->generatePdf();
                $this->generatedPdfPath =
                    $pdfContents['assessment']['path'] ?? null;
                Log::info('PDF generated for database notification.', [
                    'path' => $this->generatedPdfPath,
                ]);
            } catch (Exception $e) {
                Log::error(
                    'Failed to generate PDF for database notification: '.
                        $e->getMessage()
                );
                $this->generatedPdfPath = null;
            }
        }

        $resource = null;
        if ($this->generatedPdfPath && file_exists($this->generatedPdfPath)) {
            $fileName = basename($this->generatedPdfPath);
            $resource = $this->record
                ->resources()
                ->where('type', 'assessment')
                ->where('file_name', $fileName)
                ->first();
        }

        if (! $resource) {
            $resource = $this->record
                ->resources()
                ->where('type', 'assessment')
                ->latest()
                ->first();
            Log::warning(
                'Could not find exact resource match by filename, using latest.',
                [
                    'expected_filename' => basename(
                        $this->generatedPdfPath ?? 'N/A'
                    ),
                ]
            );
        }

        $notificationData = [
            'title' => 'Enrollment Confirmation',
            'message' => 'Your enrollment has been successfully verified and processed.',
        ];

        if ($resource) {
            Log::info('Preparing database notification with PDF resource.', [
                'assessment_path' => $resource->file_path,
                'file_exists_on_disk' => file_exists($resource->file_path),
                'resource_id' => $resource->id,
            ]);
            $notificationData['actions'] = [
                [
                    'label' => 'View Assessment',
                    'url' => route(
                        'filament.admin.resources.student-enrollments.view-resource',
                        [
                            'record' => $this->record->id,
                            'resourceId' => $resource->id,
                        ]
                    ),
                    'icon' => 'heroicon-o-document',
                ],
            ];
        } else {
            Log::error(
                'No assessment resource found or could be linked for database notification.',
                [
                    'enrollment_id' => $this->record->id,
                    'generated_path' => $this->generatedPdfPath,
                ]
            );
        }

        return $notificationData;
    }

    private function generatePdf()
    {
        $originalTimeLimit = ini_get('max_execution_time');
        set_time_limit(180);

        try {
            // Use Browsershot for PDF generation
            Log::info('Attempting PDF generation with Spatie/Browsershot.');

            return $this->generatePdfWithBrowsershot();
        } catch (Exception $e) {
            // Log the error from Browsershot
            Log::error(
                'Browsershot PDF generation failed: '.$e->getMessage()
            );
            Log::error('Stack trace: '.$e->getTraceAsString());
            // Re-throw the exception so it can be caught by the toMail method
            throw $e;
        } finally {
            set_time_limit($originalTimeLimit);
        }
    }

    private function generatePdfWithBrowsershot()
    {
        $general_settings = GeneralSetting::first();

        // Ensure all data is properly UTF-8 encoded for the Blade view
        $data = [
            'student' => $this->record,
            'subjects' => $this->record->SubjectsEnrolled,
            'school_year' => mb_convert_encoding(
                $general_settings->getSchoolYearString() ?? '',
                'UTF-8',
                'auto'
            ),
            'semester' => mb_convert_encoding(
                $general_settings->getSemester() ?? '',
                'UTF-8',
                'auto'
            ),
            'tuition' => $this->record->studentTuition,
        ];

        $randomChars = mb_substr(
            str_shuffle(
                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
            ),
            0,
            10
        );
        $assessmentFilename = "assmt-{$this->record->id}-{$randomChars}.pdf";

        // Ensure private directory exists
        $privateDir = storage_path('app/private');
        if (! File::exists($privateDir)) {
            File::makeDirectory($privateDir, 0755, true);
        }
        $assessmentPath =
            $privateDir.DIRECTORY_SEPARATOR.$assessmentFilename;

        Log::info('PDF file setup:', [
            'assessment_filename' => $assessmentFilename,
            'assessment_path' => $assessmentPath,
        ]);

        // Increase memory limit for PDF generation
        $originalMemoryLimit = ini_get('memory_limit');
        if (mb_substr($originalMemoryLimit, 0, -1) < 1024) {
            ini_set('memory_limit', '1G');
            Log::info('Increased memory limit for PDF generation.', [
                'from' => $originalMemoryLimit,
                'to' => '1G',
            ]);
        }

        try {
            Log::info('Starting HTML rendering for PDF', [
                'view' => 'pdf.assesment-form',
                'original_memory_limit' => $originalMemoryLimit,
                'current_memory_limit' => ini_get('memory_limit'),
            ]);

            // Render the HTML view
            $html = view('pdf.assesment-form', $data)->render();
            Log::info('HTML rendering completed successfully', [
                'html_length' => mb_strlen($html),
            ]);

            // Use BrowsershotService to generate PDF with consistent configuration
            $success = BrowsershotService::generatePdf($html, $assessmentPath, [
                'format' => 'A4',
                'landscape' => true,
                'print_background' => true,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_left' => 10,
                'margin_right' => 10,
                'timeout' => 180,
                'wait_until_network_idle' => true,
            ]);

            if ($success === false || ($success === '' || $success === '0')) {
                throw new Exception(
                    'BrowsershotService failed to generate PDF'
                );
            }

            Log::info('PDF saved locally via BrowsershotService', [
                'path' => $assessmentPath,
                'size' => filesize($assessmentPath),
            ]);

            // Verify file existence and size
            if (
                ! file_exists($assessmentPath) ||
                filesize($assessmentPath) === 0
            ) {
                Log::error('Failed to save PDF or PDF is empty.', [
                    'path' => $assessmentPath,
                ]);
                throw new Exception(
                    'Failed to save PDF or generated PDF is empty.'
                );
            }

            // Try to upload to public storage
            try {
                Storage::disk('public')->putFileAs(
                    '',
                    $assessmentPath,
                    $assessmentFilename
                );
                Log::info('PDF uploaded to public storage');
            } catch (Exception $storageE) {
                Log::error(
                    'Failed to upload PDF to public storage: '.
                        $storageE->getMessage()
                );
            }

            // Save resource record
            $this->record->resources()->updateOrCreate(
                [
                    'resourceable_id' => $this->record->id,
                    'resourceable_type' => $this->record::class,
                    'type' => 'assessment',
                ],
                [
                    'file_path' => $assessmentPath,
                    'file_name' => $assessmentFilename,
                    'mime_type' => 'application/pdf',
                    'disk' => 'local',
                    'file_size' => filesize($assessmentPath),
                    'metadata' => [
                        'school_year' => mb_convert_encoding(
                            $general_settings->getSchoolYear() ?? '',
                            'UTF-8',
                            'auto'
                        ),
                        'semester' => mb_convert_encoding(
                            $general_settings->semester ?? '',
                            'UTF-8',
                            'auto'
                        ),
                        'generation_method' => 'browsershot_service',
                    ],
                ]
            );
            Log::info('Resource record created/updated in database.', [
                'enrollment_id' => $this->record->id,
            ]);

            return [
                'assessment' => [
                    'content' => file_get_contents($assessmentPath),
                    'path' => $assessmentPath,
                ],
            ];
        } catch (Exception $e) {
            Log::error(
                'PDF Generation Error (BrowsershotService): '.$e->getMessage()
            );
            Log::error('Stack trace: '.$e->getTraceAsString());
            throw new Exception(
                'Failed to generate PDF with BrowsershotService: '.
                    $e->getMessage(),
                0,
                $e
            );
        } finally {
            // Restore original memory limit
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }
}
