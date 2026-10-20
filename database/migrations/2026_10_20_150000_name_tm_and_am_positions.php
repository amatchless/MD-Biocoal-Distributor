<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAMES = ['TM' => 'Team Manager', 'AM' => 'Assistant Manager'];

    public function up(): void
    {
        foreach (self::NAMES as $abbr => $name) {
            DB::table('positions')->whereNull('department_id')->where('abbreviation', $abbr)->update(['name' => $name, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::NAMES) as $abbr) {
            DB::table('positions')->whereNull('department_id')->where('abbreviation', $abbr)->update(['name' => $abbr, 'updated_at' => now()]);
        }
    }
};
