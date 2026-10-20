<?php

use App\Models\Position;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('abbreviation', 20);
            // NULL = the standard ladder, used by every department without its own.
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'abbreviation']);
        });

        // Seed the standard ladders here too, so `migrate` alone on the server is enough.
        $departments = DB::table('departments')->pluck('id', 'name');
        DB::table('positions')->insert(collect(Position::defaults())
            ->filter(fn ($p) => $p['department'] === null || $departments->has($p['department']))
            ->map(fn ($p) => [
                'name' => $p['name'],
                'abbreviation' => $p['abbreviation'],
                'department_id' => $p['department'] ? $departments[$p['department']] : null,
                'display_order' => $p['display_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->all());

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
        });

        Schema::dropIfExists('positions');
    }
};
