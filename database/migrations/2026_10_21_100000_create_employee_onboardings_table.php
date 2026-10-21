<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_onboardings', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->nullable()->unique();
            $table->string('status')->default('invited')->index();

            // Set up by HR
            $table->string('candidate_name');
            $table->string('candidate_phone', 20);
            $table->string('candidate_email')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('work_location')->nullable();
            $table->date('joining_date')->nullable();
            $table->date('target_date')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');

            // Filled in by the candidate
            $table->string('father_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('alternate_phone', 20)->nullable();
            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 6)->nullable();
            $table->string('pan_no', 10)->nullable();
            $table->string('aadhaar_last4', 4)->nullable();
            $table->boolean('is_fresher')->default(false);
            $table->json('education')->nullable();
            $table->json('employment')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_ifsc', 11)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
            $table->string('consent_user_agent', 300)->nullable();
            $table->timestamp('submitted_at')->nullable();

            // The candidate's private link (only a hash is stored)
            $table->string('access_token_hash', 64)->nullable()->unique();
            $table->timestamp('access_expires_at')->nullable();
            $table->text('info_request')->nullable();

            // Assessment and decision
            $table->string('risk_level', 10)->nullable();
            $table->text('risk_notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('onboarding_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_onboarding_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('onboarding_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_onboarding_id')->constrained()->cascadeOnDelete();
            $table->string('check_key');
            $table->string('status')->default('pending');
            $table->string('method')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_onboarding_id', 'check_key']);
        });

        Schema::create('onboarding_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_onboarding_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('contact_name');
            $table->string('organisation')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('outcome');
            $table->text('notes')->nullable();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('onboarding_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_onboarding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('remarks')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_activities');
        Schema::dropIfExists('onboarding_references');
        Schema::dropIfExists('onboarding_checks');
        Schema::dropIfExists('onboarding_documents');
        Schema::dropIfExists('employee_onboardings');
    }
};
