<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class SubjectEnrollment
 *
 * @property-read \App\Models\Classes|null $class
 * @property-read \App\Models\Student|null $student
 * @property-read \App\Models\StudentEnrollment|null $studentEnrollment
 * @property-read \App\Models\Subject|null $subject
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment query()
 * @mixin \Eloquent
 */
final class SubjectEnrollment extends Model
{
    protected $table = 'subject_enrollments';

    protected $casts = [
        'subject_id' => 'int',
        'class_id' => 'int',
        'grade' => 'float',
        'student_id' => 'int',
        'semester' => 'int',
        'enrollment_id' => 'int',
        'is_credited' => 'bool',
        'credited_subject_id' => 'int',
        'is_modular' => 'bool',
        'lecture_fee' => 'float',
        'laboratory_fee' => 'float',
        'enrolled_lecture_units' => 'int',
        'enrolled_laboratory_units' => 'int',
    ];

    protected $fillable = [
        'subject_id',
        'class_id',
        'grade',
        'instructor',
        'student_id',
        'academic_year',
        'school_year',
        'semester',
        'enrollment_id',
        'remarks',
        'classification',
        'school_name',
        'is_credited',
        'credited_subject_id',
        'section',
        'is_modular',
        'lecture_fee',
        'laboratory_fee',
        'enrolled_lecture_units',
        'enrolled_laboratory_units',
    ];

    public function guestEnrollment(): BelongsTo
    {
        return $this->belongsTo(GuestEnrollment::class, 'enrollment_id', 'id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function studentEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($model): void {
            $highestId = static::max('id');
            $model->id = $highestId ? $highestId + 1 : 1;
        });

        self::updating(function ($model): void {
            // Check if class_id has changed
            if ($model->isDirty('class_id') && $model->getOriginal('class_id') !== null) {
                $oldClassId = $model->getOriginal('class_id');
                $newClassId = $model->class_id;

                // Only proceed if both old and new class IDs are valid
                if ($oldClassId && $newClassId && $oldClassId !== $newClassId) {
                    static::handleClassEnrollmentUpdate($model, $oldClassId, $newClassId);
                }
            }
        });
    }

    /**
     * Handle updating class enrollment when subject enrollment class changes
     */
    protected static function handleClassEnrollmentUpdate($subjectEnrollment, $oldClassId, $newClassId): void
    {
        try {
            $oldClass = Classes::find($oldClassId);
            $newClass = Classes::find($newClassId);

            if (!$oldClass || !$newClass) {
                \Illuminate\Support\Facades\Log::warning('Class not found during enrollment update', [
                    'old_class_id' => $oldClassId,
                    'new_class_id' => $newClassId,
                    'student_id' => $subjectEnrollment->student_id
                ]);
                return;
            }

            // Check if both classes are for the same subject
            if ($oldClass->subject_code !== $newClass->subject_code) {
                \Illuminate\Support\Facades\Log::warning('Attempting to move student between different subjects', [
                    'old_subject' => $oldClass->subject_code,
                    'new_subject' => $newClass->subject_code,
                    'student_id' => $subjectEnrollment->student_id
                ]);
                return;
            }

            // Find existing class enrollment for the old class
            $existingClassEnrollment = ClassEnrollment::where('student_id', $subjectEnrollment->student_id)
                ->where('class_id', $oldClassId)
                ->first();

            // Check if student is already enrolled in the new class
            $newClassEnrollment = ClassEnrollment::where('student_id', $subjectEnrollment->student_id)
                ->where('class_id', $newClassId)
                ->first();

            if ($newClassEnrollment) {
                // Student is already enrolled in the new class
                if ($existingClassEnrollment) {
                    // Remove the old enrollment to avoid duplicates
                    $existingClassEnrollment->delete();
                    $message = "Student moved from {$oldClass->subject_code} Section {$oldClass->section} to Section {$newClass->section}";
                } else {
                    $message = "Student already enrolled in {$newClass->subject_code} Section {$newClass->section}";
                }
            } else {
                if ($existingClassEnrollment) {
                    // Update the existing class enrollment to the new class
                    $existingClassEnrollment->class_id = $newClassId;
                    $existingClassEnrollment->save();
                    $message = "Student moved from {$oldClass->subject_code} Section {$oldClass->section} to Section {$newClass->section}";
                } else {
                    // Create new class enrollment if none existed
                    ClassEnrollment::create([
                        'student_id' => $subjectEnrollment->student_id,
                        'class_id' => $newClassId,
                        'status' => true
                    ]);
                    $message = "Student enrolled in {$newClass->subject_code} Section {$newClass->section}";
                }
            }

            \Illuminate\Support\Facades\Log::info('Class enrollment updated', [
                'student_id' => $subjectEnrollment->student_id,
                'old_class_id' => $oldClassId,
                'new_class_id' => $newClassId,
                'old_section' => $oldClass->section,
                'new_section' => $newClass->section,
                'subject_code' => $oldClass->subject_code,
                'action' => $message
            ]);

            // Send Filament notification
            \Filament\Notifications\Notification::make()
                ->title('Class Enrollment Updated')
                ->body($message)
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating class enrollment', [
                'error' => $e->getMessage(),
                'student_id' => $subjectEnrollment->student_id,
                'old_class_id' => $oldClassId,
                'new_class_id' => $newClassId,
                'trace' => $e->getTraceAsString()
            ]);

            \Filament\Notifications\Notification::make()
                ->title('Class Enrollment Update Failed')
                ->body('Failed to update class enrollment: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
