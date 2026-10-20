<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /** Creates any missing standard departments. Safe to run more than once. */
    public function run(): void
    {
        foreach (Department::DEFAULTS as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}
