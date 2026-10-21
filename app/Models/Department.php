<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Department extends Model
{
    /** Members of this department see everyone's attendance, like a super admin. */
    public const HR = 'HR';

    /** The departments every install starts with. */
    public const DEFAULTS = [
        'HR', 'Liasioning', 'Account', 'Media', 'Inventory', 'Labour', 'Production', 'RnD',
        'Sales', 'Marketing', 'Quality Control', 'Purchase', 'Dispatch', 'Admin', 'Security',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Positions specific to this department; empty when it uses the standard ladder. */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class)->orderBy('display_order');
    }
}
