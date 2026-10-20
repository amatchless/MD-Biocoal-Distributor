<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dealer extends Model
{
    use SoftDeletes;

    public const TYPES = ['dealer' => 'Dealer', 'distributor' => 'Distributor'];

    public const STATUSES = [
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'needs_info' => 'Needs info',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    public const APPLICATION_TYPES = ['new' => 'New', 'renewal' => 'Renewal'];

    /** Constitution of the firm (the agreement requires changes to partners / directors to be notified). */
    public const ENTITY_TYPES = [
        'Proprietorship', 'Partnership', 'LLP', 'Private Limited', 'Public Limited', 'Cooperative Society', 'Other',
    ];

    /** Who signs for the dealer, as listed under "Dealer's Signature" in the agreement. */
    public const SIGNATORY_DESIGNATIONS = [
        'Proprietor', 'Partner', 'Director', 'Managing Director', 'President', 'Secretary', 'Authorised Member',
    ];

    /** Marketing policy: security deposit for a distributor, in rupees. */
    public const DISTRIBUTOR_SECURITY_DEPOSIT = 21000;

    public const DEPOSIT_MODES = ['RTGS', 'NEFT', 'DD', 'Cheque'];

    /** Marketing policy: security cheques must be from a current account at a nationalised bank. */
    public const NATIONALISED_BANKS = [
        'Bank of Baroda', 'Bank of India', 'Bank of Maharashtra', 'Canara Bank', 'Central Bank of India', 'Indian Bank',
        'Indian Overseas Bank', 'Punjab & Sind Bank', 'Punjab National Bank', 'State Bank of India', 'UCO Bank', 'Union Bank of India',
    ];

    /** Marketing policy: cash discount slabs. The distributor picks one up front. */
    public const CASH_DISCOUNT_SLABS = [
        'advance' => ['label' => 'Advance, or payment on the day of billing', 'rate' => '11%'],
        'days_1_15' => ['label' => 'Payment in 1 to 15 days', 'rate' => '8%'],
        'days_16_30' => ['label' => 'Payment in 16 to 30 days', 'rate' => '6%'],
        'days_31_45' => ['label' => 'Payment in 31 to 45 days', 'rate' => '4%'],
        'after_45' => ['label' => 'Payment after 45 days', 'rate' => 'Nil'],
    ];

    /** Version of the terms the dealer / distributor accepts, stored with the record. */
    public const TERMS_VERSIONS = [
        'distributor' => 'Marketing Policy FY 2026-27',
        'dealer' => 'Dealer Agreement (Annexure 5, 2.2.26)',
    ];

    public const STATES = [
        'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chandigarh', 'Chhattisgarh',
        'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir',
        'Jharkhand', 'Karnataka', 'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
        'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
        'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
    ];

    protected $guarded = ['id', 'ref_no', 'status', 'created_by', 'reviewed_by', 'reviewed_at', 'review_remarks'];

    protected $hidden = ['bank_account_no'];

    protected function casts(): array
    {
        return [
            'security_deposit_amount' => 'integer',
            'security_deposit_date' => 'date',
            'agreement_date' => 'date',
            'terms_accepted_at' => 'datetime',
            'pesticide_licence_expiry' => 'date',
            'fertilizer_licence_expiry' => 'date',
            'gst_registered' => 'boolean',
            'has_pesticide_licence' => 'boolean',
            'has_fertilizer_licence' => 'boolean',
            'consent_given' => 'boolean',
            'bank_account_no' => 'encrypted',
            'latitude' => 'float',
            'longitude' => 'float',
            'geo_accuracy' => 'float',
            'geo_captured_at' => 'datetime',
            'consent_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Dealer $dealer) {
            $prefix = $dealer->partner_type === 'distributor' ? 'DIS' : 'DLR';
            $dealer->ref_no = sprintf('MDB-%s-%s-%05d', $prefix, now()->format('Y'), $dealer->id);
            $dealer->saveQuietly();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function references(): HasMany
    {
        return $this->hasMany(DealerReference::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DealerDocument::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DealerActivity::class)->latest('id');
    }

    /** Only the onboardings the given user is allowed to see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->visibleCreatorIds();

        return $ids === null ? $query : $query->whereIn('created_by', $ids);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('partner_type', $v))
            ->when($filters['employee'] ?? null, fn ($q, $v) => $q->where('created_by', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['q'] ?? null, function ($q, $v) {
                $like = '%'.trim($v).'%';
                $q->where(fn ($w) => $w->where('business_name', 'like', $like)
                    ->orWhere('ref_no', 'like', $like)
                    ->orWhere('gst_no', 'like', $like)
                    ->orWhere('pan_no', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('contact_phone', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('district', 'like', $like));
            });
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function applicationTypeLabel(): string
    {
        return self::APPLICATION_TYPES[$this->application_type] ?? ucfirst((string) $this->application_type);
    }

    public function cashDiscountLabel(): ?string
    {
        $slab = self::CASH_DISCOUNT_SLABS[$this->cash_discount_slab] ?? null;

        return $slab ? "{$slab['label']} ({$slab['rate']})" : null;
    }

    /** Financial year a form dated today belongs to, e.g. "2026-27" (April to March). */
    public static function currentFinancialYear(): string
    {
        $start = now()->month >= 4 ? now()->year : now()->year - 1;

        return $start.'-'.substr((string) ($start + 1), -2);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->partner_type] ?? ucfirst($this->partner_type);
    }

    public function mapUrl(): string
    {
        return "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
    }

    public function maskedBankAccount(): ?string
    {
        $no = $this->bank_account_no;

        return $no ? str_repeat('•', max(0, strlen($no) - 4)).substr($no, -4) : null;
    }

    /** Licences that expire within the given number of days (or have already expired). */
    public function expiringLicences(int $days = 60): array
    {
        $out = [];
        foreach (['pesticide', 'fertilizer'] as $kind) {
            $expiry = $this->{"{$kind}_licence_expiry"};
            if ($this->{"has_{$kind}_licence"} && $expiry && $expiry->lte(now()->addDays($days))) {
                $out[] = ['kind' => ucfirst($kind), 'expiry' => $expiry, 'expired' => $expiry->isPast()];
            }
        }

        return $out;
    }
}
