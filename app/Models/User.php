<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Panel;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $avatar_url
 * @property string|null $theme_color
 * @property-read bool $is_cashier
 * @property-read bool $is_dept_head
 * @property-read bool $is_registrar
 * @property-read bool $is_super_admin
 * @property-read string $view_title_course
 * @property-read array $viewable_courses
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AdminTransaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereThemeColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 * @mixin \Eloquent
 */
final class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // Update this method to control access to the Filament panel.
        // Here, we allow access only to users with the Developer or Admin role.
        return in_array($this->role, [
            UserRole::Developer,
            UserRole::Admin,
        ]);
    }

    /** @returns array<int, UserRole> */
    public function lowerRoles(): array
    {
        return match (Auth::user()->role) {
            UserRole::Developer => [UserRole::Developer, UserRole::Admin, UserRole::User],
            UserRole::Admin => [UserRole::User],
            UserRole::User => [],
        };
    }

    /** @returns bool */
    public function isLowerInRole(): bool
    {
        if (Auth::user()->role === UserRole::Developer) {
            return true;
        }

        return in_array($this->role, Auth::user()->lowerRoles());
    }

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
            'role' => UserRole::class,
        ];
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(AdminTransaction::class, 'admin_id', 'id');
    }

    public function getIsSuperAdminAttribute(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function getIsCashierAttribute(): bool
    {
        return $this->hasRole('Cashier');
    }

    public function getIsRegistrarAttribute(): bool
    {
        return $this->hasRole('registrar');
    }

    public function getIsDeptHeadAttribute(): bool
    {
        return $this->hasAnyRole([
            'IT-head-dept',
            'BA-head-dept',
            'HM-head-dept',
        ]);
    }

    public function getViewableCoursesAttribute(): array
    {
        $courses = [];

        if ($this->can('view_IT_students_students')) {
            $courses = array_merge($courses, [1, 6, 10, 13]);
        }

        if ($this->can('view_BA_students_students')) {
            $courses = array_merge($courses, [4, 5, 8, 9]);
        }

        if ($this->can('view_HM_students_students')) {
            return array_merge($courses, [2, 3, 11, 12]);
        }

        return $courses;
    }

    public function getViewTitleCourseAttribute(): string
    {
        $titles = [];

        if ($this->can('view_IT_students_students')) {
            $titles[] = 'IT';
        }

        if ($this->can('view_BA_students_students')) {
            $titles[] = 'BA';
        }

        if ($this->can('view_HM_students_students')) {
            $titles[] = 'HM';
        }

        return implode(', ', $titles);
    }
}
