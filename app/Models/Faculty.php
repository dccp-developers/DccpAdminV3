<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Class Faculty
 *
 * @property-read \App\Models\Account|null $account
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClassEnrollment> $classEnrollments
 * @property-read int|null $class_enrollments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Classes> $classes
 * @property-read int|null $classes_count
 * @property-read string $full_name
 * @property-read string $name
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty query()
 *
 * @mixin \Eloquent
 */
final class Faculty extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasFactory, HasUuids, Notifiable;

    public $incrementing = false;

    protected $table = 'faculty';

    protected $casts = [
        'id' => 'string',
        'birth_date' => 'datetime',
        'status' => 'string',
        'gender' => 'string',
        'age' => 'int',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $fillable = [
        'id',
        'faculty_id_number',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'password',
        'phone_number',
        'department',
        'office_hours',
        'birth_date',
        'address_line1',
        'biography',
        'education',
        'courses_taught',
        'photo_url',
        'status',
        'gender',
        'age',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    private string $guard = 'faculty';

    // Get full name attribute
    public function getFullNameAttribute(): string
    {
        $name = mb_trim("{$this->last_name}, {$this->first_name} {$this->middle_name}");

        return $name !== '' && $name !== '0' ? $name : 'N/A';  // Return 'N/A' if the name is empty
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->photo_url) {
            return $this->photo_url;
        }

        // Default to gravatar
        $hash = md5(mb_strtolower(mb_trim($this->email)));

        return 'https://www.gravatar.com/avatar/'.$hash.'?d=mp&r=g&s=250';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'faculty';
    }

    // Relationships
    public function classes()
    {
        return $this->hasMany(Classes::class, 'faculty_id', 'id');
    }

    public function classEnrollments()
    {
        return $this->hasManyThrough(
            ClassEnrollment::class,
            Classes::class,
            'faculty_id',
            'class_id'
        );
    }

    // Update or add this method to ensure we always return a string
    public function getNameAttribute(): string
    {
        return $this->getFullNameAttribute();
    }

    /**
     * Get the account associated with this faculty member
     * Since Faculty uses UUID and accounts.person_id is bigint, we match by email
     */
    public function account()
    {
        return $this->hasOne(Account::class, 'email', 'email')
            ->where('person_type', Faculty::class);
    }
}
