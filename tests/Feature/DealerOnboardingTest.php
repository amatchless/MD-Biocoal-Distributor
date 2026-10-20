<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DealerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $employee;
    private User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->manager = User::factory()->create()->assignRole(User::MANAGER);
        $otherManager = User::factory()->create()->assignRole(User::MANAGER);
        $this->employee = User::factory()->create(['manager_id' => $this->manager->id])->assignRole(User::EMPLOYEE);
        $this->otherEmployee = User::factory()->create(['manager_id' => $otherManager->id])->assignRole(User::EMPLOYEE);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'partner_type' => 'dealer',
            'application_type' => 'new',
            'financial_year' => '2026-27',
            'business_name' => 'Krishi Kendra',
            'entity_type' => 'Proprietorship',
            'contact_name' => 'Ramesh',
            'contact_phone' => '9876543210',
            'state_head' => 'Anil Kumar',
            'address_line1' => 'Main Road',
            'city' => 'Krishnanagar',
            'tehsil' => 'Sadar',
            'district' => 'Nadia',
            'state' => 'West Bengal',
            'pincode' => '741101',
            'gst_registered' => '1',
            'gst_no' => '19ABCDE1234F1Z5',
            'pan_no' => 'ABCDE1234F',
            'aadhaar_last4' => '1234',
            'has_pesticide_licence' => '1',
            'pesticide_licence_no' => 'PL-1',
            'pesticide_licence_expiry' => now()->addYear()->format('Y-m-d'),
            'has_fertilizer_licence' => '0',
            'agreement_date' => now()->format('Y-m-d'),
            'witness1_name' => 'W One',
            'witness2_name' => 'W Two',
            'signatory_name' => 'Ramesh',
            'signatory_designation' => 'Proprietor',
            'employee_code' => 'mdb0042',
            'appointing_manager_name' => 'Rakesh Mondal',
            'appointing_manager_code' => 'MDB0007',
            'latitude' => '23.4058',
            'longitude' => '88.4907',
            'geo_accuracy' => '12',
            'terms_accepted' => '1',
            'consent' => '1',
            'documents' => [
                ['file' => UploadedFile::fake()->image('shop.jpg'), 'category' => 'shop_front', 'latitude' => '23.4058', 'longitude' => '88.4907', 'accuracy' => '10'],
                ['file' => UploadedFile::fake()->image('signature.png'), 'category' => 'signature'],
            ],
        ], $overrides);
    }

    private function distributorPayload(array $overrides = []): array
    {
        return $this->payload(array_merge([
            'partner_type' => 'distributor',
            'application_type' => 'renewal',
            'security_deposit_amount' => '21000',
            'security_deposit_mode' => 'RTGS',
            'security_deposit_ref' => 'UTR123456',
            'security_deposit_date' => now()->format('Y-m-d'),
            'bank_account_name' => 'Krishi Kendra',
            'bank_name' => 'Punjab National Bank',
            'bank_account_no' => '1234567890',
            'bank_ifsc' => 'PUNB0123400',
            'cheque_1_no' => '000101',
            'cheque_2_no' => '000102',
            'current_account' => '1',
            'cash_discount_slab' => 'days_1_15',
        ], $overrides));
    }

    public function test_employee_can_register_a_dealer_with_location_and_signature(): void
    {
        Storage::fake('local');

        $this->actingAs($this->employee)->post(route('dealers.store'), $this->payload())->assertRedirect();

        $dealer = Dealer::firstOrFail();
        $this->assertSame($this->employee->id, $dealer->created_by);
        $this->assertSame('submitted', $dealer->status);
        $this->assertEqualsWithDelta(23.4058, $dealer->latitude, 0.0001);
        $this->assertStringStartsWith('MDB-DLR-', $dealer->ref_no);
        $this->assertSame('MDB0042', $dealer->employee_code);
        $this->assertSame('Dealer Agreement (Annexure 5, 2.2.26)', $dealer->terms_version);
        $this->assertNotNull($dealer->terms_accepted_at);
        $this->assertSame('W One', $dealer->witness1_name);
        $this->assertNull($dealer->cash_discount_slab);
        $this->assertCount(2, $dealer->documents);
        Storage::disk('local')->assertExists($dealer->documents->first()->path);
    }

    public function test_distributor_needs_deposit_cheques_and_a_discount_slab(): void
    {
        Storage::fake('local');

        $this->actingAs($this->employee)->post(route('dealers.store'), $this->distributorPayload())->assertRedirect();
        $dealer = Dealer::firstOrFail();
        $this->assertStringStartsWith('MDB-DIS-', $dealer->ref_no);
        $this->assertSame('renewal', $dealer->application_type);
        $this->assertSame(21000, $dealer->security_deposit_amount);
        $this->assertSame('days_1_15', $dealer->cash_discount_slab);
        $this->assertSame('Marketing Policy FY 2026-27', $dealer->terms_version);
        $this->assertNull($dealer->witness1_name);

        $this->actingAs($this->employee)->post(route('dealers.store'), $this->distributorPayload([
            'security_deposit_amount' => '5000',
            'bank_name' => 'HDFC Bank',
            'cheque_2_no' => '000101',
            'current_account' => null,
            'cash_discount_slab' => null,
        ]))->assertSessionHasErrors(['security_deposit_amount', 'bank_name', 'cheque_2_no', 'current_account', 'cash_discount_slab']);
        $this->assertSame(1, Dealer::count());
    }

    public function test_location_signature_terms_and_matching_gst_are_required(): void
    {
        $payload = $this->payload([
            'latitude' => null,
            'gst_no' => '19ZZZZZ9999Z1Z5',
            'terms_accepted' => null,
            'witness2_name' => null,
            'employee_code' => null,
            'documents' => [
                ['file' => UploadedFile::fake()->image('shop.jpg'), 'category' => 'shop_front'],
            ],
        ]);

        $this->actingAs($this->employee)->post(route('dealers.store'), $payload)
            ->assertSessionHasErrors(['latitude', 'gst_no', 'terms_accepted', 'witness2_name', 'employee_code', 'signature']);
        $this->assertSame(0, Dealer::count());
    }

    public function test_form_and_detail_pages_render(): void
    {
        Storage::fake('local');
        $this->actingAs($this->employee)->get(route('dealers.create'))->assertOk()
            ->assertSee('Marketing policy, FY 2026-27')->assertSee('DEALER AGREEMENT')->assertSee('Appointing manager');

        $this->actingAs($this->employee)->post(route('dealers.store'), $this->distributorPayload());
        $dealer = Dealer::firstOrFail();
        $this->actingAs($this->employee)->get(route('dealers.show', $dealer))->assertOk()
            ->assertSee('Security deposit & cheques')->assertSee('Payment in 1 to 15 days (8%)')->assertSee('Company letterhead (2)');
        $this->actingAs($this->employee)->get(route('dealers.edit', $dealer))->assertOk();
        $this->actingAs($this->manager)->get(route('dealers.export'))->assertOk();
    }

    public function test_visibility_follows_the_hierarchy(): void
    {
        Storage::fake('local');
        $this->actingAs($this->employee)->post(route('dealers.store'), $this->payload());
        $dealer = Dealer::firstOrFail();

        $this->actingAs($this->employee)->get(route('dealers.show', $dealer))->assertOk();
        $this->actingAs($this->manager)->get(route('dealers.show', $dealer))->assertOk();
        $this->actingAs($this->otherEmployee)->get(route('dealers.show', $dealer))->assertForbidden();
        $this->actingAs($this->otherEmployee)->get(route('dealers.index'))->assertDontSee('Krishi Kendra');
        $this->actingAs($this->manager)->get(route('dealers.index'))->assertSee('Krishi Kendra');
    }

    public function test_only_managers_review_and_only_admins_manage_users(): void
    {
        Storage::fake('local');
        $this->actingAs($this->employee)->post(route('dealers.store'), $this->payload());
        $dealer = Dealer::firstOrFail();

        $this->actingAs($this->employee)->post(route('dealers.review', $dealer), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('dealers.review', $dealer), ['status' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $dealer->fresh()->status);

        $this->actingAs($this->manager)->get(route('users.index'))->assertForbidden();
        $admin = User::role(User::SUPER_ADMIN)->first();
        $this->actingAs($admin)->get(route('users.index'))->assertOk();
    }
}
