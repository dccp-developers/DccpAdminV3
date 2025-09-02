<?php

declare(strict_types=1);

namespace App\Models;

use Exception;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * @property int $id
 * @property int $institution_id
 * @property int $student_id
 * @property string|null $lrn
 * @property string $student_type
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $suffix
 * @property string|null $email
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon $birth_date
 * @property string $gender
 * @property string $civil_status
 * @property string $nationality
 * @property string|null $religion
 * @property string|null $address
 * @property string|null $emergency_contact
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClassEnrollment> $Classes
 * @property-read int|null $classes_count
 * @property-read \App\Models\Course|null $Course
 * @property-read \App\Models\DocumentLocation|null $DocumentLocation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentTransaction> $StudentTransactions
 * @property-read int|null $student_transactions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentTuition> $StudentTuition
 * @property-read int|null $student_tuition_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $Transaction
 * @property-read int|null $transaction_count
 * @property-read \App\Models\Account|null $account
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClassEnrollment> $classEnrollments
 * @property-read int|null $class_enrollments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentClearance> $clearances
 * @property-read int|null $clearances_count
 * @property-read string $formatted_academic_year
 * @property-read mixed $full_name
 * @property-read mixed $picture1x1
 * @property-read mixed $student_picture
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\StudentsPersonalInfo|null $personalInfo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Resource> $resources
 * @property-read int|null $resources_count
 * @property-read \App\Models\StudentContact|null $studentContactsInfo
 * @property-read \App\Models\StudentEducationInfo|null $studentEducationInfo
 * @property-read \App\Models\StudentParentsInfo|null $studentParentInfo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubjectEnrollment> $subjectEnrolled
 * @property-read int|null $subject_enrolled_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubjectEnrollment> $subjectEnrolledCurrent
 * @property-read int|null $subject_enrolled_current_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subject> $subjects
 * @property-read int|null $subjects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentTransaction> $transactions
 * @property-read int|null $transactions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereCivilStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereEmergencyContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereLrn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereMiddleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereReligion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereStudentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereSuffix($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class Student extends Model
{
    use HasFactory, SoftDeletes;
    use Notifiable;
    // use Versionable;

    public $timestamps = true;

    protected $versionable = ['title', 'content'];

    protected $table = 'students';

    // protected $versionStrategy = VersionStrategy::SNAPSHOT;

    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'middle_name',
        'gender',
        'birth_date',
        'age',
        'address',
        'contacts',
        'course_id',
        'academic_year',
        'email',
        'remarks',
        'created_at',
        'updated_at',
        'profile_url',
        'student_contact_id',
        'student_parent_info',
        'student_education_id',
        'student_personal_id',
        'document_location_id',
        'student_id',
        'status',
        'clearance_status',
        'year_graduated',
        'special_order',
        'issued_date',
    ];

    protected $casts = [
        'id' => 'integer',
        'age' => 'integer',
        'course_id' => 'integer',
        'academic_year' => 'integer',
        'student_contact_id' => 'integer',
        'student_parent_info' => 'integer',
        'student_education_id' => 'integer',
        'student_personal_id' => 'integer',
        'document_location_id' => 'integer',
        'student_id' => 'integer',
        'birth_date' => 'date',
        'issued_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'contacts' => 'array',
    ];

    private bool $autoIncrement = false;

    private static int $cacheFor = 3600; // Cache for 1 hour

    private static array $cacheKeys = [
        'id',
        'full_name',
        'course_id',
        'academic_year',
    ];

    /**
     * Generate the next available student ID.
     * Finds the maximum existing ID and increments it.
     * Note: This simple approach might have race condition issues under high concurrency.
     * Consider database sequences or UUIDs for more robust solutions if needed.
     */
    public static function generateNextId(): int
    {
        // Lock the table to prevent race conditions during ID generation (optional but recommended)
        // DB::statement('LOCK TABLES students WRITE'); // MySQL specific lock

        try {
            // Find the maximum ID. Ensure it handles potential non-numeric IDs if any exist, though unlikely if primary key.
            $maxId = self::withTrashed()->max('id'); // Consider trashed students too? Or just max()?

            return ($maxId ? (int) $maxId : 0) + 1; // Increment max ID, or start from 1 if table is empty
        } finally {
            // DB::statement('UNLOCK TABLES'); // Release lock if using MySQL specific lock
        }
    }

    /**
     * Get the clearances for the student.
     */
    public function clearances()
    {
        return $this->hasMany(StudentClearance::class);
    }

    /**
     * Get the current semester clearance for the student.
     */
    public function currentClearance()
    {
        $settings = GeneralSetting::first();

        return $this->clearances()
            ->where('academic_year', $settings->getSchoolYear())
            ->where('semester', $settings->semester);
    }

    /**
     * Get the current clearance record as a relationship.
     * This method returns a relationship instance for use with Filament forms.
     */
    public function getCurrentClearanceRecord()
    {
        $settings = GeneralSetting::first();

        return $this->hasOne(StudentClearance::class)
            ->where('academic_year', $settings->getSchoolYear())
            ->where('semester', $settings->semester);
    }

    /**
     * Get the current clearance record as a model instance.
     *
     * @return StudentClearance|null
     */
    public function getCurrentClearanceModel()
    {
        return $this->getCurrentClearanceRecord()->first();
    }

    /**
     * Check if student has cleared their clearance for current semester.
     */
    public function hasCurrentClearance(): bool
    {
        $clearance = $this->getCurrentClearanceModel();

        return $clearance && $clearance->is_cleared;
    }

    /**
     * Get or create clearance for current semester.
     */
    public function getOrCreateCurrentClearance()
    {
        $clearance = $this->getCurrentClearanceModel();

        if (! $clearance) {
            return StudentClearance::createForCurrentSemester($this);
        }

        return $clearance;
    }

    /**
     * Mark student clearance as cleared for the current semester.
     *
     * @param  string|null  $clearedBy
     * @param  string|null  $remarks
     */
    public function markClearanceAsCleared(
        $clearedBy = null,
        $remarks = null
    ): bool {
        $clearance = $this->getOrCreateCurrentClearance();

        return $clearance->markAsCleared($clearedBy, $remarks);
    }

    /**
     * Mark student clearance as not cleared for the current semester.
     *
     * @param  string|null  $remarks
     */
    public function markClearanceAsNotCleared($remarks = null): bool
    {
        $clearance = $this->getOrCreateCurrentClearance();

        return $clearance->markAsNotCleared($remarks);
    }

    /**
     * Undo the last clearance action.
     */
    public function undoClearance(?string $remarks = null): bool
    {
        $clearance = $this->getCurrentClearanceModel();

        if (! $clearance) {
            return false;
        }

        // If clearance was marked as cleared, mark it as not cleared
        if ($clearance->is_cleared) {
            return $clearance->markAsNotCleared($remarks);
        }

        // If clearance was already marked as not cleared, mark it as cleared
        return $clearance->markAsCleared(null, $remarks);
    }

    public function DocumentLocation()
    {
        return $this->belongsTo(
            DocumentLocation::class,
            'document_location_id'
        );
    }

    public function Course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function subjects()
    {
        return $this->hasManyThrough(
            Subject::class,
            Course::class,
            'id', // Foreign key on Course table
            'course_id', // Foreign key on Subject table
            'course_id', // Local key on Students table
            'id' // Local key on Course table
        );
    }

    public function personalInfo()
    {
        return $this->belongsTo(
            StudentsPersonalInfo::class,
            'student_personal_id',
            'id'
        );
    }

    public function studentEducationInfo()
    {
        return $this->belongsTo(
            StudentEducationInfo::class,
            'student_education_id',
            'id'
        );
    }

    public function studentContactsInfo()
    {
        return $this->belongsTo(
            StudentContact::class,
            'student_contact_id',
            'id'
        );
    }

    public function studentParentInfo()
    {
        return $this->belongsTo(
            StudentParentsInfo::class,
            'student_parent_info',
            'id'
        );
    }

    public function classEnrollments()
    {
        return $this->hasMany(ClassEnrollment::class, 'student_id', 'id');
    }

    public function subjectEnrolled()
    {
        return $this->hasMany(SubjectEnrollment::class, 'student_id', 'id');
    }

    public function subjectEnrolledCurrent()
    {
        return $this->hasMany(SubjectEnrollment::class, 'student_id', 'id')
            ->where('school_year', config('app.school_year'))
            ->where('semester', config('app.semester'));
    }

    public function Classes()
    {
        return $this->hasMany(ClassEnrollment::class, 'student_id', 'id');
    }

    /**
     * Automatically enrolls a student in classes based on their subject enrollments
     *
     * Process:
     * 1. Gets all subject enrollments for the student based on the enrollment_id
     * 2. For each subject enrollment:
     *    - Gets the associated subject details
     *    - Finds matching classes based on:
     *      * Subject code
     *      * Course codes (JSON array containing the subject's course ID)
     *      * School year from settings
     *      * Academic year from subject
     *      * Current semester from settings
     * 3. For each matching class:
     *    - Checks if student is already enrolled
     *    - If not enrolled, creates a new class enrollment record
     *    - If already enrolled, skips creation
     * 4. Logs the enrollment process at key points
     */
    public function autoEnrollInClasses($enrollment_id = null): void
    {
        $subjectEnrollments = $this->subjectEnrolled->where(
            'enrollment_id',
            $enrollment_id
        );

        GeneralSetting::first();
        $settingsService = app(\App\Services\GeneralSettingsService::class);
        $notificationData = [];
        $errors = []; // Initialize $errors array

        // Flag to allow enrollment even when class appears full (override maximum_slots check)
        $forceEnrollWhenFull = config(
            'enrollment.force_enroll_when_full',
            false
        );

        foreach ($subjectEnrollments as $subjectEnrollment) {
            $subject = $subjectEnrollment->subject;

            Log::info(
                "Attempting to enroll student {$this->id} in classes for subject: {$subject->code}",
                [
                    'student_id' => $this->id,
                    'subject_code' => $subject->code,
                    'course_id' => $subject->course_id,
                    'academic_year' => $subject->academic_year,
                ]
            );

            try {
                // First, check if the student is already enrolled in a class for this subject
                $existingEnrollment = ClassEnrollment::whereHas(
                    'class',
                    function ($query) use ($subject): void {
                        $query->where('subject_code', $subject->code);
                    }
                )
                    ->where('student_id', $this->id)
                    ->first();

                if ($existingEnrollment) {
                    Log::info(
                        "Student {$this->id} is already enrolled in a class for subject {$subject->code}",
                        [
                            'class_id' => $existingEnrollment->class_id,
                        ]
                    );
                    $notificationData[] = [
                        'subject' => $subject->code,
                        'section' => $existingEnrollment->class->section ?? 'Unknown',
                        'status' => 'Already enrolled',
                    ];

                    continue; // Skip to next subject
                }

                // Find all potential classes for this subject
                $query = Classes::where('subject_code', $subject->code)
                    ->whereJsonContains(
                        'course_codes',
                        (string) $subject->course_id
                    )
                    ->where('school_year', $settingsService->getCurrentSchoolYearString())
                    ->where('semester', $settingsService->getCurrentSemester());

                // Handle academic_year filtering - if subject has null academic_year,
                // look for classes with either null academic_year OR any academic_year
                // This is especially important for subjects like PATHFIT that may be
                // available across multiple academic years
                if ($subject->academic_year !== null) {
                    $query->where('academic_year', $subject->academic_year);
                } else {
                    // For subjects with null academic_year, we should look for classes
                    // that either have null academic_year OR match the student's current academic year
                    // Since PATHFIT and similar subjects can be taken by any year level
                    $query->where(function ($academicYearQuery) {
                        $academicYearQuery->whereNull('academic_year')
                            ->orWhereNotNull('academic_year'); // Accept any academic year
                    });
                }

                Log::info('Query for classes: '.$query->toSql(), [
                    'bindings' => $query->getBindings(),
                    'academic_year' => $subject->academic_year,
                    'school_year' => $settingsService->getCurrentSchoolYearString(),
                    'semester' => $settingsService->getCurrentSemester(),
                ]);

                $classes = $query->get();

                if ($classes->isEmpty()) {
                    $errorMessage = "No classes found for subject {$subject->code}";
                    Log::warning($errorMessage, [
                        'student_id' => $this->id,
                        'subject_code' => $subject->code,
                        'course_id' => $subject->course_id,
                    ]);
                    $errors[] = $errorMessage;

                    continue;
                }

                Log::info(
                    "Found {$classes->count()} classes for subject {$subject->code}"
                );

                // Sort classes by available slots (least full first)
                $availableClasses = $classes->sortBy(function ($class): int|float {
                    // Check if maximum_slots is set and not zero to avoid division by zero
                    if (empty($class->maximum_slots)) {
                        return PHP_INT_MAX; // Sort classes with no maximum to the end
                    }
                    $enrolledCount = ClassEnrollment::where(
                        'class_id',
                        $class->id
                    )->count();

                    // Calculate fill percentage
                    return ($enrolledCount / $class->maximum_slots) * 100;
                });

                $enrolled = false;
                $fullClasses = 0;

                foreach ($availableClasses as $class) {
                    $enrolledCount = ClassEnrollment::where(
                        'class_id',
                        $class->id
                    )->count();
                    $maxSlots = $class->maximum_slots ?: 0;

                    // Log detailed class information
                    Log::info(
                        "Checking class section {$class->section} for subject {$subject->code}",
                        [
                            'class_id' => $class->id,
                            'enrolled_count' => $enrolledCount,
                            'maximum_slots' => $maxSlots,
                            'is_full' => $maxSlots > 0 && $enrolledCount >= $maxSlots,
                        ]
                    );

                    // If class is full and we're not forcing enrollment, skip to next class
                    if (
                        $maxSlots > 0 &&
                        $enrolledCount >= $maxSlots &&
                        ! $forceEnrollWhenFull
                    ) {
                        Log::info(
                            "Class {$class->id} (Section {$class->section}) is full, trying next section"
                        );
                        $fullClasses++;

                        continue;
                    }

                    // Try to enroll the student in this class
                    try {
                        ClassEnrollment::create([
                            'class_id' => $class->id,
                            'student_id' => $this->id,
                        ]);

                        // If we got here, enrollment was successful
                        $remainingSlots = $maxSlots
                            ? $maxSlots - $enrolledCount - 1
                            : 'unlimited';
                        $notificationData[] = [
                            'subject' => $subject->code,
                            'section' => $class->section,
                            'slots' => $maxSlots > 0
                                    ? $remainingSlots.' remaining'
                                    : 'no slot limit',
                        ];

                        Log::info(
                            "Successfully enrolled student {$this->id} in class {$class->id} (Section {$class->section})",
                            [
                                'remaining_slots' => $remainingSlots,
                            ]
                        );
                        $enrolled = true;
                        break;
                    } catch (Exception $e) {
                        $errorMessage =
                            "Failed to enroll in {$subject->code} Section {$class->section}: ".
                            $e->getMessage();
                        Log::error($errorMessage, ['exception' => $e]);
                        // Continue trying other classes rather than failing immediately
                    }
                }

                if (! $enrolled) {
                    $errorMessage = "No available slots in any section for subject {$subject->code}";
                    // Add more diagnostic info
                    if ($fullClasses > 0) {
                        $errorMessage .= " ({$fullClasses} classes found but all are full)";
                    }
                    Log::warning($errorMessage);
                    $errors[] = $errorMessage;
                }
            } catch (Exception $e) {
                $errorMessage =
                    "Error processing subject {$subject->code}: ".
                    $e->getMessage();
                Log::error($errorMessage, ['exception' => $e]);
                $errors[] = $errorMessage;
            }
        }

        // Create Filament notification
        $notificationTitle = $errors === []
            ? 'Enrollment Successful'
            : 'Enrollment Completed with Issues';
        $notificationColor = $errors === [] ? 'success' : 'warning';

        // Build notification content
        $content = '';
        foreach ($notificationData as $data) {
            $content .=
                "Subject: {$data['subject']} - Section: {$data['section']}".
                (isset($data['slots']) ? " ({$data['slots']})" : '').
                (isset($data['status']) ? " ({$data['status']})" : '').
                "\n";
        }

        if ($errors !== []) {
            $content .= "\nIssues:\n".implode("\n", $errors);
        }

        // Send notification
        Notification::make()
            ->title($notificationTitle)
            ->body($content)
            ->color($notificationColor)
            ->persistent()
            ->send();
    }

    public function subjectsByYear($academicYear)
    {
        return $this->subjects()
            ->where('academic_year', $academicYear)
            ->get()
            ->map(fn ($subject): string => "{$subject->title} (Code: {$subject->code}, Units: {$subject->units})")
            ->join(', ');
    }

    public function StudentTuition()
    {
        return $this->hasMany(StudentTuition::class, 'student_id', 'id');
    }

    public function studentEnrollments()
    {
        // Handle type mismatch by using a query that casts the student ID to string
        // Include soft-deleted records for complete data integrity
        return StudentEnrollment::withTrashed()->where('student_id', (string) $this->id);
    }

    /**
     * Get the current semester tuition record as a relationship.
     * This method returns a relationship instance for use with Filament forms.
     */
    public function getCurrentTuitionRecord()
    {
        $settings = GeneralSetting::first();

        return $this->hasOne(StudentTuition::class)
            ->where('school_year', $settings->getSchoolYear())
            ->where('semester', $settings->semester);
    }

    /**
     * Get the current semester tuition record as a model instance.
     *
     * @return StudentTuition|null
     */
    public function getCurrentTuitionModel()
    {
        return $this->getCurrentTuitionRecord()->first();
    }

    /**
     * Get or create tuition record for current semester.
     */
    public function getOrCreateCurrentTuition()
    {
        $tuition = $this->getCurrentTuitionModel();

        if (! $tuition) {
            $settings = GeneralSetting::first();
            $course = $this->Course;

            $tuition = StudentTuition::create([
                'student_id' => $this->id,
                'academic_year' => $this->academic_year,
                'semester' => $settings->semester,
                'school_year' => $settings->getSchoolYear(),
                'total_tuition' => 0,
                'total_lectures' => 0,
                'total_laboratory' => 0,
                'total_miscelaneous_fees' => $course ? $course->getMiscellaneousFee() : 3500,
                'overall_tuition' => 0,
                'total_balance' => 0,
                'downpayment' => 0,
                'discount' => 0,
                'status' => 'pending',
            ]);
        }

        return $tuition;
    }

    public function StudentTransactions()
    {
        return $this->hasMany(StudentTransaction::class, 'student_id', 'id');
    }

    public function StudentTransact($type, $amount, $description): void
    {
        StudentTransaction::create([
            'student_id' => $this->id,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'balance' => $this->StudentTuition->balance +
                ($type === 'credit' ? $amount : -$amount),
            'date' => now(),
        ]);
    }

    public function getStudentPictureAttribute()
    {
        return $this->DocumentLocation->picture_1x1 ?? '';
    }

    // get Full name
    public function getFullNameAttribute()
    {
        return cache()->remember(
            "student_{$this->id}_full_name",
            3600,
            fn (): string => "{$this->last_name}, {$this->first_name} {$this->middle_name}"
        );
    }

    public function account()
    {
        return $this->hasOne(Account::class, 'person_id', 'id');
    }

    //    transaction for students
    public function Transaction()
    {
        return $this->belongsToMany(
            Transaction::class,
            'student_transactions',
            'student_id',
            'transaction_id'
        );
    }

    public function getPicture1x1Attribute()
    {
        return $this->DocumentLocation->picture_1x1 ?? '';
    }

    public function getFormattedAcademicYearAttribute(): string
    {
        $years = [
            1 => '1st year',
            2 => '2nd year',
            3 => '3rd year',
            4 => '4th year',
        ];

        return $years[$this->academic_year] ?? 'Unknown year';
    }

    // Add this relationship to the Students class
    public function resources()
    {
        return $this->morphMany(Resource::class, 'resourceable');
    }

    public function documents()
    {
        return $this->resources()->whereIn('type', [
            'assessment',
            'certificate',
        ]);
    }

    public function assessmentDocuments()
    {
        return $this->resources()->where('type', 'assessment');
    }

    public function certificateDocuments()
    {
        return $this->resources()->where('type', 'certificate');
    }

    public function withoutGraduatesScope()
    {
        return $this->where('academic_year', '!=', '5');
    }

    public function transactions()
    {
        return $this->hasMany(StudentTransaction::class, 'student_id', 'id');
    }

    protected static function boot(): void
    {
        parent::boot();
        self::saving(function (Student $student): void {
            if ($student->studentContactsInfo) {
                $student->studentContactsInfo->save();
                $student->student_contact_id =
                    $student->studentContactsInfo->id;
            }
            if ($student->studentParentInfo) {
                $student->studentParentInfo->save();
                $student->student_parent_info = $student->studentParentInfo->id;
            }
            if ($student->studentEducationInfo) {
                $student->studentEducationInfo->save();
                $student->student_education_id =
                    $student->studentEducationInfo->id;
            }
            if ($student->personalInfo) {
                $student->personalInfo->save();
                $student->student_personal_id = $student->personalInfo->id;
            }
            if ($student->DocumentLocation) {
                $student->DocumentLocation->save();
                $student->document_location_id = $student->DocumentLocation->id;
            }
        });
        self::forceDeleting(function ($student): void {
            $student->StudentTransactions()->delete();
            $student->StudentTuition()->delete();
            $student->StudentParentInfo()->delete();
            $student->StudentEducationInfo()->delete();
            $student->StudentContactsInfo()->delete();
            $student->personalInfo()->delete();
            $student->subjectEnrolled()->delete();
            // $student->DocumentLocation()->delete();
            $student->account()->delete();
            $student->classEnrollments()->delete();
        });
        // Note: Student ID updates are handled by StudentIdUpdateService
        // to ensure proper transaction management and data integrity
    }
}
