<?php


namespace App\Filament\Resources\ClassResource\RelationManagers;

use Exception;
use Filament\Tables;
use App\Models\Classes;
use App\Models\Student;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\ClassEnrollment;
use Filament\Actions\BulkAction;
// use Filament\Forms\Components\Grid;
use Filament\Actions\EditAction;
use App\Models\StudentEnrollment;
use App\Models\SubjectEnrollment;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ExportAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Jobs\MoveStudentToSectionJob;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteBulkAction;
// use Filament\Tables\Actions\ExportAction;
use App\Jobs\GenerateStudentListPdfJob;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use App\Services\GeneralSettingsService;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use App\Jobs\BulkMoveStudentsToSectionJob;
use Illuminate\Database\Eloquent\Collection;
use App\Services\StudentSectionTransferService;
use App\Filament\Exports\ClassEnrollmentExporter;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Resources\RelationManagers\RelationManager;

final class ClassEnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = "class_enrollments";

    protected static ?string $recordTitleAttribute = "student_id";

    protected static ?string $title = "Enrolled Students";

    public function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Select::make("student_id")
                ->label("Student")
                ->options(fn() => Student::all()->pluck("full_name", "id"))
                ->searchable()
                ->required()
                ->preload()
                ->columnSpan("full"),

            Grid::make(3)->schema([
                TextInput::make("prelim_grade")
                    ->label("Prelim")
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->live()
                    ->afterStateUpdated(
                        fn(callable $set) => $set("total_average", null)
                    ), // Recalculate average

                TextInput::make("midterm_grade")
                    ->label("Midterm")
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->live()
                    ->afterStateUpdated(
                        fn(callable $set) => $set("total_average", null)
                    ), // Recalculate average

                TextInput::make("finals_grade")
                    ->label("Finals")
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->live()
                    ->afterStateUpdated(
                        fn(callable $set) => $set("total_average", null)
                    ), // Recalculate average
            ]),

            Grid::make(2)->schema([
                TextInput::make("total_average")
                    ->label("Final Grade")
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->disabled()
                    ->placeholder(function (callable $get): string {
                        // Calculate average only if all grades are present
                        $prelim = $get("prelim_grade");
                        $midterm = $get("midterm_grade");
                        $finals = $get("finals_grade");

                        if (
                            $prelim !== null &&
                            $midterm !== null &&
                            $finals !== null
                        ) {
                            $average = ($prelim + $midterm + $finals) / 3;

                            return number_format($average, 2);
                        }

                        return "N/A";
                    }),

                Select::make("status")
                    ->options([
                        true => "Passed",
                        false => "Failed",
                    ])
                    ->default(true),
            ]),

            Textarea::make("remarks")->rows(2)->columnSpan("full"),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute("student_id")
            ->columns([
                TextColumn::make("student_id")
                    ->label("Student")
                    ->formatStateUsing(
                        fn($record) => $record->student?->full_name ?? "N/A"
                    )
                    ->searchable(
                        query: fn($query, $search) => $query->whereHas(
                            "student",
                            function ($q) use ($search): void {
                                $q->where(
                                    "first_name",
                                    "like",
                                    "%{$search}%"
                                )->orWhere("last_name", "like", "%{$search}%");
                            }
                        )
                    )
                    ->sortable(),
                TextColumn::make("student.course.code")
                    ->label("Course")
                    ->searchable()
                    ->sortable(),
                TextColumn::make("created_at")
                    ->label("date Added")
                    ->sortable(),
                TextColumn::make("status")
                    ->label("Status")
                    ->badge()
                    ->formatStateUsing(
                        fn($record): string => $record->status
                            ? "Enrolled"
                            : "Not Active"
                    )
                    ->color(
                        fn($record): string => $record->status
                            ? "success"
                            : "gray"
                    ),

                TextColumn::make("prelim_grade")->label("Prelim")->sortable(),

                TextColumn::make("midterm_grade")->label("Midterm")->sortable(),

                TextColumn::make("finals_grade")->label("Finals")->sortable(),

                TextColumn::make("total_average")
                    ->label("Final Grade")
                    ->sortable(),

                IconColumn::make("status")
                    ->boolean()
                    ->label("Status")
                    ->trueIcon("heroicon-o-check-circle")
                    ->falseIcon("heroicon-o-x-circle")
                    ->trueColor("success")
                    ->falseColor("danger"),

                TextColumn::make("remarks")
                    ->limit(30)
                    ->tooltip(
                        fn(TextColumn $column): mixed => $column->getState()
                    ),
                
            ])
            ->filters([
                Tables\Filters\SelectFilter::make("status")->options([
                    "1" => "Passed",
                    "0" => "Failed",
                ]),
            ])
            ->headerActions([
               CreateAction::make()
                    ->label("Enroll Student")
                    ->modalHeading("Enroll New Student")
                    ->modalWidth("lg"),
                Action::make("view_pending")
                    ->label("View Pending Students")
                    ->icon("heroicon-o-clock")
                    ->color("warning")
                    ->modalHeading("Pending Students for this Class")
                    ->modalContent(function () {
                        $pendingInfo = $this->getPendingStudentsInfo();

                        if ($pendingInfo["count"] === 0) {
                            return view(
                                "filament.components.no-pending-students"
                            );
                        }

                        return view(
                            "filament.components.pending-students-list",
                            [
                                "students" => $pendingInfo["students"],
                                "count" => $pendingInfo["count"],
                            ]
                        );
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel("Close"),
                Action::make("re_enroll_failed")
                    ->label("Re-enroll Failed Students")
                    ->icon("heroicon-o-arrow-path")
                    ->color("info")
                    ->requiresConfirmation()
                    ->modalHeading(
                        "Re-enroll Students Who Failed Auto-Enrollment"
                    )
                    ->modalDescription(
                        "This will attempt to re-enroll students who were verified by cashier but failed to be enrolled in this class due to technical issues."
                    )
                    ->action(function (): void {
                        $result = $this->reEnrollFailedStudents();

                        if ($result["success_count"] > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title("Re-enrollment Successful")
                                ->body(
                                    "Successfully re-enrolled {$result["success_count"]} student(s) in this class."
                                )
                                ->success()
                                ->send();
                        }

                        if ($result["error_count"] > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title("Re-enrollment Issues")
                                ->body(
                                    "Failed to re-enroll {$result["error_count"]} student(s). Check logs for details."
                                )
                                ->warning()
                                ->send();
                        }

                        if (
                            $result["success_count"] === 0 &&
                            $result["error_count"] === 0
                        ) {
                            \Filament\Notifications\Notification::make()
                                ->title("No Students to Re-enroll")
                                ->body(
                                    "No students found who need re-enrollment in this class."
                                )
                                ->info()
                                ->send();
                        }
                    }),
                Action::make("recreate_assessment_pdf")
                    ->label("Recreate Assessment PDFs")
                    ->icon("heroicon-o-document-duplicate")
                    ->color("success")
                    ->requiresConfirmation()
                    ->modalHeading(
                        "Recreate Assessment PDFs for Enrolled Students"
                    )
                    ->modalDescription(
                        "This will regenerate assessment PDFs for all students enrolled in this class. This is useful when there are issues with class schedules not showing up correctly in the PDFs."
                    )
                    ->action(function (): void {
                        $result = $this->recreateAssessmentPdfs();

                        if ($result["success_count"] > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title("PDFs Recreated Successfully")
                                ->body(
                                    "Successfully recreated assessment PDFs for {$result["success_count"]} student(s)."
                                )
                                ->success()
                                ->send();
                        }

                        if ($result["error_count"] > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title("PDF Recreation Issues")
                                ->body(
                                    "Failed to recreate PDFs for {$result["error_count"]} student(s). Check logs for details."
                                )
                                ->warning()
                                ->send();
                        }

                        if (
                            $result["success_count"] === 0 &&
                            $result["error_count"] === 0
                        ) {
                            \Filament\Notifications\Notification::make()
                                ->title("No Students Found")
                                ->body(
                                    "No enrolled students found for this class."
                                )
                                ->info()
                                ->send();
                        }
                    }),
                ActionGroup::make([
                    ExportAction::make('export_students')
                        ->label('Export Students (Excel)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->exporter(ClassEnrollmentExporter::class)
                        ->fileName(function () {
                            $class = $this->getOwnerRecord();
                            return sprintf(
                                'enrolled_students_%s_%s_%s_%s',
                                str_replace(' ', '_', $class->subject_code ?? 'Unknown'),
                                str_replace(' ', '_', $class->section ?? 'Unknown'),
                                str_replace(' ', '_', $class->semester ?? 'Unknown'),
                                str_replace('-', '_', $class->school_year ?? 'Unknown')
                            );
                        })
                        ->modifyQueryUsing(function (Builder $query) {
                            return $query->where('class_id', $this->getOwnerRecord()->id)
                                ->with(['student.course', 'student.studentContactsInfo', 'class.Faculty']);
                        })
                        ->tooltip('Export enrolled students to Excel file with comprehensive data'),
                    Action::make('export_student_list_pdf')
                        ->label('Export Student List (PDF)')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->tooltip('Generate PDF list of students with auto-scaling to fit one page')
                        ->requiresConfirmation()
                        ->modalHeading('Generate Student List PDF')
                        ->modalDescription('This will generate a PDF containing a simple list of students with their ID, name, course code, and academic year. The PDF will be automatically scaled to fit on one page.')
                        ->modalSubmitActionLabel('Generate PDF')
                        ->action(function (): void {
                            $class = $this->getOwnerRecord();
                            $userId = Auth::id();

                            // Dispatch the job to generate PDF
                            GenerateStudentListPdfJob::dispatch($class, $userId);

                            // Send immediate notification that job was queued
                            \Filament\Notifications\Notification::make()
                                ->title('PDF Generation Queued')
                                ->body('Your student list PDF is being generated in the background. You will receive a notification when it\'s ready for download.')
                                ->info()
                                ->icon('heroicon-o-clock')
                                ->duration(5000)
                                ->send();
                        }),
                ])
                    ->label('Export Options')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->button(),
            ])
            ->actions([
                Action::make("Move")
                    ->requiresConfirmation()
                    ->modalHeading("Move This Student to another class")
                    ->modalDescription(
                        'Are you sure you\'d like to Move this student to another Class? This will also update their Subject Enrollment record. The transfer will be processed in the background.'
                    )
                    ->icon("heroicon-o-arrow-right-on-rectangle")
                    ->label("Move to a Class")
                    ->form([
                        Select::make("moveClass")
                            ->label("Classes")
                            ->hint(
                                "Select a Section you want this student to move to "
                            )
                            ->options(function () {
                                $transferService = app(StudentSectionTransferService::class);
                                $currentClass = $this->getOwnerRecord();

                                return $transferService->getAvailableTargetClasses($currentClass->id)
                                    ->mapWithKeys(function ($class) {
                                        $slotsInfo = $class->maximum_slots
                                            ? " (Available: {$class->available_slots}/{$class->maximum_slots})"
                                            : " (Unlimited)";
                                        $status = $class->is_full ? " [FULL]" : "";

                                        return [$class->id => "Section {$class->section}{$slotsInfo}{$status}"];
                                    })
                                    ->toArray();
                            })
                            ->required()
                            ->searchable(),

                        Toggle::make("notifyStudent")
                            ->label("Send Email Notification to Student")
                            ->helperText("Enable this to send an email notification to the student about the section transfer. Disable if the student already knows about the transfer.")
                            ->default(true)
                            ->inline(false),
                    ])
                    ->action(function (array $data, $record): void {
                        // Dispatch background job for student transfer
                        MoveStudentToSectionJob::dispatch(
                            $record->id,
                            $data["moveClass"],
                            Auth::id(),
                            $data["notifyStudent"] ?? true
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Student Transfer Queued')
                            ->body("Transfer request for {$record->student?->full_name} has been queued for background processing. You will receive a notification when the transfer is complete.")
                            ->info()
                            ->icon('heroicon-o-clock')
                            ->duration(5000)
                            ->send();
                    }),
                EditAction::make()
                    ->modalHeading("Edit Student Enrollment")
                    ->modalWidth("lg"),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make("Move")
                        ->requiresConfirmation()
                        ->modalHeading("Move These Students to another class")
                        ->modalDescription(
                            'Are you sure you\'d like to move these students to another class? This will also update their Subject Enrollment records. The transfers will be processed in the background.'
                        )
                        ->icon("heroicon-o-arrow-right-on-rectangle")
                        ->label("Move to a class")
                        ->form([
                            Select::make("moveClass1")
                                ->label("Classes")
                                ->options(function () {
                                    $transferService = app(StudentSectionTransferService::class);
                                    $currentClass = $this->getOwnerRecord();

                                    return $transferService->getAvailableTargetClasses($currentClass->id)
                                        ->mapWithKeys(function ($class) {
                                            $slotsInfo = $class->maximum_slots
                                                ? " (Available: {$class->available_slots}/{$class->maximum_slots})"
                                                : " (Unlimited)";
                                            $status = $class->is_full ? " [FULL]" : "";

                                            return [$class->id => "Section {$class->section}{$slotsInfo}{$status}"];
                                        })
                                        ->toArray();
                                })
                                ->required()
                                ->searchable(),

                            Toggle::make("notifyStudents")
                                ->label("Send Email Notifications to Students")
                                ->helperText("Enable this to send email notifications to all students about the section transfer. Disable if the students already know about the transfer.")
                                ->default(true)
                                ->inline(false),
                        ])
                        ->action(function (
                            array $data,
                            Collection $records
                        ): void {
                            // Get class enrollment IDs for the job
                            $classEnrollmentIds = $records->pluck('id')->toArray();

                            // Dispatch background job for bulk transfer
                            BulkMoveStudentsToSectionJob::dispatch(
                                $classEnrollmentIds,
                                $data["moveClass1"],
                                Auth::id(),
                                $data["notifyStudents"] ?? true
                            );

                            // Get target class details for notification
                            $targetClass = Classes::find($data["moveClass1"]);
                            $targetSection = $targetClass ? $targetClass->section : 'Unknown';
                            $subjectCode = $targetClass ? $targetClass->subject_code : 'Unknown';

                            \Filament\Notifications\Notification::make()
                                ->title('Bulk Student Transfer Queued')
                                ->body("
                                    **Operation:** Bulk Student Move
                                    **Subject:** {$subjectCode}
                                    **Target Section:** {$targetSection}
                                    **Students:** " . count($classEnrollmentIds) . "

                                    The bulk transfer request has been queued for background processing. You will receive notifications as the transfers are completed.
                                ")
                                ->info()
                                ->icon('heroicon-o-clock')
                                ->duration(8000)
                                ->send();
                        }),
                ]),
            ]);
    }

    /**
     * Get pending students for this class
     */
    public function getPendingStudentsInfo(): array
    {
        $settingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();
        $currentSemester = $settingsService->getCurrentSemester();
        $classId = $this->getOwnerRecord()->id;

        // Get pending students from SubjectEnrollment who are not yet enrolled in this class
        $pendingStudents = SubjectEnrollment::with([
            "student",
            "studentEnrollment",
        ])
            ->where("class_id", $classId)
            ->whereHas("studentEnrollment", function ($query) use (
                $currentSchoolYear,
                $currentSemester
            ): void {
                $query
                    ->where("status", "Pending")
                    ->where("school_year", $currentSchoolYear)
                    ->where("semester", $currentSemester);
            })
            ->whereDoesntHave("student.classEnrollments", function (
                $query
            ) use ($classId): void {
                $query->where("class_id", $classId);
            })
            ->get();

        return [
            "count" => $pendingStudents->count(),
            "students" => $pendingStudents
                ->map(
                    fn($subjectEnrollment): array => [
                        "id" => $subjectEnrollment->student->id,
                        "name" => $subjectEnrollment->student->full_name,
                        "enrollment_id" => $subjectEnrollment->enrollment_id,
                        "subject_enrollment_id" => $subjectEnrollment->id,
                    ]
                )
                ->toArray(),
        ];
    }

    /**
     * Re-enroll students who were verified by cashier but failed to be enrolled in this class
     */
    public function reEnrollFailedStudents(): array
    {
        $settingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();
        $currentSemester = $settingsService->getCurrentSemester();
        $classId = $this->getOwnerRecord()->id;
        $class = $this->getOwnerRecord();

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        // Find students who should be enrolled in this class but aren't
        // Use a simpler approach to avoid data type issues
        $subjectEnrollments = SubjectEnrollment::where("class_id", $classId)
            ->whereHas("studentEnrollment", function ($query) use (
                $currentSchoolYear,
                $currentSemester
            ): void {
                $query
                    ->where("status", "Verified By Cashier")
                    ->where("school_year", $currentSchoolYear)
                    ->where("semester", $currentSemester);
            })
            ->with(["student", "studentEnrollment"])
            ->get();

        // Filter out students who are already enrolled in this class
        $enrolledStudentIds = ClassEnrollment::where("class_id", $classId)
            ->pluck("student_id")
            ->map(function ($id): string {
                return (string) $id; // Convert to string for comparison
            })
            ->toArray();

        $failedStudents = $subjectEnrollments->filter(
            fn($subjectEnrollment): bool => !in_array(
                (string) $subjectEnrollment->student_id,
                $enrolledStudentIds
            )
        );

        foreach ($failedStudents as $subjectEnrollment) {
            try {
                // Create class enrollment directly
                ClassEnrollment::create([
                    "class_id" => $classId,
                    "student_id" => $subjectEnrollment->student_id,
                    "status" => true, // Active enrollment
                ]);

                $successCount++;

                Log::info(
                    "Successfully re-enrolled student {$subjectEnrollment->student_id} in class {$classId}",
                    [
                        "student_name" =>
                            $subjectEnrollment->student->full_name,
                        "class_subject" => $class->subject_code,
                        "class_section" => $class->section,
                    ]
                );
            } catch (Exception $e) {
                $errorCount++;
                $errorMessage =
                    "Failed to re-enroll student {$subjectEnrollment->student_id}: " .
                    $e->getMessage();
                $errors[] = $errorMessage;

                Log::error($errorMessage, [
                    "student_name" =>
                        $subjectEnrollment->student->full_name ?? "Unknown",
                    "class_id" => $classId,
                    "exception" => $e,
                ]);
            }
        }

        return [
            "success_count" => $successCount,
            "error_count" => $errorCount,
            "errors" => $errors,
            "total_found" => $failedStudents->count(),
        ];
    }

    /**
     * Recreate assessment PDFs for all students enrolled in this class
     */
    public function recreateAssessmentPdfs(): array
    {
        $settingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();
        $currentSemester = $settingsService->getCurrentSemester();
        $classId = $this->getOwnerRecord()->id;
        $class = $this->getOwnerRecord();

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        // Find all students enrolled in this class
        $enrolledStudents = ClassEnrollment::where("class_id", $classId)
            ->with(["student"])
            ->get();

        foreach ($enrolledStudents as $classEnrollment) {
            try {
                $student = $classEnrollment->student;

                // Find the student's enrollment record for current period
                $studentEnrollment = StudentEnrollment::withTrashed()
                    ->where("student_id", $student->id)
                    ->where("school_year", $currentSchoolYear)
                    ->where("semester", $currentSemester)
                    ->where("status", "Verified By Cashier")
                    ->first();

                if (!$studentEnrollment) {
                    $errors[] = "No verified enrollment found for student {$student->full_name} (ID: {$student->id})";
                    $errorCount++;

                    continue;
                }

                // Dispatch the PDF generation job
                \App\Jobs\GenerateAssessmentPdfJob::dispatch(
                    $studentEnrollment
                );

                $successCount++;

                Log::info(
                    "Successfully queued assessment PDF recreation for student {$student->id}",
                    [
                        "student_name" => $student->full_name,
                        "enrollment_id" => $studentEnrollment->id,
                        "class_subject" => $class->subject_code,
                        "class_section" => $class->section,
                    ]
                );
            } catch (Exception $e) {
                $errorCount++;
                $errorMessage =
                    "Failed to recreate PDF for student {$classEnrollment->student_id}: " .
                    $e->getMessage();
                $errors[] = $errorMessage;

                Log::error($errorMessage, [
                    "student_name" =>
                        $classEnrollment->student->full_name ?? "Unknown",
                    "class_id" => $classId,
                    "exception" => $e,
                ]);
            }
        }

        return [
            "success_count" => $successCount,
            "error_count" => $errorCount,
            "errors" => $errors,
            "total_found" => $enrolledStudents->count(),
        ];
    }




}
