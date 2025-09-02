<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnrollStat;
use App\Jobs\SendAssessmentNotificationJob;
use App\Models\AdminTransaction;
use App\Models\Classes;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\InvoiceTransact;
use App\Notifications\MigrateToStudent; // Alias Filament Action
use App\Notifications\StudentEnrolledVerified;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection; // Use Log facade
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
// Keep if needed, ensure correct namespace
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class EnrollmentService
{
    /**
     * Checks if any selected classes for enrollment are full based on their maximum slots.
     *
     * @param  Collection  $classes  A collection of relevant Class models, keyed by subject_code.
     * @param  Collection  $enrolledSubjectsData  A collection of subject data being enrolled (from form).
     * @return Collection A collection of subject codes for classes that are full.
     */
    public function checkFullClasses(
        Collection $classes,
        Collection $enrolledSubjectsData
    ): Collection {
        return $enrolledSubjectsData
            ->filter(function ($subject) use ($classes): bool {
                // Ensure subject_code exists and the corresponding class is found
                if (! isset($subject['subject_code'])) {
                    Log::warning(
                        'Subject code missing during full class check.',
                        ['subject_data' => $subject]
                    );

                    return false; // Cannot check if subject_code is missing
                }
                $class = $classes->get($subject['subject_code']);

                // Check if class exists and has the relationship loaded or count available
                return $class &&
                    isset($class->maximum_slots) && // Ensure maximum_slots is available
                    ($class->relationLoaded('ClassStudents') // Check if relationship is loaded
                        ? $class->ClassStudents->count() >=
                            $class->maximum_slots
                        : $class->classStudents()->count() >=
                            $class->maximum_slots); // Or query the count
            })
            ->pluck('subject_code');
    }

    /**
     * Calculates tuition details and creates the StudentTuition record.
     * Typically called after a StudentEnrollment record is created.
     *
     * @param  StudentEnrollment  $enrollmentRecord  The newly created enrollment record.
     * @param  array  $formData  The form data containing subject and discount info.
     * @return StudentTuition|null The created StudentTuition record or null on failure.
     */
    public function createStudentTuition(
        StudentEnrollment $enrollmentRecord,
        array $formData
    ): ?StudentTuition {
        try {
            $subjectsEnrolled = $formData['subjectsEnrolled'] ?? [];
            $totalLecture = 0;
            $totalLaboratory = 0;

            // It's safer to load the course relationship here if needed for fees
            $subjectIds = collect($subjectsEnrolled)
                ->pluck('subject_id')
                ->filter()
                ->unique();
            $subjects = Subject::with('course')
                ->findMany($subjectIds)
                ->keyBy('id');

            foreach ($subjectsEnrolled as $subjectData) {
                if (! empty($subjectData['subject_id'])) {
                    $subjectModel = $subjects->get($subjectData['subject_id']);
                    if (! $subjectModel || ! $subjectModel->course) {
                        Log::warning(
                            'Subject or course not found for calculation',
                            ['subject_id' => $subjectData['subject_id']]
                        );

                        continue; // Skip if subject or course data is missing
                    }

                    // Use fees from form if manually entered, otherwise calculate
                    $lectureFee = $subjectData['lecture'] ?? 0;
                    $laboratoryFee = $subjectData['laboratory'] ?? 0;

                    // If fees were not manually entered (or are zero), calculate them
                    // Note: This calculation logic might differ slightly from the form's live calculation
                    // Ensure consistency or rely solely on form-provided fees if they are always present.
                    if (empty($lectureFee) && empty($laboratoryFee)) {
                        $isNSTP = str_contains(
                            mb_strtoupper($subjectModel->code),
                            'NSTP'
                        );
                        $totalUnits =
                            $subjectModel->lecture + $subjectModel->laboratory;
                        $courseLecPerUnit =
                            $subjectModel->course->lec_per_unit ?? 0;
                        $calculatedLectureFee = $subjectModel->lecture
                            ? $totalUnits * $courseLecPerUnit
                            : 0;

                        if ($isNSTP) {
                            $calculatedLectureFee *= 0.5;
                        }

                        $courseLabPerUnit =
                            $subjectModel->course->lab_per_unit ?? 0;
                        $calculatedLaboratoryFee = $subjectModel->laboratory
                            ? 1 * $courseLabPerUnit
                            : 0; // Lab fee is always 1 × lab_per_unit if any lab units exist

                        $lectureFee = $calculatedLectureFee;
                        $laboratoryFee = $calculatedLaboratoryFee;
                    }

                    // Handle modular fee override
                    if (! empty($subjectData['is_modular'])) {
                        $lectureFee = 2400; // Fixed modular fee
                        $laboratoryFee = 0; // No lab fee for modular
                    }

                    $totalLecture += (float) $lectureFee;
                    $totalLaboratory += (float) $laboratoryFee;
                }
            }

            $discount = (int) ($formData['discount'] ?? 0);
            $discountedLecture = $totalLecture * (1 - $discount / 100);
            $discountedTuition = $discountedLecture + $totalLaboratory;

            // Fetch miscellaneous fee from the enrollment's course based on curriculum year
            $miscellaneousFee = 3500; // Default fallback
            $enrollmentCourse = $enrollmentRecord->course; // Load enrollment course
            if ($enrollmentCourse) {
                $miscellaneousFee = $enrollmentCourse->getMiscellaneousFee();
            }

            // Calculate additional fees total
            $additionalFeesTotal = 0;
            if (! empty($formData['additionalFees'])) {
                foreach ($formData['additionalFees'] as $fee) {
                    if (! empty($fee['amount'])) {
                        $additionalFeesTotal += (float) $fee['amount'];
                    }
                }
            }

            // Check if overall total was manually modified
            if (! empty($formData['is_overall_manually_modified']) && ! empty($formData['overall_total'])) {
                $overallTotal = (float) $formData['overall_total'];
            } else {
                $overallTotal = $discountedTuition + $miscellaneousFee + $additionalFeesTotal;
            }

            $downPayment = (float) ($formData['downpayment'] ?? 0); // Ensure float
            $balance = $overallTotal - $downPayment;

            // Create StudentTuition record
            return StudentTuition::create([
                'enrollment_id' => $enrollmentRecord->id,
                'student_id' => $enrollmentRecord->student_id, // Use ID from the record
                'total_tuition' => $discountedTuition,
                'total_balance' => $balance,
                'total_lectures' => $discountedLecture,
                'total_laboratory' => $totalLaboratory,
                'total_miscelaneous_fees' => $miscellaneousFee,
                'discount' => $discount,
                'downpayment' => $downPayment,
                'overall_tuition' => $overallTotal,
                // Add other relevant fields if needed (semester, school_year, etc.)
                'semester' => $enrollmentRecord->semester, // Assuming semester is on enrollment record
                'school_year' => GeneralSetting::first()?->getSchoolYearString(), // Fetch current school year
                'academic_year' => $enrollmentRecord->academic_year, // Assuming academic_year is on enrollment record
            ]);
        } catch (Exception $e) {
            Log::error('Error creating student tuition: '.$e->getMessage(), [
                'enrollment_id' => $enrollmentRecord->id,
                'form_data' => $formData, // Be cautious logging sensitive data
                'exception' => $e,
            ]);

            return null; // Indicate failure
        }
    }

    // NOTE: updateTotals, recalculateTotals, updateOrCreateTuition, generateChecklistTable,
    // verifyByHeadDept, verifyByCashier, resendAssessmentNotification remain in EnrollmentServiceProvider
    // as they are either static utility methods for the form or specific verification actions.
    // If further refactoring is desired, these could also be moved to appropriate services.

    /**
     * Handles the logic for verifying an enrollment by the Department Head.
     * Creates signature record, updates status, and sends notifications.
     *
     * @param  StudentEnrollment  $enrollmentRecord  The enrollment record to verify.
     * @param  string|null  $signatureData  The signature data (if signatures are enabled).
     * @return bool True on success, false on failure.
     */
    public function verifyByHeadDept(
        StudentEnrollment $enrollmentRecord,
        ?string $signatureData
    ): bool {
        try {
            DB::beginTransaction();

            // Update enrollment status
            $enrollmentRecord->status = EnrollStat::VerifiedByDeptHead->value; // Use Enum value
            $enrollmentRecord->save();

            // Send notifications
            Notification::make()
                ->title('Verified as Head Dept')
                ->success()
                ->body(
                    'Successfully verified the student as Head Dept and notified the student.'
                )
                ->sendToDatabase(User::role('super_admin')->get()) // Send to DB
                ->send(); // Also send regular notification

            // Notify the student
            if ($enrollmentRecord->student?->email) {
                NotificationFacade::route(
                    'mail',
                    $enrollmentRecord->student->email
                )->notify(new StudentEnrolledVerified($enrollmentRecord));
            } else {
                Log::warning('Student email not found for notification.', [
                    'enrollment_id' => $enrollmentRecord->id,
                ]);
            }

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error verifying by Head Dept: '.$e->getMessage(), [
                'enrollment_id' => $enrollmentRecord->id,
                'exception' => $e,
            ]);
            Notification::make()
                ->danger()
                ->title('Verification Failed')
                ->body(
                    'An error occurred while verifying the enrollment: '.
                        $e->getMessage()
                )
                ->sendToDatabase(User::role('super_admin')->get()) // Send to DB
                ->send(); // Also send regular notification

            return false;
        }
    }

    /**
     * Handles the logic for verifying an enrollment by the Cashier.
     * Creates transactions, updates tuition, enrolls in classes, sends notifications,
     * updates status, and soft deletes the enrollment record.
     *
     * @param  StudentEnrollment  $enrollmentRecord  The enrollment record to verify.
     * @param  array  $actionData  Data from the action form (settlements, invoicenumber, signature).
     * @return bool True on success, false on failure.
     */
    public function verifyByCashier(
        StudentEnrollment $enrollmentRecord,
        array $actionData
    ): bool {
        DB::beginTransaction();
        try {
            $generalSettings = GeneralSetting::first();
            $signatureData = $actionData['signature'] ?? null;
            $settlements = $actionData['settlements'] ?? [];
            $invoiceNumber = $actionData['invoicenumber'] ?? null;

            if (empty($invoiceNumber)) {
                throw new Exception('Invoice number is required.');
            }
            if (empty($settlements['tuition_fee'])) {
                Log::warning(
                    'Tuition fee settlement is zero or missing during cashier verification.',
                    [
                        'enrollment_id' => $enrollmentRecord->id,
                        'settlements' => $settlements,
                    ]
                );
                // Depending on requirements, you might throw an exception or allow proceeding.
                // For now, let's allow proceeding but log it.
            }

            // Handle separate transaction fees
            $separateTransactionFees = $enrollmentRecord->additionalFees()
                ->where('is_separate_transaction', true)
                ->get();

            $totalSeparateFeesAmount = 0;
            $separateTransactions = [];

            foreach ($separateTransactionFees as $fee) {
                $transactionNumberKey = "separate_fee_{$fee->id}_transaction";
                $transactionNumber = $actionData[$transactionNumberKey] ?? null;

                if (empty($transactionNumber)) {
                    throw new Exception("Transaction number is required for {$fee->fee_name}");
                }

                // Create separate transaction for this fee
                $separateTransaction = Transaction::create([
                    'description' => "Payment for {$fee->fee_name}",
                    'payment_method' => 'Cash',
                    'settlements' => ['others' => $fee->amount], // Put in 'others' category
                    'status' => 'Paid',
                    'invoicenumber' => $transactionNumber,
                    'signature' => $generalSettings?->enable_signatures && $signatureData
                            ? $signatureData
                            : null,
                    'user_id' => Auth::id(),
                ]);

                // Link separate transaction to student
                StudentTransaction::create([
                    'student_id' => $enrollmentRecord->student_id,
                    'transaction_id' => $separateTransaction->id,
                    'status' => $separateTransaction->status,
                ]);

                // Link separate transaction to admin
                AdminTransaction::create([
                    'admin_id' => Auth::id(),
                    'transaction_id' => $separateTransaction->id,
                    'status' => $separateTransaction->status,
                ]);

                // Update the additional fee with the transaction number
                $fee->update(['transaction_number' => $transactionNumber]);
                $totalSeparateFeesAmount += $fee->amount;
                $separateTransactions[] = $separateTransaction;
            }

            // Calculate the total downpayment (main transaction + separate fees)
            $mainTransactionAmount = array_sum($settlements);
            $totalDownpayment = $mainTransactionAmount + $totalSeparateFeesAmount;

            // Create Transaction
            $transaction = Transaction::create([
                'description' => 'Downpayment for student Tuition', // Consider making this more dynamic
                'payment_method' => 'Cash', // Assuming Cash, might need flexibility
                'settlements' => $settlements,
                'status' => 'Paid', // Assuming paid upon verification
                'invoicenumber' => $invoiceNumber,
                'signature' => $generalSettings?->enable_signatures && $signatureData
                        ? $signatureData
                        : null,
                // Add user_id if applicable
                'user_id' => Auth::id(), // Record which admin performed the action
            ]);

            // Link Transaction to Student
            StudentTransaction::create([
                'student_id' => $enrollmentRecord->student_id,
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
            ]);

            // Link Transaction to Admin
            AdminTransaction::create([
                'admin_id' => Auth::id(),
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
            ]);

            // Log separate transactions created
            if (! empty($separateTransactions)) {
                $separateTransactionNumbers = collect($separateTransactions)
                    ->pluck('transaction_number')
                    ->join(', ');

                Log::info('Separate transactions created during cashier verification', [
                    'enrollment_id' => $enrollmentRecord->id,
                    'main_transaction' => $transaction->transaction_number,
                    'separate_transactions' => $separateTransactionNumbers,
                    'total_separate_amount' => $totalSeparateFeesAmount,
                ]);
            }

            // Update Student Tuition
            if ($enrollmentRecord->studentTuition) {
                // Update the student tuition with the total downpayment (main + separate fees)
                $currentBalance = $enrollmentRecord->studentTuition->total_balance ?? 0;
                $newBalance = max(0, $currentBalance - $totalDownpayment);

                $enrollmentRecord->studentTuition->update([
                    'status' => 'Downpayment',
                    'total_balance' => $newBalance,
                    'downpayment' => $totalDownpayment, // Store the total downpayment
                    'semester' => $generalSettings?->semester,
                    'school_year' => $generalSettings?->getSchoolYearString(),
                    'academic_year' => $enrollmentRecord->academic_year,
                ]);
            } else {
                // Handle case where studentTuition doesn't exist? Log error or create it?
                Log::error(
                    'StudentTuition record not found during cashier verification.',
                    ['enrollment_id' => $enrollmentRecord->id]
                );
                // Optionally create it based on calculations if needed, similar to afterCreate logic
                // For now, we'll just log the error.
                throw new Exception('Student tuition record not found.');
            }

            // Auto-enroll in classes
            if (
                method_exists($enrollmentRecord->student, 'autoEnrollInClasses')
            ) {
                $enrollmentRecord->student->autoEnrollInClasses(
                    $enrollmentRecord->id
                );
            }

            // Check if student is in first academic year (academic_year = 1)
            // If so, update their account information
            if (
                $enrollmentRecord->academic_year === 1 &&
                $enrollmentRecord->student
            ) {
                $student = $enrollmentRecord->student;

                // Try to find an account with the student's email
                if ($student->email) {
                    $account = \App\Models\Account::where(
                        'email',
                        $student->email
                    )->first();

                    if ($account) {
                        // Update the account with student role and association
                        $account->update([
                            'role' => 'student',
                            'person_id' => $student->id,
                            'person_type' => Student::class,
                        ]);

                        Log::info('Updated account for first-year student', [
                            'student_id' => $student->id,
                            'account_id' => $account->id,
                            'email' => $student->email,
                        ]);
                    } else {
                        Log::warning(
                            'No account found for first-year student',
                            [
                                'student_id' => $student->id,
                                'email' => $student->email,
                            ]
                        );
                    }
                } else {
                    Log::warning(
                        'First-year student has no email to link to account',
                        [
                            'student_id' => $student->id,
                        ]
                    );
                }
            }

            // Send Notifications
            // TODO: Uncomment InvoiceTransact when library issue is resolved
            // if ($enrollmentRecord->student?->email) {
            //     NotificationFacade::route('mail', $enrollmentRecord->student->email)
            //         ->notify(new InvoiceTransact($transaction, $enrollmentRecord->student));
            // }
            if ($enrollmentRecord->student?->email) {
                NotificationFacade::route(
                    'mail',
                    $enrollmentRecord->student->email
                )->notify(new MigrateToStudent($enrollmentRecord));
            } else {
                Log::warning(
                    'Student email not found for MigrateToStudent notification.',
                    ['enrollment_id' => $enrollmentRecord->id]
                );
            }

            // Update Enrollment Status and Soft Delete
            $enrollmentRecord->status = EnrollStat::VerifiedByCashier->value;
            $enrollmentRecord->save(); // Save status before deleting
            $enrollmentRecord->delete(); // Soft delete the enrollment record

            // Notify Super Admins
            $superadmins = User::role('super_admin')->get();
            $assessmentUrl = route('assessment.download', [
                'record' => $enrollmentRecord->id,
            ]); // Generate URL before potential redirect
            // Prepare notification body with transaction details
            $notificationBody = 'Successfully Enrolled '.
                $enrollmentRecord->student?->last_name.
                '. Main Transaction: '.$transaction->transaction_number;

            if (! empty($separateTransactions)) {
                $separateTransactionNumbers = collect($separateTransactions)
                    ->pluck('transaction_number')
                    ->join(', ');
                $notificationBody .= '. Separate Transactions: '.$separateTransactionNumbers;
            }

            $notificationBody .= '. Assessment URL: '.$assessmentUrl;

            Notification::make()
                ->title(
                    'Student Enrolled: '.
                        $enrollmentRecord->student?->last_name
                )
                ->success()
                ->body($notificationBody)
                ->actions([
                    Action::make('download') // Use alias
                        ->label('View Assessment')
                        ->button()
                        ->url($assessmentUrl, shouldOpenInNewTab: true),
                ])
                ->sendToDatabase($superadmins) // Send to DB
                ->send(); // Also send regular notification

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error verifying by Cashier: '.$e->getMessage(), [
                'enrollment_id' => $enrollmentRecord->id,
                'exception' => $e,
            ]);
            Notification::make()
                ->danger()
                ->title('Enrollment Failed')
                ->body(
                    'An error occurred during cashier verification: '.
                        $e->getMessage()
                )
                ->sendToDatabase(User::role('super_admin')->get()) // Send to DB
                ->send(); // Also send regular notification

            return false;
        }
    }

    /**
     * Resend assessment notification for a student enrollment using job queue
     *
     * @return array Job tracking information
     */
    public function resendAssessmentNotification(
        StudentEnrollment $enrollmentRecord
    ): array {
        try {
            if (! $enrollmentRecord->student?->email) {
                Log::warning(
                    'Student email not found for resending assessment notification.',
                    ['enrollment_id' => $enrollmentRecord->id]
                );

                Notification::make()
                    ->warning()
                    ->title('Resend Failed')
                    ->body(
                        'Could not resend assessment notification because the student email is missing.'
                    )
                    ->sendToDatabase(User::role('super_admin')->get())
                    ->send();

                return [
                    'success' => false,
                    'message' => 'Student email not found',
                    'job_id' => null,
                ];
            }

            // Generate unique job ID for tracking
            $jobId = uniqid('assessment_resend_', true);

            // Dispatch notification job (it will handle PDF generation internally)
            $notificationJob = new SendAssessmentNotificationJob(
                $enrollmentRecord,
                $jobId
            );

            // Dispatch notification job
            dispatch($notificationJob);

            Log::info('Assessment resend job dispatched successfully', [
                'enrollment_id' => $enrollmentRecord->id,
                'job_id' => $jobId,
                'student_email' => $enrollmentRecord->student->email,
            ]);

            return [
                'success' => true,
                'message' => 'Assessment resend process started',
                'job_id' => $jobId,
                'student_name' => $enrollmentRecord->student->first_name.
                    ' '.
                    $enrollmentRecord->student->last_name,
            ];
        } catch (Exception $e) {
            Log::error(
                'Error initiating assessment notification resend: '.
                    $e->getMessage(),
                [
                    'enrollment_id' => $enrollmentRecord->id,
                    'exception' => $e,
                ]
            );

            Notification::make()
                ->danger()
                ->title('Resend Failed')
                ->body(
                    'An error occurred while starting the assessment resend process: '.
                        $e->getMessage()
                )
                ->sendToDatabase(User::role('super_admin')->get())
                ->send();

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'job_id' => null,
            ];
        }
    }

    /**
     * Undoes the Head Department verification step.
     * Reverts status to Pending and removes the signature.
     *
     * @param  StudentEnrollment  $enrollmentRecord  The enrollment record.
     * @return bool True on success, false on failure.
     */
    public function undoHeadDeptVerification(
        StudentEnrollment $enrollmentRecord
    ): bool {
        if (
            $enrollmentRecord->status !== EnrollStat::VerifiedByDeptHead->value
        ) {
            Notification::make()
                ->warning()
                ->title('Undo Failed')
                ->body(
                    'Enrollment is not in the "Verified By Dept Head" status.'
                )
                ->send();

            return false;
        }

        DB::beginTransaction();
        try {
            // Revert status
            $enrollmentRecord->status = EnrollStat::Pending->value;
            $enrollmentRecord->save();

            DB::commit();

            Notification::make()
                ->success()
                ->title('Head Dept Verification Undone')
                ->body(
                    "Enrollment status reverted to Pending for student {$enrollmentRecord->student_id}."
                )
                ->sendToDatabase(User::role('super_admin')->get())
                ->send();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error(
                'Error undoing Head Dept verification: '.$e->getMessage(),
                [
                    'enrollment_id' => $enrollmentRecord->id,
                    'exception' => $e,
                ]
            );
            Notification::make()
                ->danger()
                ->title('Undo Failed')
                ->body(
                    'An error occurred while undoing Head Dept verification: '.
                        $e->getMessage()
                )
                ->sendToDatabase(User::role('super_admin')->get())
                ->send();

            return false;
        }
    }

    /**
     * Undoes the Cashier verification step.
     * Restores the enrollment record, reverts status to VerifiedByDeptHead,
     * and removes the cashier signature.
     * IMPORTANT: Does NOT automatically reverse financial transactions.
     *
     * @param  int  $enrollmentRecordId  The ID of the enrollment record (might be soft-deleted).
     * @return bool True on success, false on failure.
     */
    public function undoCashierVerification(int $enrollmentRecordId): bool
    {
        // Find the record, including soft-deleted ones
        $enrollmentRecord = StudentEnrollment::withTrashed()->find(
            $enrollmentRecordId
        );

        if (! $enrollmentRecord) {
            Notification::make()
                ->danger()
                ->title('Undo Failed')
                ->body('Enrollment record not found.')
                ->send();

            return false;
        }

        // Check if it was actually verified by cashier (or is currently soft-deleted, implying it was)
        // This logic might need refinement depending on exact state transitions allowed.
        // We assume if it's soft-deleted, it was likely due to cashier verification.
        // Or if the status is currently VerifiedByCashier (though it shouldn't be if soft-deleted).
        if (
            ! $enrollmentRecord->trashed() &&
            $enrollmentRecord->status !== EnrollStat::VerifiedByCashier->value
        ) {
            Notification::make()
                ->warning()
                ->title('Undo Failed')
                ->body(
                    'Enrollment was not verified by Cashier or is not in the expected state for undo.'
                )
                ->send();

            return false;
        }

        DB::beginTransaction();
        try {
            // Restore the record if it was soft-deleted
            if ($enrollmentRecord->trashed()) {
                $enrollmentRecord->restore();
            }

            // Revert status to the previous step
            $enrollmentRecord->status = EnrollStat::VerifiedByDeptHead->value;
            $enrollmentRecord->save();

            // IMPORTANT: We are NOT deleting transactions or reverting tuition automatically.

            DB::commit();

            Notification::make()
                ->success()
                ->title('Cashier Verification Undone')
                ->body(
                    "Enrollment status reverted to Verified By Dept Head for student {$enrollmentRecord->student_id}. IMPORTANT: Financial transactions were NOT automatically reversed. Please review and adjust manually if needed."
                )
                ->persistent() // Make it persistent so user acknowledges the manual step
                ->sendToDatabase(User::role('super_admin')->get())
                ->send();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error(
                'Error undoing Cashier verification: '.$e->getMessage(),
                [
                    'enrollment_id' => $enrollmentRecord->id,
                    'exception' => $e,
                ]
            );
            Notification::make()
                ->danger()
                ->title('Undo Failed')
                ->body(
                    'An error occurred while undoing Cashier verification: '.
                        $e->getMessage()
                )
                ->sendToDatabase(User::role('super_admin')->get())
                ->send();

            return false;
        }
    }

    /**
     * Get subject dropdown options for enrollment.
     *
     * @param  string|int|null  $semester
     */
    public function getSubjectDropdownOptions(
        ?int $courseId = null,
        ?int $studentId = null,
        $semester = null,
        ?string $schoolYear = null
    ): array {
        // If any required parameter is missing, return empty options
        if (
            $courseId === null ||
            $studentId === null ||
            $semester === null ||
            $schoolYear === null
        ) {
            return [];
        }

        // Get all subjects for the course
        $subjects = Subject::where('course_id', $courseId)->get();

        // Get all subject IDs the student has already enrolled in
        $enrolledSubjectIds = SubjectEnrollment::where('student_id', $studentId)
            ->pluck('subject_id')
            ->toArray();

        // Get all classes for this course, semester, and school year
        $availableClasses = Classes::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->whereJsonContains('course_codes', (string) $courseId)
            ->get(['subject_code', 'subject_id']);

        // Create a map of trimmed subject codes to check for matches
        $classSubjectCodeMap = [];
        foreach ($availableClasses as $class) {
            $trimmedCode = mb_trim($class->subject_code);
            $classSubjectCodeMap[$trimmedCode] = true;
        }

        $options = [];
        foreach ($subjects as $subject) {
            // Skip if already enrolled
            if (in_array($subject->id, $enrolledSubjectIds)) {
                continue;
            }

            // Check if there's a class available for this subject
            // Use trimmed comparison to handle trailing spaces
            $trimmedSubjectCode = mb_trim($subject->code);
            $hasClass = isset($classSubjectCodeMap[$trimmedSubjectCode]);

            $label = $subject->code.' - '.$subject->title;
            if ($hasClass) {
                $label = '⭐ '.$label;
            }
            $options[$subject->id] = [
                'label' => $label,
                'disabled' => ! $hasClass,
            ];
        }

        return $options;
    }
}
