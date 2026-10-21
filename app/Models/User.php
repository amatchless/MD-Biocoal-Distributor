<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'manager_id', 'department_id', 'position_id', 'territory', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const SUPER_ADMIN = 'super-admin';
    public const MANAGER = 'manager';
    public const EMPLOYEE = 'employee';

    public const ROLE_LABELS = [
        self::SUPER_ADMIN => 'Super Admin',
        self::MANAGER => 'Manager',
        self::EMPLOYEE => 'Employee',
    ];

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
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function onboardedDealers(): HasMany
    {
        return $this->hasMany(Dealer::class, 'created_by');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN);
    }

    public function isManager(): bool
    {
        return $this->hasRole(self::MANAGER);
    }

    public function isEmployee(): bool
    {
        return $this->hasRole(self::EMPLOYEE);
    }

    public function roleName(): string
    {
        return $this->getRoleNames()->first() ?? self::EMPLOYEE;
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->roleName()] ?? 'User';
    }

    /**
     * IDs of the users whose onboardings this user may see.
     * Employees see their own, managers see their team's and their own, super admins see everyone (null).
     */
    public function visibleCreatorIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        if ($this->isManager()) {
            return $this->teamMembers()->pluck('id')->push($this->id)->all();
        }

        return [$this->id];
    }

    /** Super admins and the HR department see every employee's attendance. */
    public function seesAllAttendance(): bool
    {
        return $this->isSuperAdmin() || $this->isInHr();
    }

    /** Managers see their team's attendance; HR and super admins see everyone's. */
    public function canViewTeamAttendance(): bool
    {
        return $this->isManager() || $this->seesAllAttendance();
    }

    public function isInHr(): bool
    {
        return $this->department?->name === Department::HR;
    }

    /** Super admins and the HR department run employee onboarding. */
    public function canManageOnboarding(): bool
    {
        return $this->isSuperAdmin() || $this->isInHr();
    }

    /** The final approve / reject is for super admins and HR managers. */
    public function canApproveOnboarding(): bool
    {
        return $this->isSuperAdmin() || ($this->isInHr() && $this->isManager());
    }

    /** IDs of the users whose attendance this user may see, or null for everyone. */
    public function visibleAttendanceUserIds(): ?array
    {
        return $this->seesAllAttendance() ? null : $this->visibleCreatorIds();
    }
}
