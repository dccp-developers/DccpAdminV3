<?php

namespace App\Filament\Resources\Students\Pages;

use Exception;
use Filament\Actions\Action;
use App\Models\GeneralSetting;
use Filament\Actions\EditAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\ForceDeleteAction;
use App\Services\StudentIdUpdateService;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Resources\Students\StudentResource;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make([
                EditAction::make(),
                DeleteAction::make(),
                // evisionsAction::make(),
            ])
                ->label('Student Management')
                ->icon('heroicon-o-user')
                ->color('primary')
                ->button(),
            ActionGroup::make([
                Action::make('linkStudentAccount')
                    ->label('Link/Update Student Account')
                    ->icon('heroicon-o-user-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Link Student Account')
                    ->modalDescription('This will find and update the account associated with this student\'s email, setting the role to "student" and linking it to this student record.')
                    ->action(function ($record): void {
                        if (! $record->email) {
                            Notification::make()
                                ->warning()
                                ->title('No Email Found')
                                ->body('This student does not have an email address to link to an account.')
                                ->send();

                            return;
                        }

                        $account = \App\Models\Account::where('email', $record->email)->first();

                        if (! $account) {
                            Notification::make()
                                ->warning()
                                ->title('No Account Found')
                                ->body('No account was found with the email: '.$record->email)
                                ->send();

                            return;
                        }

                        try {
                            $account->update([
                                'role' => 'student',
                                'person_id' => $record->id,
                                'person_type' => \App\Models\Student::class,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Account Linked')
                                ->body('Successfully linked account to student. Email: '.$record->email)
                                ->send();
                        } catch (Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error Linking Account')
                                ->body('An error occurred: '.$e->getMessage())
                                ->send();
                        }
                    }),

                // Update Student ID Action
                Action::make('updateStudentId')
                    ->label('Update Student ID')
                    ->icon('heroicon-o-identification')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Update Student ID')
                    ->modalDescription('This will update the student ID and all related records in the database. This action cannot be undone.')
                    ->form([
                        Section::make('Current Information')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('current_id')
                                    ->label('Current Student ID')
                                    ->content(fn ($record) => $record->id),

                                \Filament\Forms\Components\Placeholder::make('student_name')
                                    ->label('Student Name')
                                    ->content(fn ($record) => $record->full_name),
                            ])
                            ->columns(2),

                        Section::make('Impact Summary')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('affected_records')
                                    ->label('Records that will be updated')
                                    ->content(function ($record) {
                                        $service = app(StudentIdUpdateService::class);
                                        $summary = $service->getAffectedRecordsSummary($record->id);

                                        $content = '';
                                        foreach ($summary as $table => $count) {
                                            if ($count > 0) {
                                                $tableName = str_replace('_', ' ', ucfirst($table));
                                                $content .= "• {$tableName}: {$count} record(s)\n";
                                            }
                                        }

                                        return $content ?: 'No related records found.';
                                    })
                                    ->columnSpanFull(),
                            ]),

                        Section::make('New ID')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('new_student_id')
                                    ->label('New Student ID')
                                    ->numeric()
                                    ->required()
                                    ->rules([
                                        'integer',
                                        'min:1',
                                        'different:current_id',
                                    ])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get, $component) {
                                        if (!$state) return;

                                        // Convert to integer and validate
                                        if (!is_numeric($state)) {
                                            $component->state(null);
                                            \Filament\Notifications\Notification::make()
                                                ->title('Invalid Input')
                                                ->body('Student ID must be a number.')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        $newId = (int) $state;
                                        $currentRecord = $this->getRecord();
                                        $service = app(StudentIdUpdateService::class);

                                        // Check if same as current ID
                                        if ($newId == $currentRecord->id) {
                                            $component->state(null);
                                            \Filament\Notifications\Notification::make()
                                                ->title('Invalid ID')
                                                ->body('New ID cannot be the same as current ID.')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        // Check if ID already exists
                                        if (!$service->isIdAvailable($newId)) {
                                            $component->state(null);
                                            \Filament\Notifications\Notification::make()
                                                ->title('ID Already Exists')
                                                ->body("Student ID {$newId} already exists.")
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        // Check ID pattern
                                        if ($newId < 100000 || $newId > 9999999) {
                                            $component->state(null);
                                            \Filament\Notifications\Notification::make()
                                                ->title('Invalid ID Format')
                                                ->body('Student ID should be 6-7 digits.')
                                                ->warning()
                                                ->send();
                                            return;
                                        }
                                    })
                                    ->helperText(function () {
                                        $service = app(StudentIdUpdateService::class);
                                        $suggested = $service->generateSuggestedId();
                                        return "Suggested ID: {$suggested}";
                                    }),
                            ]),

                        Section::make('⚠️ Confirmation Required')
                            ->schema([
                                \Filament\Forms\Components\Checkbox::make('confirm_operation')
                                    ->label('I confirm this student ID change')
                                    ->helperText('This will update the student ID and all related records (enrollments, tuition, transactions, etc.)')
                                    ->required()
                                    ->accepted(),
                            ])
                            ->description('This operation will permanently change the student ID and update all related records in the database.')
                            ->icon('heroicon-o-exclamation-triangle'),
                    ])
                    ->action(function (array $data, $record): void {
                        $service = app(StudentIdUpdateService::class);
                        $oldId = $record->id;
                        $newId = (int) $data['new_student_id'];

                        // Get affected records summary before update for detailed notification
                        $affectedRecords = $service->getAffectedRecordsSummary($oldId);
                        $totalRecords = array_sum($affectedRecords);

                        // Send initial notification about the update process
                        Notification::make()
                            ->title('Updating Student ID...')
                            ->body("Starting update from ID {$oldId} to {$newId}. This will affect {$totalRecords} related records.")
                            ->info()
                            ->duration(2000)
                            ->send();

                        // Perform the update with bypass safety checks since user confirmed
                        $result = $service->updateStudentId($record, $newId, true);

                        if ($result['success']) {
                            // Create detailed success message
                            $detailsMessage = "Student ID successfully changed from {$oldId} to {$newId}.\n\nUpdated records:\n";

                            foreach ($affectedRecords as $table => $count) {
                                if ($count > 0) {
                                    $tableName = ucwords(str_replace('_', ' ', $table));
                                    $detailsMessage .= "• {$tableName}: {$count} record(s)\n";
                                }
                            }

                            $detailsMessage .= "\nTotal: {$totalRecords} records updated successfully.";

                            Notification::make()
                                ->title('✅ Student ID Updated Successfully!')
                                ->body($detailsMessage)
                                ->success()
                                ->duration(5000)
                                ->send();

                            // Send database notification
                            \Filament\Notifications\Notification::make()
                                ->title('Student ID Updated')
                                ->body("Student ID changed from {$oldId} to {$newId}. {$totalRecords} related records updated.")
                                ->success()
                                ->sendToDatabase(Auth::user());

                            // Send a follow-up notification about redirection
                            Notification::make()
                                ->title('Redirecting...')
                                ->body('Taking you to the updated student record.')
                                ->info()
                                ->duration(2000)
                                ->send();

                            // Use JavaScript redirect to ensure proper page reload
                            $this->js('setTimeout(() => { window.location.href = "' .
                                route('filament.admin.resources.students.view', ['record' => $result['new_id']]) .
                                '"; }, 2000);');
                        } else {
                            Notification::make()
                                ->title('❌ Failed to Update Student ID')
                                ->body("Error: {$result['message']}\n\nNo changes were made to the database.")
                                ->danger()
                                ->duration(8000)
                                ->send();
                        }
                    }),

                // Undo Student ID Change Action
                Action::make('undoStudentIdChange')
                    ->label('Undo ID Change')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(function ($record) {
                        $service = app(StudentIdUpdateService::class);
                        $changes = $service->getStudentChangeHistory($record->id);
                        return $changes->where('is_undone', false)->isNotEmpty();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Undo Student ID Change')
                    ->modalDescription('This will revert the student ID back to its previous value and update all related records.')
                    ->form([
                        Section::make('Available Changes to Undo')
                            ->schema([
                                \Filament\Forms\Components\Select::make('change_log_id')
                                    ->label('Select Change to Undo')
                                    ->options(function ($record) {
                                        $service = app(StudentIdUpdateService::class);
                                        $changes = $service->getStudentChangeHistory($record->id)
                                            ->where('is_undone', false);

                                        return $changes->mapWithKeys(function ($change) {
                                            $date = $change->created_at->format('M j, Y g:i A');
                                            $label = "Changed from {$change->old_student_id} to {$change->new_student_id} on {$date} by {$change->changed_by}";
                                            return [$change->id => $label];
                                        })->toArray();
                                    })
                                    ->required()
                                    ->helperText('Select which ID change you want to undo'),
                            ]),

                        Section::make('⚠️ Confirmation')
                            ->schema([
                                \Filament\Forms\Components\Checkbox::make('confirm_undo')
                                    ->label('I confirm this undo operation')
                                    ->helperText('This will revert the student ID and update all related records')
                                    ->required()
                                    ->accepted(),
                            ]),
                    ])
                    ->action(function (array $data, $record): void {
                        $service = app(StudentIdUpdateService::class);
                        $changeLogId = (int) $data['change_log_id'];

                        // Send initial notification
                        Notification::make()
                            ->title('Processing Undo...')
                            ->body('Reverting student ID change. Please wait...')
                            ->info()
                            ->duration(2000)
                            ->send();

                        $result = $service->undoStudentIdChange($changeLogId);

                        if ($result['success']) {
                            // Create detailed success message
                            $message = "Student ID successfully reverted from {$result['old_id']} back to {$result['new_id']}.";

                            Notification::make()
                                ->title('✅ ID Change Undone Successfully!')
                                ->body($message)
                                ->success()
                                ->duration(5000)
                                ->send();

                            // Send database notification
                            \Filament\Notifications\Notification::make()
                                ->title('Student ID Change Undone')
                                ->body($message)
                                ->success()
                                ->sendToDatabase(Auth::user());

                            // Redirect to the reverted student record
                            $this->js('setTimeout(() => { window.location.href = "' .
                                route('filament.admin.resources.students.view', ['record' => $result['new_id']]) .
                                '"; }, 2000);');
                        } else {
                            Notification::make()
                                ->title('❌ Failed to Undo ID Change')
                                ->body("Error: {$result['message']}")
                                ->danger()
                                ->duration(8000)
                                ->send();

                            // Send database notification for failure
                            \Filament\Notifications\Notification::make()
                                ->title('Student ID Undo Failed')
                                ->body("Failed to undo student ID change: {$result['message']}")
                                ->danger()
                                ->sendToDatabase(Auth::user());
                        }
                    }),
            ])
                ->label('Account & System')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->button(),

            // Academic Actions Group
            ActionGroup::make([
                Action::make('retryClassEnrollment')
                    ->label('Retry Class Enrollment')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Retry Class Enrollment?')
                    ->modalDescription('This will attempt to re-enroll the student in all available classes for their current subjects. Force enrollment is enabled by default to override maximum class size limits.')
                    ->form([
                        Toggle::make('force_enrollment')
                            ->label('Force Enrollment')
                            ->helperText('Override maximum class size limits when enrolling')
                            ->default(true),
                        Select::make('enrollment_id')
                            ->label('Enrollment to Use')
                            ->options(fn ($record) => $record->subjectEnrolled()
                                ->select('enrollment_id')
                                ->distinct()
                                ->get()
                                ->pluck('enrollment_id', 'enrollment_id')
                                ->map(fn ($id): string => "Enrollment #{$id}")
                                ->toArray())
                            ->helperText('Select which enrollment to use for class assignments. Leave empty to use all subjects.')
                            ->searchable()
                            ->placeholder('All Subjects'),
                    ])
                    ->action(function (array $data, $record): void {
                        // Temporarily override the force_enroll_when_full config if needed
                        $originalConfigValue = config('enrollment.force_enroll_when_full');
                        if ($data['force_enrollment']) {
                            config(['enrollment.force_enroll_when_full' => true]);
                        }

                        try {
                            // Attempt to auto-enroll using the specified enrollment ID or null for all subjects
                            $enrollmentId = $data['enrollment_id'] ?? null;
                            $record->autoEnrollInClasses($enrollmentId);

                            Notification::make()
                                ->success()
                                ->title('Enrollment Retry Complete')
                                ->body('The system has attempted to enroll the student in all classes. Check the notification for results.')
                                ->send();
                        } catch (Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Enrollment Retry Failed')
                                ->body('An error occurred: '.$e->getMessage())
                                ->send();
                        } finally {
                            // Restore original config value
                            if ($data['force_enrollment']) {
                                config(['enrollment.force_enroll_when_full' => $originalConfigValue]);
                            }
                        }
                    }),
                ]),
