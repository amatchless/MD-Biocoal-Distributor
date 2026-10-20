<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'remarks', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->action) {
            'submitted' => 'Onboarding submitted',
            'edited' => 'Details updated',
            'resubmitted' => 'Updated and resubmitted',
            'documents_added' => 'Documents added',
            default => 'Marked '.(Dealer::STATUSES[$this->action] ?? $this->action),
        };
    }
}
