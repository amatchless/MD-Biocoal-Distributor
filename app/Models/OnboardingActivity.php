<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit trail entry for an employee case. A null user means the candidate acted through their link. */
#[Fillable(['user_id', 'action', 'remarks', 'meta'])]
class OnboardingActivity extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actorName(): string
    {
        return $this->user?->name ?? 'Candidate';
    }

    public function label(): string
    {
        return match ($this->action) {
            'created' => 'Case opened',
            'link_issued' => 'Candidate link created',
            'candidate_saved' => 'Candidate saved details',
            'candidate_uploaded' => 'Candidate uploaded documents',
            'candidate_removed_document' => 'Candidate removed a document',
            'submitted' => 'Candidate submitted for verification',
            'details_updated' => 'Case details updated',
            'documents_added' => 'Documents added by HR',
            'info_requested' => 'Sent back to candidate',
            'check_updated' => 'Check updated: '.($this->meta['check'] ?? '').' → '.($this->meta['status'] ?? ''),
            'reference_logged' => 'Reference contact logged',
            'assessed' => 'Risk assessed: '.($this->meta['risk'] ?? ''),
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'account_created' => 'Employee login created',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
