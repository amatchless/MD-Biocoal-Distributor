<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->nullable()->unique();
            $table->string('partner_type', 20); // dealer | distributor
            $table->string('status', 20)->default('submitted')->index();

            // Business
            $table->string('business_name');
            $table->string('trade_name')->nullable();
            $table->string('entity_type', 40);
            $table->unsignedSmallInteger('year_established')->nullable();
            $table->date('incorporation_date')->nullable();
            $table->json('product_lines')->nullable();
            $table->string('annual_turnover', 40)->nullable();
            $table->string('storage_capacity', 100)->nullable();

            // Contact
            $table->string('contact_name');
            $table->string('contact_designation')->nullable();
            $table->string('contact_phone', 10);
            $table->string('alt_phone', 10)->nullable();
            $table->string('email')->nullable();
            $table->date('contact_dob')->nullable();

            // Address
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city');
            $table->string('district')->index();
            $table->string('state')->index();
            $table->string('pincode', 6);
            $table->string('territory')->nullable();
            $table->string('premises_ownership', 20)->nullable();

            // Registrations. Only the last 4 digits of Aadhaar are kept (UIDAI / DPDP).
            $table->boolean('gst_registered')->default(true);
            $table->string('gst_no', 15)->nullable()->index();
            $table->string('pan_no', 10)->index();
            $table->string('aadhaar_last4', 4)->nullable();
            $table->string('registration_no')->nullable();

            // Licences
            $table->boolean('has_pesticide_licence')->default(false);
            $table->string('pesticide_licence_no')->nullable();
            $table->date('pesticide_licence_expiry')->nullable();
            $table->boolean('has_fertilizer_licence')->default(false);
            $table->string('fertilizer_licence_no')->nullable();
            $table->date('fertilizer_licence_expiry')->nullable();

            // Bank (account number is stored encrypted)
            $table->string('bank_account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('bank_account_no')->nullable();
            $table->string('bank_ifsc', 11)->nullable();

            // Location captured on the employee's device at the premises
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('geo_accuracy', 8, 2)->nullable();
            $table->timestamp('geo_captured_at')->nullable();

            $table->text('visit_notes')->nullable();
            $table->boolean('consent_given')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->string('ip_address', 45)->nullable();

            // Review
            $table->text('review_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dealer_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // customer | supplier
            $table->string('name');
            $table->string('firm')->nullable();
            $table->string('phone', 10);
            $table->string('relationship_years', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('dealer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('original_name')->nullable();
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('geo_accuracy', 8, 2)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('dealer_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->text('remarks')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_activities');
        Schema::dropIfExists('dealer_documents');
        Schema::dropIfExists('dealer_references');
        Schema::dropIfExists('dealers');
    }
};
