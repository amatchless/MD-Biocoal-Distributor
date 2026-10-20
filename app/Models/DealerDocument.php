<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerDocument extends Model
{
    public const CATEGORIES = [
        'shop_front' => 'Shop / office front',
        'owner_at_premises' => 'Owner / director / partner at the business place',
        'premises_inside' => 'Premises inside / stock',
        'signboard' => 'Signboard',
        'signature' => 'Signature of dealer / distributor',
        'stamp' => 'Firm stamp / seal',
        'aadhaar_masked' => 'Aadhaar card (masked copy)',
        'pan_card' => 'PAN card',
        'gst_certificate' => 'GST certificate',
        'letterhead' => 'Company letterhead',
        'security_cheque' => 'Security cheque',
        'deposit_proof' => 'Security deposit receipt (RTGS / NEFT / DD)',
        'pesticide_licence' => 'Pesticide licence',
        'fertilizer_licence' => 'Fertilizer licence',
        'tax_copies' => 'Tax copies (ITR / returns)',
        'signed_form' => 'Signed paper form / agreement',
        'other' => 'Other',
    ];

    /** Categories captured as on-site photos, which carry the GPS position at capture time. */
    public const PHOTO_CATEGORIES = ['shop_front', 'owner_at_premises', 'premises_inside', 'signboard'];

    /**
     * The "required documents" list from the marketing policy, with how many copies it asks for.
     * Shown as a checklist in the form; the reviewer checks them before approving.
     */
    public const REQUIRED_FOR_DISTRIBUTOR = [
        'aadhaar_masked' => 1,
        'pan_card' => 1,
        'gst_certificate' => 1,
        'letterhead' => 2,
        'security_cheque' => 2,
        'deposit_proof' => 1,
        'pesticide_licence' => 1,
        'fertilizer_licence' => 1,
        'tax_copies' => 1,
    ];

    public const REQUIRED_FOR_DEALER = [
        'aadhaar_masked' => 1,
        'pan_card' => 1,
        'gst_certificate' => 1,
        'fertilizer_licence' => 1,
    ];

    protected $fillable = [
        'category', 'original_name', 'path', 'mime', 'size', 'latitude', 'longitude', 'geo_accuracy', 'captured_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geo_accuracy' => 'float',
            'captured_at' => 'datetime',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function label(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Document';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
