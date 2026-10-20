<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the dealers table in line with the company's paper documents:
 * the Distributor's Registration & Renewal Form, the Marketing Policy (FY 2026-27) and the Dealer Agreement.
 * Placeholder fields that are not on those documents are dropped.
 */
return new class extends Migration
{
    private array $placeholders = [
        'trade_name', 'year_established', 'incorporation_date', 'product_lines', 'annual_turnover', 'storage_capacity',
        'contact_designation', 'alt_phone', 'contact_dob', 'landmark', 'territory', 'premises_ownership', 'registration_no',
    ];

    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            // Registration form cover
            $table->string('application_type', 10)->default('new'); // new | renewal
            $table->string('financial_year', 7)->nullable(); // e.g. 2026-27
            $table->string('state_head')->nullable();
            $table->string('tehsil')->nullable();

            // Security deposit and the 2 security cheques (current account, nationalised bank)
            $table->unsignedInteger('security_deposit_amount')->nullable();
            $table->string('security_deposit_mode', 20)->nullable();
            $table->string('security_deposit_ref', 60)->nullable();
            $table->date('security_deposit_date')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('cheque_1_no', 6)->nullable();
            $table->string('cheque_2_no', 6)->nullable();

            // Marketing policy / dealer agreement
            $table->string('cash_discount_slab', 20)->nullable();
            $table->string('terms_version', 40)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->date('agreement_date')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_designation', 60)->nullable();
            $table->string('witness1_name')->nullable();
            $table->string('witness1_address')->nullable();
            $table->string('witness2_name')->nullable();
            $table->string('witness2_address')->nullable();

            // "TM & Above" block: company officials who appoint the dealer / distributor
            $table->string('employee_code', 30)->nullable();
            $table->string('manager_name')->nullable();
            $table->string('appointing_manager_name')->nullable();
            $table->string('appointing_manager_code', 30)->nullable();
            $table->string('appointing_manager_designation')->nullable();
            $table->string('appointing_manager_mobile', 10)->nullable();
            $table->string('appointing_manager_area')->nullable();
            $table->string('appointing_manager_state')->nullable();
            $table->string('reporting_head_name')->nullable();
            $table->string('reporting_head_designation')->nullable();
        });

        Schema::table('dealers', function (Blueprint $table) {
            $table->dropColumn($this->placeholders);
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->string('trade_name')->nullable();
            $table->unsignedSmallInteger('year_established')->nullable();
            $table->date('incorporation_date')->nullable();
            $table->json('product_lines')->nullable();
            $table->string('annual_turnover', 40)->nullable();
            $table->string('storage_capacity', 100)->nullable();
            $table->string('contact_designation')->nullable();
            $table->string('alt_phone', 10)->nullable();
            $table->date('contact_dob')->nullable();
            $table->string('landmark')->nullable();
            $table->string('territory')->nullable();
            $table->string('premises_ownership', 20)->nullable();
            $table->string('registration_no')->nullable();
        });

        Schema::table('dealers', function (Blueprint $table) {
            $table->dropColumn([
                'application_type', 'financial_year', 'state_head', 'tehsil',
                'security_deposit_amount', 'security_deposit_mode', 'security_deposit_ref', 'security_deposit_date',
                'bank_branch', 'cheque_1_no', 'cheque_2_no',
                'cash_discount_slab', 'terms_version', 'terms_accepted_at', 'agreement_date', 'signatory_name', 'signatory_designation',
                'witness1_name', 'witness1_address', 'witness2_name', 'witness2_address',
                'employee_code', 'manager_name', 'appointing_manager_name', 'appointing_manager_code', 'appointing_manager_designation',
                'appointing_manager_mobile', 'appointing_manager_area', 'appointing_manager_state', 'reporting_head_name', 'reporting_head_designation',
            ]);
        });
    }
};
