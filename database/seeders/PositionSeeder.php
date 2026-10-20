<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /** Creates any missing standard positions. Safe to run more than once. */
    public function run(): void
    {
        foreach (Position::defaults() as $p) {
            $departmentId = $p['department'] ? Department::where('name', $p['department'])->value('id') : null;
            if ($p['department'] && ! $departmentId) {
                continue;
            }

            Position::updateOrCreate(
                ['department_id' => $departmentId, 'abbreviation' => $p['abbreviation']],
                ['name' => $p['name'], 'display_order' => $p['display_order']],
            );
        }
    }
}
