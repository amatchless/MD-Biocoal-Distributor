<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A designation within a department. Positions with no department are the standard
 * ladder used by every department that has no ladder of its own (today, all but Sales).
 */
#[Fillable(['name', 'abbreviation', 'department_id', 'display_order'])]
class Position extends Model
{
    /** The standard ladder, junior to senior: [name, abbreviation]. */
    public const STANDARD = [
        ['User', 'User'],
        ['Team Manager', 'TM'],
        ['Assistant Manager', 'AM'],
        ['Manager', 'Manager'],
        ['Director', 'Director'],
    ];

    /** Ladders for departments that have their own, junior to senior, keyed by department name. */
    public const BY_DEPARTMENT = [
        'Sales' => [
            ['Sales Officer', 'SO'],
            ['Associate Assistant Sales Manager', 'AASM'],
            ['Associate Sales Manager', 'ASM'],
            ['Regional Manager', 'RM'],
            ['Zonal Manager', 'ZM'],
            ['National Head', 'NH'],
            ['Director', 'Director'],
        ],
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Positions open to people in the given department, junior first. */
    public function scopeForDepartment(Builder $query, int|Department|null $department): Builder
    {
        $id = $department instanceof Department ? $department->id : $department;
        $hasOwn = $id && static::where('department_id', $id)->exists();

        return $query->when($hasOwn, fn ($q) => $q->where('department_id', $id), fn ($q) => $q->whereNull('department_id'))
            ->orderBy('display_order');
    }

    /** "Sales Officer (SO)", or just "Manager" when the name is the abbreviation. */
    public function label(): string
    {
        return $this->name === $this->abbreviation ? $this->name : "{$this->name} ({$this->abbreviation})";
    }

    /** Rows for every default position, with department names left for the caller to resolve. */
    public static function defaults(): array
    {
        $rows = [];
        foreach (self::STANDARD as $i => [$name, $abbr]) {
            $rows[] = ['department' => null, 'name' => $name, 'abbreviation' => $abbr, 'display_order' => $i + 1];
        }
        foreach (self::BY_DEPARTMENT as $dept => $ladder) {
            foreach ($ladder as $i => [$name, $abbr]) {
                $rows[] = ['department' => $dept, 'name' => $name, 'abbreviation' => $abbr, 'display_order' => $i + 1];
            }
        }

        return $rows;
    }
}
