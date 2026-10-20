<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerReference extends Model
{
    protected $fillable = ['kind', 'name', 'firm', 'phone', 'relationship_years', 'notes'];

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }
}