/* 
                Action::make('history')
                    ->label('Student History')
                    ->url(fn ($record): string => StudentHistory::getUrl(['record' => $record])),
            ])
                ->label('Academic Actions')
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->button(), */

            // Financial Actions Group
            ActionGroup::make([
                Action::make('manageTuition')
                    ->label('Manage Tuition')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->modalHeading('Manage Student Tuition')
                    ->modalDescription('Update the tuition information for this student in the current semester')
                    ->modalSubmitActionLabel('Save Tuition Information')
                    ->fillForm(function ($record): array {
                        $tuition = $record->getCurrentTuitionModel();
                        $course = $record->Course;

                        if (! $tuition) {
                            return [
                                'total_lectures' => 0,
                                'total_laboratory' => 0,
                                'total_miscelaneous_fees' => $course ? $course->getMiscellaneousFee() : 3500,
                                'downpayment' => 0,
                                'discount' => 0,
                            ];
                        }

                        return [
                            'total_lectures' => $tuition->total_lectures,
                            'total_laboratory' => $tuition->total_laboratory,
                            'total_miscelaneous_fees' => $tuition->total_miscelaneous_fees,
                            'downpayment' => $tuition->downpayment,
                            'discount' => $tuition->discount,
                        ];
                    })
                    ->form([
                        Section::make('Tuition Fees')
                            ->schema([
                                TextInput::make('total_lectures')
                                    ->label('Lecture Fees')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $this->calculateTotals($set, $get);
                                    }),
                                TextInput::make('total_laboratory')
                                    ->label('Laboratory Fees')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $this->calculateTotals($set, $get);
                                    }),
                                TextInput::make('total_miscelaneous_fees')
                                    ->label('Miscellaneous Fees')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->default(3500)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $this->calculateTotals($set, $get);
                                    }),
                            ])->columns(3),

                        Section::make('Payment Information')
                            ->schema([
                                TextInput::make('downpayment')
                                    ->label('Downpayment')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $this->calculateTotals($set, $get);
                                    }),
                                TextInput::make('discount')
                                    ->label('Discount (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $this->calculateTotals($set, $get);
                                    }),
                            ])->columns(2),

                        Section::make('Calculated Totals')
                            ->schema([
                                TextInput::make('total_tuition')
                                    ->label('Total Tuition (Lectures + Laboratory)')
                                    ->prefix('₱')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('overall_tuition')
                                    ->label('Overall Tuition (Including Misc Fees)')
                                    ->prefix('₱')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('total_balance')
                                    ->label('Total Balance')
                                    ->prefix('₱')
                                    ->disabled()
                                    ->dehydrated(false),
                            ])->columns(3),
                    ])
                    ->action(function (array $data, $record): void {
                        $tuition = $record->getOrCreateCurrentTuition();
                        $settings = GeneralSetting::first();

                        // Calculate totals
                        $totalTuition = (float) $data['total_lectures'] + (float) $data['total_laboratory'];
                        $overallTuition = $totalTuition + (float) $data['total_miscelaneous_fees'];

                        // Apply discount
                        $discountAmount = $overallTuition * ((float) $data['discount'] / 100);
                        $overallTuitionAfterDiscount = $overallTuition - $discountAmount;

                        $totalBalance = $overallTuitionAfterDiscount - (float) $data['downpayment'];

                        $tuition->update([
                            'total_lectures' => (float) $data['total_lectures'],
                            'total_laboratory' => (float) $data['total_laboratory'],
                            'total_miscelaneous_fees' => (float) $data['total_miscelaneous_fees'],
                            'total_tuition' => $totalTuition,
                            'overall_tuition' => $overallTuitionAfterDiscount,
                            'downpayment' => (float) $data['downpayment'],
                            'discount' => (float) $data['discount'],
                            'total_balance' => $totalBalance,
                            'status' => $totalBalance <= 0 ? 'paid' : 'pending',
                        ]);

                        Notification::make()
                            ->title('Tuition Updated')
                            ->body('The student tuition information has been updated for the '.$settings->getSemester().' of '.$settings->getSchoolYearString())
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Financial Management')
                ->icon('heroicon-o-currency-dollar')
                ->color('warning')
                ->button(),

            // Administrative Actions Group
            ActionGroup::make([
                Action::make('manageClearance')
                    ->label('Manage Clearance')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (): bool => (bool) GeneralSetting::first()->enable_clearance_check)
                    ->form([
                        Toggle::make('is_cleared')
                            ->label('Is Cleared')
                            ->helperText('Set whether this student has cleared their requirements for the current semester')
                            ->required(),

                        \Filament\Forms\Components\DateTimePicker::make('cleared_at')
                            ->label('Cleared At')
                            ->visible(fn (Get $get): bool => (bool) $get('is_cleared'))
                            ->default(now())
                            ->displayFormat('F j, Y g:i A')
                            ->seconds(false),

                        \Filament\Forms\Components\Textarea::make('remarks')
                            ->label('Remarks')
                            ->placeholder('Enter any notes about this clearance status')
                            ->helperText('Optional notes about the clearance status')
                            ->columnSpan(2),
                    ])
                    ->action(function (array $data, $record): void {
                        $user = Auth::user();
                        $clearedBy = $user ? $user->name : 'System';
                        $settings = GeneralSetting::first();

                        if ($data['is_cleared']) {
                            $success = $record->markClearanceAsCleared(
                                $clearedBy,
                                $data['remarks'] ?? null
                            );

                            if ($success) {
                                Notification::make()
                                    ->title('Clearance Approved')
                                    ->body('The student has been cleared for the '.$settings->getSemester().' of '.$settings->getSchoolYearString())
                                    ->success()
                                    ->send();
                            }
                        } else {
                            $success = $record->markClearanceAsNotCleared($data['remarks'] ?? null);

                            if ($success) {
                                Notification::make()
                                    ->title('Clearance Status Updated')
                                    ->body('The student is marked as not cleared for the '.$settings->getSemester().' of '.$settings->getSchoolYearString())
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),

                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
                ->label('Administrative')
                ->icon('heroicon-o-shield-check')
                ->color('danger')
                ->button(),
        ];
    }
}
