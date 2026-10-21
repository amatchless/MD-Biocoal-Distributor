<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * An employee background verification case (the proposal's "Employee Background Verification" module).
 * HR opens it, the candidate fills details and uploads documents through a private link,
 * HR runs the checks, records the risk assessment and an approver makes the final decision.
 */
class EmployeeOnboarding extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'invited' => 'Waiting for candidate',
        'info_pending' => 'Information pending',
        'under_verification' => 'Under verification',
        'risk_review' => 'Risk review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    /** Statuses in which the candidate's link accepts details and uploads. */
    public const CANDIDATE_EDITABLE = ['invited', 'info_pending'];

    /** Statuses in which HR can work on the checks. */
    public const VERIFYING = ['under_verification', 'risk_review'];

    public const RISK_LEVELS = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    public const GENDERS = ['Male', 'Female', 'Other'];

    /** How long a candidate link stays valid, in days. */
    public const LINK_DAYS = 14;

    protected $guarded = [
        'id', 'ref_no', 'status', 'created_by', 'access_token_hash', 'access_expires_at', 'submitted_at',
        'consent_at', 'consent_ip', 'consent_user_agent', 'risk_level', 'risk_notes', 'assessed_by', 'assessed_at',
        'decision_notes', 'decided_by', 'decided_at', 'user_id', 'info_request',
    ];

    protected $hidden = ['bank_account_no', 'access_token_hash'];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'target_date' => 'date',
            'date_of_birth' => 'date',
            'is_fresher' => 'boolean',
            'education' => 'array',
            'employment' => 'array',
            'bank_account_no' => 'encrypted',
            'consent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'access_expires_at' => 'datetime',
            'assessed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (EmployeeOnboarding $onboarding) {
            $onboarding->ref_no = sprintf('MDB-EMP-%s-%05d', now()->format('Y'), $onboarding->id);
            $onboarding->saveQuietly();

            foreach (array_keys(OnboardingCheck::CHECKS) as $key) {
                $onboarding->checks()->create(['check_key' => $key]);
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** The login created for the new hire once approved. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OnboardingDocument::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(OnboardingCheck::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(OnboardingReference::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OnboardingActivity::class)->latest('id');
    }

    /** Creates a fresh candidate link, replacing any earlier one. Returns the plain token, which is never stored. */
    public function issueAccessToken(): string
    {
        $token = Str::random(48);
        $this->forceFill([
            'access_token_hash' => hash('sha256', $token),
            'access_expires_at' => now()->addDays(self::LINK_DAYS),
        ])->save();

        return $token;
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('access_token_hash', hash('sha256', $token))->first();
    }

    public function linkExpired(): bool
    {
        return ! $this->access_expires_at || $this->access_expires_at->isPast();
    }

    public function candidateCanEdit(): bool
    {
        return in_array($this->status, self::CANDIDATE_EDITABLE, true) && ! $this->linkExpired();
    }

    public function isVerifying(): bool
    {
        return in_array($this->status, self::VERIFYING, true);
    }

    public function isDecided(): bool
    {
        return in_array($this->status, ['approved', 'rejected'], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function riskLabel(): ?string
    {
        return self::RISK_LEVELS[$this->risk_level] ?? null;
    }

    public function isOverdue(): bool
    {
        return $this->target_date && ! $this->isDecided() && $this->target_date->endOfDay()->isPast();
    }

    public function maskedBankAccount(): ?string
    {
        $no = (string) $this->bank_account_no;

        return $no === '' ? null : str_repeat('X', max(0, strlen($no) - 4)).substr($no, -4);
    }

    /**
     * Required document categories (with no upload yet), as labels.
     *
     * @return Collection<string, string>
     */
    public function missingDocuments(): Collection
    {
        $have = $this->documents->pluck('category')->unique();

        return collect(OnboardingDocument::requiredFor($this))
            ->reject(fn ($cat) => $have->contains($cat))
            ->mapWithKeys(fn ($cat) => [$cat => OnboardingDocument::CATEGORIES[$cat]]);
    }

    /** True once every check has an outcome other than pending / in progress. */
    public function checksComplete(): bool
    {
        return $this->checks->every(fn (OnboardingCheck $c) => $c->isResolved());
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function ($q, $s) {
                $like = '%'.$s.'%';
                $q->where(fn ($w) => $w->where('candidate_name', 'like', $like)
                    ->orWhere('ref_no', 'like', $like)
                    ->orWhere('candidate_phone', 'like', $like)
                    ->orWhere('candidate_email', 'like', $like)
                    ->orWhere('pan_no', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['department'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['owner'] ?? null, fn ($q, $v) => $q->where('owner_id', $v))
            ->when(! empty($filters['overdue']), fn ($q) => $q
                ->whereNotIn('status', ['approved', 'rejected'])
                ->whereDate('target_date', '<', now(config('app.display_timezone'))->toDateString()));
    }
}
