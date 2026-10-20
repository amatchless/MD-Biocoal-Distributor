<?php

use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });

        // Seed the standard list here too, so `migrate` alone on the server is enough.
        DB::table('departments')->insert(array_map(
            fn ($name) => ['name' => $name, 'created_at' => now(), 'updated_at' => now()],
            Department::DEFAULTS,
        ));

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('manager_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
