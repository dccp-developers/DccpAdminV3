<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Import Attribute class

/**
 * @property-read \App\Models\User|null $approver
 * @property-read \App\Models\Course|null $course
 * @property-read mixed $course_id
 * @property-read mixed $email
 * @property-read mixed $first_name
 * @property-read mixed $last_name
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment query()
 *
 * @mixin \Eloquent
 */
final class PendingEnrollment extends Model
{
    use HasFactory;

    protected $table = 'pending_enrollments';

    protected $fillable = [
        'data',
        'status',
        'remarks',
        'approved_by',
        'processed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array', // Cast the JSON column to an array
        'processed_at' => 'datetime',
    ];

    /**
     * Get the user who approved/rejected the enrollment.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // You can add more accessors for commonly used fields if needed
    public function getFirstNameAttribute()
    {
        return $this->data['first_name'] ?? null;
    }

    public function getLastNameAttribute()
    {
        return $this->data['last_name'] ?? null;
    }

    public function getEmailAttribute()
    {
        return $this->data['email'] ?? null;
    }

    public function getCourseIdAttribute()
    {
        return $this->data['course_id'] ?? null;
    }

    // Add a relationship to Course if needed for display
    public function course()
    {
        // Check if course_id exists in data before trying to relate
        if (isset($this->data['course_id'])) {
            return $this->belongsTo(Course::class, 'data->course_id'); // Adjust if course_id is stored differently
        }

        // Return a dummy relationship or null if course_id is not set
        return $this->belongsTo(Course::class)->whereRaw('1 = 0'); // Example: always returns empty
    }
}
