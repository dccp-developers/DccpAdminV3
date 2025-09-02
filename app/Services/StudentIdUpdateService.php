<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ClassEnrollment;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentIdChangeLog;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\SubjectEnrollment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class StudentIdUpdateService
{
    /**
     * Update a student's ID and all related records
     *
     * @param  Student  $student  The student to update
     * @param  int  $newId  The new ID to assign
     * @param  bool  $bypassSafetyChecks  Whether to bypass safety checks (when user has confirmed)
     * @return array Result array with success status and message
     */
    public function updateStudentId(Student $student, int $newId, bool $bypassSafetyChecks = false): array
    {
        // Comprehensive validation
        $validation = $this->validateNewId($student, $newId);
        if ($validation !== true) {
            return $validation;
        }

        // Additional safety checks (only if not bypassed)
        if (! $bypassSafetyChecks) {
            $safetyCheck = $this->performSafetyChecks($student, $newId);
            if ($safetyCheck !== true) {
                return $safetyCheck;
            }
        }

        $oldId = $student->id;

        // Pre-update verification
        $preUpdateVerification = $this->verifyPreUpdateState($student);
        if (! $preUpdateVerification['success']) {
            return $preUpdateVerification;
        }

        DB::beginTransaction();

        try {
            // Perform the ID update
            $updateResult = $this->performIdUpdate($student, $newId, 'Student ID update via admin interface');

            if (! $updateResult['success']) {
                DB::rollBack();

                return $updateResult;
            }

            $updateResults = $updateResult['updated_records'];

            // Log the change to database for audit trail and undo functionality
            $changeLog = StudentIdChangeLog::create([
                'old_student_id' => (string) $oldId,
                'new_student_id' => (string) $newId,
                'student_name' => $student->full_name,
                'changed_by' => Auth::user()?->email ?? 'System',
                'affected_records' => $updateResults,
                'backup_data' => [
                    'student_data' => $student->toArray(),
                    'timestamp' => now()->toISOString(),
                ],
                'reason' => 'Student ID update via admin interface',
            ]);

            DB::commit();

            // Calculate total updated records
            $totalUpdated = $updateResults['total_updated'];

            Log::info('Student ID updated successfully', [
                'old_id' => $oldId,
                'new_id' => $newId,
                'student_name' => $student->full_name,
                'updated_records' => $updateResults,
                'total_updated' => $totalUpdated,
                'change_log_id' => $changeLog->id,
            ]);

            return [
                'success' => true,
                'message' => "Student ID successfully updated from {$oldId} to {$newId}. Updated {$updateResults['total_updated']} related records.",
                'old_id' => $oldId,
                'new_id' => $newId,
                'updated_records' => $updateResults,
                'change_log_id' => $changeLog->id,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update student ID', [
                'old_id' => $oldId,
                'new_id' => $newId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update student ID: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate the new ID
     */
    private function validateNewId(Student $student, int $newId): array|true
    {
        // Check if new ID is the same as current
        if ($student->id === $newId) {
            return [
                'success' => false,
                'message' => 'New ID cannot be the same as the current ID',
            ];
        }

        // Check if new ID already exists
        if (Student::where('id', $newId)->exists()) {
            return [
                'success' => false,
                'message' => "Student ID {$newId} already exists",
            ];
        }

        // Validate ID format (assuming positive integers)
        if ($newId <= 0) {
            return [
                'success' => false,
                'message' => 'Student ID must be a positive integer',
            ];
        }

        return true;
    }

    /**
     * Perform additional safety checks before updating
     */
    private function performSafetyChecks(Student $student, int $newId): array|true
    {
        // Check if student has active enrollments
        $activeEnrollments = $student->subjectEnrolled()->count();
        if ($activeEnrollments > 5) {
            return [
                'success' => false,
                'message' => "Student has {$activeEnrollments} active enrollments. This is a high-risk operation. Please confirm in the form to proceed.",
            ];
        }

        // Check if student has recent transactions
        $recentTransactions = $student->StudentTransactions()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentTransactions > 3) {
            return [
                'success' => false,
                'message' => "Student has {$recentTransactions} recent transactions. Changing ID may affect financial records. Please confirm in the form to proceed.",
            ];
        }

        // Check if the new ID follows institutional patterns
        if ($newId < 100000 || $newId > 9999999) {
            return [
                'success' => false,
                'message' => "New ID {$newId} doesn't follow institutional ID patterns (should be 6-7 digits). Please verify this is correct.",
            ];
        }

        return true;
    }

    /**
     * Verify the state before updating
     */
    private function verifyPreUpdateState(Student $student): array
    {
        // Verify student exists and is accessible
        if (! $student->exists) {
            return [
                'success' => false,
                'message' => 'Student record does not exist or is not accessible.',
            ];
        }

        // Verify student is not soft deleted
        if ($student->trashed()) {
            return [
                'success' => false,
                'message' => 'Cannot update ID of a deleted student record.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Pre-update verification passed.',
        ];
    }

    /**
     * Verify the state after updating
     */
    private function verifyPostUpdateState(int $oldId, int $newId): array
    {
        // Verify the student record was updated
        $updatedStudent = Student::find($newId);
        if (! $updatedStudent) {
            return [
                'success' => false,
                'message' => 'Student record with new ID was not found after update.',
            ];
        }

        // Verify old ID no longer exists
        $oldStudent = Student::find($oldId);
        if ($oldStudent) {
            return [
                'success' => false,
                'message' => 'Old student ID still exists after update. Data integrity compromised.',
            ];
        }

        // Verify related records were updated
        $remainingOldReferences = $this->getAffectedRecordsSummary($oldId);
        $totalOldReferences = array_sum($remainingOldReferences);

        if ($totalOldReferences > 0) {
            return [
                'success' => false,
                'message' => "Found {$totalOldReferences} records still referencing old ID {$oldId}. Update incomplete.",
            ];
        }

        return [
            'success' => true,
            'message' => 'Post-update verification passed.',
        ];
    }

    /**
     * Update all related records manually
     */
    private function updateRelatedRecordsManually(int $oldId, int $newId): array
    {
        $results = [];

        // Update StudentTuition records
        $results['student_tuitions'] = StudentTuition::where('student_id', $oldId)->update(['student_id' => $newId]);

        // Update StudentTransaction records
        $results['student_transactions'] = StudentTransaction::where('student_id', $oldId)->update(['student_id' => $newId]);

        // Update StudentEnrollment records (student_id is stored as string)
        // Include soft-deleted records to maintain data integrity
        $results['student_enrollments'] = StudentEnrollment::withTrashed()
            ->where('student_id', (string) $oldId)
            ->update(['student_id' => (string) $newId]);

        // Update ClassEnrollment records
        $results['class_enrollments'] = ClassEnrollment::where('student_id', $oldId)->update(['student_id' => $newId]);

        // Update SubjectEnrollment records
        $results['subject_enrollments'] = SubjectEnrollment::where('student_id', $oldId)->update(['student_id' => $newId]);

        // Update Account records (if they reference student by person_id)
        $results['accounts'] = Account::where('person_id', $oldId)
            ->where('person_type', Student::class)
            ->update(['person_id' => $newId]);

        // Update student_clearances table if it exists
        try {
            $results['student_clearances'] = DB::table('student_clearances')
                ->where('student_id', $oldId)
                ->update(['student_id' => $newId]);
        } catch (\Exception $e) {
            // Table might not exist, log but don't fail
            Log::warning('Could not update student_clearances table: '.$e->getMessage());
            $results['student_clearances'] = 0;
        }

        $results['total_updated'] = array_sum($results);

        return $results;
    }

    /**
     * Get a summary of records that will be affected by the ID change
     */
    public function getAffectedRecordsSummary(int|string $studentId): array
    {
        // Convert to integer if it's a valid numeric string
        if (is_string($studentId)) {
            if (! is_numeric($studentId)) {
                return []; // Invalid ID format, return empty array
            }
            $studentId = (int) $studentId;
        }

        $summary = [
            'student_tuitions' => StudentTuition::where('student_id', $studentId)->count(),
            'student_transactions' => StudentTransaction::where('student_id', $studentId)->count(),
            'student_enrollments' => StudentEnrollment::withTrashed()->where('student_id', (string) $studentId)->count(),
            'class_enrollments' => ClassEnrollment::where('student_id', $studentId)->count(),
            'subject_enrollments' => SubjectEnrollment::where('student_id', $studentId)->count(),
            'accounts' => Account::where('person_id', $studentId)
                ->where('person_type', Student::class)
                ->count(),
        ];

        // Add counts for additional tables
        $additionalTables = [
            'student_clearances',
            'student_grades',
            'student_schedules',
            'student_assessments',
            'student_documents',
            'student_payments',
            'student_fees',
            'student_records',
        ];

        foreach ($additionalTables as $table) {
            try {
                // Check if table exists and has student_id column
                $exists = DB::select("SELECT 1 FROM information_schema.tables WHERE table_name = ? AND table_schema = 'public'", [$table]);
                if (! empty($exists)) {
                    $hasColumn = DB::select("SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = 'student_id' AND table_schema = 'public'", [$table]);
                    if (! empty($hasColumn)) {
                        $count = DB::table($table)->where('student_id', $studentId)->count();
                        if ($count > 0) {
                            $summary[$table] = $count;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log but don't fail - table might not exist
                Log::warning("Could not count records in table {$table}: ".$e->getMessage());
            }
        }

        return $summary;
    }

    /**
     * Check if a student ID is available
     */
    public function isIdAvailable(int|string $id): bool
    {
        // Convert to integer if it's a valid numeric string
        if (is_string($id)) {
            if (! is_numeric($id)) {
                return false; // Invalid ID format
            }
            $id = (int) $id;
        }

        return ! Student::where('id', $id)->exists();
    }

    /**
     * Generate a suggested new ID based on existing patterns
     */
    public function generateSuggestedId(): int
    {
        // Get the highest existing ID and add 1
        $maxId = Student::max('id') ?? 0;

        return $maxId + 1;
    }

    /**
     * Undo a student ID change
     *
     * @param  int  $changeLogId  The ID of the change log entry
     * @return array Result array with success status and message
     */
    public function undoStudentIdChange(int $changeLogId): array
    {
        $changeLog = StudentIdChangeLog::find($changeLogId);

        if (! $changeLog) {
            return [
                'success' => false,
                'message' => 'Change log not found',
            ];
        }

        if ($changeLog->is_undone) {
            return [
                'success' => false,
                'message' => 'This change has already been undone',
            ];
        }

        $oldId = $changeLog->new_student_id; // Current ID (what we want to change from)
        $newId = $changeLog->old_student_id; // Original ID (what we want to change back to)

        // Check if the original ID is now available
        if (! $this->isIdAvailable((int) $newId)) {
            return [
                'success' => false,
                'message' => "Cannot undo: Original student ID {$newId} is now taken by another student",
            ];
        }

        // Find the current student
        $student = Student::find($oldId);
        if (! $student) {
            return [
                'success' => false,
                'message' => "Cannot undo: Student with ID {$oldId} not found",
            ];
        }

        DB::beginTransaction();

        try {
            // Perform the reverse update
            $result = $this->performIdUpdate($student, (int) $newId, "Undo of change #{$changeLogId}");

            if (! $result['success']) {
                DB::rollBack();

                return $result;
            }

            // Mark the change as undone
            $changeLog->update([
                'is_undone' => true,
                'undone_at' => now(),
                'undone_by' => Auth::user()?->email ?? 'System',
            ]);

            DB::commit();

            Log::info('Student ID change undone successfully', [
                'change_log_id' => $changeLogId,
                'reverted_from' => $oldId,
                'reverted_to' => $newId,
                'undone_by' => Auth::user()?->email ?? 'System',
            ]);

            return [
                'success' => true,
                'message' => "Successfully undone student ID change. Reverted from {$oldId} back to {$newId}.",
                'old_id' => $oldId,
                'new_id' => $newId,
                'change_log_id' => $changeLogId,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to undo student ID change', [
                'change_log_id' => $changeLogId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to undo student ID change: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Helper method to perform the actual ID update (used by both update and undo)
     */
    private function performIdUpdate(Student $student, int $newId, string $reason): array
    {
        $oldId = $student->id;

        // Create a new student record with the new ID
        $studentData = $student->toArray();
        $studentData['id'] = $newId;
        $studentData['updated_at'] = now();

        // Insert the new student record using raw query to avoid auto-increment issues
        DB::table('students')->insert($studentData);

        // Update all related records manually
        $updateResults = $this->updateRelatedRecordsManually($oldId, $newId);

        // Delete the old student record
        DB::table('students')->where('id', $oldId)->delete();

        // Verify the update was successful
        $updatedStudent = Student::find($newId);
        if (! $updatedStudent) {
            throw new \Exception("Failed to find student with new ID {$newId} after update");
        }

        // Post-update verification
        $postUpdateVerification = $this->verifyPostUpdateState($oldId, $newId);
        if (! $postUpdateVerification['success']) {
            throw new \Exception($postUpdateVerification['message']);
        }

        return [
            'success' => true,
            'updated_records' => $updateResults,
        ];
    }

    /**
     * Get recent student ID changes that can be undone
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentChanges(int $limit = 10)
    {
        return StudentIdChangeLog::undoable()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get change log for a specific student
     *
     * @param  int|string  $studentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStudentChangeHistory($studentId)
    {
        return StudentIdChangeLog::where('old_student_id', (string) $studentId)
            ->orWhere('new_student_id', (string) $studentId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Perform a dry run to check what would be updated
     *
     * @param  bool  $bypassSafetyChecks  Whether to bypass safety checks
     */
    public function dryRun(Student $student, int $newId, bool $bypassSafetyChecks = false): array
    {
        $validation = $this->validateNewId($student, $newId);
        if ($validation !== true) {
            return $validation;
        }

        // Check safety only if not bypassed
        if (! $bypassSafetyChecks) {
            $safetyCheck = $this->performSafetyChecks($student, $newId);
            if ($safetyCheck !== true) {
                return $safetyCheck;
            }
        }

        $summary = $this->getAffectedRecordsSummary($student->id);
        $totalRecords = array_sum($summary);

        return [
            'success' => true,
            'message' => "Dry run successful. {$totalRecords} records would be updated.",
            'affected_records' => $summary,
            'total_records' => $totalRecords,
            'old_id' => $student->id,
            'new_id' => $newId,
        ];
    }
}
