<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Account
 *
 * @property-read Model|\Eloquent $UserPerson
 * @property-read \App\Models\Faculty|null $faculty
 * @property-read mixed $approved_pending_enrollment
 * @property-read mixed $is_faculty
 * @property-read mixed $is_student
 * @property-read mixed $profile_photo_url
 * @property-read Model|\Eloquent $person
 * @property-read \App\Models\ShsStudent|null $shsStudent
 * @property-read Student|null $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account withoutTrashed()
 * @mixin \Eloquent
 */
final class Account extends Model
{
    use SoftDeletes;

    protected $table = 'accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'person_id',
        'person_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    // protected $appends = [
    //     'profile_photo_url',
    // ];

    /**
     * Get the team that the invitation belongs to.
     *
     * @return HasMany<Team, covariant $this>
     */
    public function ownedTeams(): HasMany
    {
        return $this->ownedTeamsBase();
    }




    public function person()
    {
        return $this->morphTo();
    }
    public function getProfilePhotoUrlAttribute()
    {
        if (!$this->profile_photo_path) {
            return null;
        }

        // If profile_photo_path starts with http:// or https://, it's already a URL
        if (str_starts_with($this->profile_photo_path, 'http://') ||
            str_starts_with($this->profile_photo_path, 'https://')) {
            return $this->profile_photo_path;
        }

        // Otherwise get URL from S3
        return Storage::disk('s3')->url($this->profile_photo_path);
    }

    public function UserPerson()
    {
        return $this->morphTo();
    }

    /**
     * Get the student record if this user is a student.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'person_id', 'id');
    }

    /**
     * Get the faculty record if this user is a faculty.
     */
    public function faculty()
    {
        // Since Faculty uses UUID and accounts.person_id is bigint, we match by email
        return $this->belongsTo(Faculty::class, 'email', 'email');
    }

    /**
     * Get the SHS student record if this user is an SHS student.
     */
    public function shsStudent()
    {
        return $this->belongsTo(ShsStudent::class, 'person_id', 'student_lrn');
    }



    public function getIsStudentAttribute()
    {
        return $this->hasOne(Student::class, 'person_id');
    }

    public function getIsFacultyAttribute()
    {
        return $this->hasOne(Faculty::class, 'person_id');
    }

    /**
     * Check if the user is a student.
     */
    public function isStudent(): bool
    {
        return $this->role === 'student' &&
               ($this->person_type === Student::class || $this->person_type === ShsStudent::class);
    }

    /**
     * Check if the user is a faculty member.
     */
    public function isFaculty(): bool
    {
        return $this->role === 'faculty' && $this->faculty()->exists();
    }

    /**
     * Get the person record (polymorphic relationship).
     */
    public function getPerson()
    {
        if ($this->person_type === Student::class) {
            return $this->student;
        }

        if ($this->person_type === Faculty::class) {
            return $this->faculty;
        }

        if ($this->person_type === ShsStudent::class) {
            return $this->shsStudent;
        }

        return null;
    }

    /**
     * Check if account has any linked person
     */
    public function hasLinkedPerson(): bool
    {
        if ($this->person_type === Faculty::class) {
            // For Faculty, check if email matches and person_type is set
            return !empty($this->email) && !empty($this->person_type);
        }

        // For other person types, check person_id and person_type
        return !empty($this->person_id) && !empty($this->person_type);
    }

    

    // public function getPhotoUrl(): Attribute
    // {
    //     return Attribute::get(fn() => $this->profile_photo_path
    //         ? Storage::disk('s3')->url($this->profile_photo_path)
    //         : null);
    // }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_login' => 'boolean',
            'is_notification_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'otp_activated_at' => 'datetime',
            'last_login' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user's approved pending enrollment if the user is a guest.
     */
    public function getApprovedPendingEnrollmentAttribute()
    {
        if ($this->role !== 'guest') {
            return null;
        }
        $enrollment = \App\Models\PendingEnrollment::where(function ($query) {
            $query->whereJsonContains('data->email', $this->email)
                  ->orWhereJsonContains('data->enrollment_google_email', $this->email);
        })->where('status', 'approved')->first();
        return $enrollment;
    }
}
