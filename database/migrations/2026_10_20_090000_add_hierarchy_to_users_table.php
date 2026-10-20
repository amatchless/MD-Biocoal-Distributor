<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 15)->nullable()->after('email');
            $table->foreignId('manager_id')->nullable()->after('phone')->constrained('users')->nullOnDelete();
            $table->string('territory')->nullable()->after('manager_id');
            $table->boolean('is_active')->default(true)->after('territory');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['phone', 'territory', 'is_active']);
        });
    }
};
