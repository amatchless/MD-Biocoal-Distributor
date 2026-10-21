<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EmployeeOnboarding;
use App\Models\OnboardingCheck;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $hr;
    private User $hrManager;
    private User $manager;
    private Department $sales;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $hrDept = Department::where('name', Department::HR)->first();
        $this->sales = Department::where('name', 'Sales')->first();
        $this->admin = User::role(User::SUPER_ADMIN)->first();
        $this->manager = User::factory()->create(['name' => 'Meena Manager'])->assignRole(User::MANAGER);
        $this->hr = User::factory()->create(['name' => 'Harini HR', 'department_id' => $hrDept->id, 'manager_id' => $this->manager->id])->assignRole(User::EMPLOYEE);
        $this->hrManager = User::factory()->create(['name' => 'Hari HR Head', 'department_id' => $hrDept->id])->assignRole(User::MANAGER);
    }

    /** Opens a case as HR and returns it with the candidate's token taken from the flashed link. */
    private function openCase(array $overrides = []): array
    {
        $response = $this->actingAs($this->hr)->post('/new-hires', $overrides + [
            'candidate_name' => 'Kiran Patil',
            'candidate_phone' => '9876543210',
            'candidate_email' => 'kiran@example.com',
            'department_id' => $this->sales->id,
            'position_id' => Position::where('abbreviation', 'SO')->value('id'),
            'manager_id' => $this->manager->id,
            'work_location' => 'Nashik',
            'joining_date' => now()->addDays(20)->toDateString(),
            'target_date' => now()->addDays(10)->toDateString(),
            'owner_id' => $this->hr->id,
        ]);

        $onboarding = EmployeeOnboarding::latest('id')->first();
        $response->assertRedirect(route('onboarding.show', $onboarding));
        $link = session('candidate_link');
        $this->assertNotNull($link);

        return [$onboarding, basename($link)];
    }

    private function candidateDetails(array $overrides = []): array
    {
        return $overrides + [
            'father_name' => 'Suresh Patil',
            'date_of_birth' => '1998-04-12',
            'gender' => 'Male',
            'current_address' => '12 Gangapur Road',
            'city' => 'Nashik',
            'state' => 'Maharashtra',
            'pincode' => '422013',
            'pan_no' => 'abcde1234f',
            'aadhaar_last4' => '4321',
            'is_fresher' => '0',
            'education' => [['qualification' => 'B.Sc. Agriculture', 'institution' => 'MPKV Rahuri', 'year' => '2019', 'score' => '72%']],
            'employment' => [['company' => 'Agro Inputs Ltd', 'designation' => 'Field Officer', 'from' => '2019-07', 'to' => '2026-08', 'contact_name' => 'R. Shah', 'contact_phone' => '9822012345']],
            'emergency_contact_name' => 'Suresh Patil',
            'emergency_contact_phone' => '9822000000',
            'bank_name' => 'State Bank of India',
            'bank_account_no' => '123456789012',
            'bank_ifsc' => 'sbin0001234',
        ];
    }

    private function allDocuments(): array
    {
        $files = [];
        foreach (['photo', 'aadhaar_masked', 'pan_card', 'address_proof', 'education_certificate', 'bank_proof', 'experience_letter'] as $cat) {
            $files[$cat] = [UploadedFile::fake()->image("$cat.jpg")];
        }

        return $files;
    }

    private function submitAsCandidate(string $token, array $overrides = [])
    {
        return $this->post("/join/$token", $this->candidateDetails($overrides) + [
            'files' => $this->allDocuments(),
            'consent' => '1',
            'action' => 'submit',
        ]);
    }

    public function test_only_hr_and_super_admins_can_open_new_hires(): void
    {
        $salesEmployee = User::factory()->create(['department_id' => $this->sales->id, 'manager_id' => $this->manager->id])->assignRole(User::EMPLOYEE);

        $this->actingAs($this->hr)->get('/new-hires')->assertOk()->assertSee('New hires');
        $this->actingAs($this->admin)->get('/new-hires')->assertOk();
        $this->actingAs($salesEmployee)->get('/new-hires')->assertForbidden();
        $this->actingAs($this->manager)->get('/new-hires/create')->assertForbidden();

        $this->actingAs($this->hr)->get('/dashboard')->assertSee('New hires');
        $this->actingAs($salesEmployee)->get('/dashboard')->assertDontSee('New hires');
    }

    public function test_hr_opens_a_case_with_all_checks_and_a_private_link(): void
    {
        [$onboarding, $token] = $this->openCase();

        $this->assertSame('invited', $onboarding->status);
        $this->assertMatchesRegularExpression('/^MDB-EMP-\d{4}-\d{5}$/', $onboarding->ref_no);
        $this->assertSame(array_keys(OnboardingCheck::CHECKS), $onboarding->checks()->orderBy('id')->pluck('check_key')->all());
        $this->assertSame(hash('sha256', $token), $onboarding->access_token_hash);
        $this->assertSame('created', $onboarding->activities()->value('action'));

        $this->get("/join/$token")->assertOk()->assertSee('Welcome, Kiran Patil')->assertSee('Aadhaar: last 4 digits only');
        $this->get('/join/'.str_repeat('x', 48))->assertNotFound();
    }

    public function test_candidate_can_save_a_draft_then_submit_everything(): void
    {
        [$onboarding, $token] = $this->openCase();

        $this->post("/join/$token", [
            'father_name' => 'Suresh Patil',
            'files' => ['photo' => [UploadedFile::fake()->image('me.jpg')]],
            'action' => 'save',
        ])->assertSessionHasNoErrors();

        $onboarding->refresh();
        $this->assertSame('invited', $onboarding->status);
        $this->assertSame('Suresh Patil', $onboarding->father_name);
        $this->assertSame(1, $onboarding->documents()->count());
        $doc = $onboarding->documents()->first();
        $this->assertNull($doc->uploaded_by);
        Storage::disk('local')->assertExists($doc->path);

        $this->submitAsCandidate($token)->assertSessionHasNoErrors();

        $onboarding->refresh();
        $this->assertSame('under_verification', $onboarding->status);
        $this->assertSame('ABCDE1234F', $onboarding->pan_no);
        $this->assertSame('SBIN0001234', $onboarding->bank_ifsc);
        $this->assertSame('123456789012', $onboarding->bank_account_no);
        $this->assertNotSame('123456789012', $onboarding->getRawOriginal('bank_account_no'), 'bank account is stored encrypted');
        $this->assertNotNull($onboarding->consent_at);
        $this->assertNotNull($onboarding->submitted_at);
        $this->assertSame('Agro Inputs Ltd', $onboarding->employment[0]['company']);
        $this->assertSame(8, $onboarding->documents()->count());

        // The link is now read-only.
        $this->get("/join/$token")->assertOk()->assertSee('Your details have been submitted');
        $this->post("/join/$token", ['action' => 'save'])->assertForbidden();
    }

    public function test_submission_requires_consent_details_and_required_documents(): void
    {
        [$onboarding, $token] = $this->openCase();

        $this->post("/join/$token", ['action' => 'submit'])
            ->assertSessionHasErrors(['father_name', 'pan_no', 'aadhaar_last4', 'education', 'employment', 'consent', 'bank_account_no']);

        $this->post("/join/$token", $this->candidateDetails() + [
            'files' => ['photo' => [UploadedFile::fake()->image('me.jpg')]],
            'consent' => '1',
            'action' => 'submit',
        ])->assertSessionHasErrors('documents');

        $this->assertSame('invited', $onboarding->fresh()->status);
    }

    public function test_aadhaar_accepts_only_the_last_four_digits(): void
    {
        [, $token] = $this->openCase();

        $this->post("/join/$token", ['aadhaar_last4' => '123412341234', 'action' => 'save'])->assertSessionHasErrors('aadhaar_last4');
    }

    public function test_fresher_skips_employment_and_the_check_is_marked_not_applicable(): void
    {
        [$onboarding, $token] = $this->openCase();
        $files = $this->allDocuments();
        unset($files['experience_letter']);

        $this->post("/join/$token", $this->candidateDetails(['is_fresher' => '1', 'employment' => []]) + [
            'files' => $files, 'consent' => '1', 'action' => 'submit',
        ])->assertSessionHasNoErrors();

        $check = $onboarding->checks()->where('check_key', 'employment')->first();
        $this->assertSame('under_verification', $onboarding->fresh()->status);
        $this->assertSame('not_applicable', $check->status);
        $this->assertStringContainsString('Fresher', $check->remarks);
    }

    public function test_expired_links_stop_working(): void
    {
        [$onboarding, $token] = $this->openCase();
        $onboarding->forceFill(['access_expires_at' => now()->subMinute()])->save();

        $this->get("/join/$token")->assertOk()->assertSee('This link has expired');
        $this->post("/join/$token", ['action' => 'save'])->assertForbidden();
    }

    public function test_a_new_link_replaces_the_old_one(): void
    {
        [$onboarding, $old] = $this->openCase();

        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/link")->assertSessionHas('candidate_link');
        $new = basename(session('candidate_link'));

        $this->get("/join/$old")->assertNotFound();
        $this->get("/join/$new")->assertOk();
    }

    public function test_candidate_can_remove_their_own_upload_before_submitting(): void
    {
        [$onboarding, $token] = $this->openCase();
        $this->post("/join/$token", ['files' => ['photo' => [UploadedFile::fake()->image('me.jpg')]], 'action' => 'save']);
        $doc = $onboarding->documents()->first();

        $this->delete("/join/$token/documents/{$doc->id}")->assertRedirect();

        $this->assertSame(0, $onboarding->documents()->count());
        Storage::disk('local')->assertMissing($doc->path);
    }

    public function test_hr_can_send_the_case_back_for_more_information(): void
    {
        [$onboarding, $token] = $this->openCase();
        $this->submitAsCandidate($token);

        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/request-info", ['info_request' => 'PAN photo is blurred'])
            ->assertSessionHas('candidate_link');
        $newToken = basename(session('candidate_link'));

        $this->assertSame('info_pending', $onboarding->fresh()->status);
        $this->get("/join/$newToken")->assertOk()->assertSee('PAN photo is blurred');

        $this->post("/join/$newToken", $this->candidateDetails(['bank_account_no' => '']) + [
            'files' => ['pan_card' => [UploadedFile::fake()->image('pan2.jpg')]], 'consent' => '1', 'action' => 'submit',
        ])->assertSessionHasNoErrors();

        $onboarding->refresh();
        $this->assertSame('under_verification', $onboarding->status);
        $this->assertSame('123456789012', $onboarding->bank_account_no, 'blank account number keeps the saved one');
    }

    public function test_full_verification_assessment_decision_and_login(): void
    {
        [$onboarding, $token] = $this->openCase();
        $this->submitAsCandidate($token);

        // Assessment is blocked until every check has an outcome.
        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/assess", ['risk_level' => 'low', 'risk_notes' => 'All fine'])
            ->assertSessionHasErrors('risk_level');

        $checks = $onboarding->checks;
        $discrepancy = $checks->firstWhere('check_key', 'address');
        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/checks/{$discrepancy->id}", ['status' => 'discrepancy'])
            ->assertSessionHasErrors('remarks');

        foreach ($checks as $check) {
            $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/checks/{$check->id}", [
                'status' => $check->check_key === 'address' ? 'discrepancy' : 'verified',
                'method' => 'Document review',
                'remarks' => $check->check_key === 'address' ? 'Proof shows old address; confirmed new address by call' : null,
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/references", [
            'kind' => 'previous_employer', 'contact_name' => 'R. Shah', 'organisation' => 'Agro Inputs Ltd', 'outcome' => 'confirmed', 'notes' => 'Dates and role confirmed',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/assess", ['risk_level' => 'low', 'risk_notes' => 'Minor address mismatch explained'])
            ->assertSessionHasNoErrors();
        $this->assertSame('risk_review', $onboarding->fresh()->status);

        // HR staff without the manager role cannot make the final decision.
        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/decide", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->hr)->get("/new-hires/{$onboarding->id}")->assertSee('Waiting for an HR manager or Super Admin');

        $this->actingAs($this->hrManager)->post("/new-hires/{$onboarding->id}/decide", ['decision' => 'approved'])->assertSessionHasNoErrors();
        $onboarding->refresh();
        $this->assertSame('approved', $onboarding->status);
        $this->assertSame($this->hrManager->id, $onboarding->decided_by);
        $this->assertNull($onboarding->access_token_hash);

        // Decided cases are locked.
        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/checks/{$discrepancy->id}", ['status' => 'verified'])->assertForbidden();

        $this->actingAs($this->hr)->get("/new-hires/{$onboarding->id}/report")
            ->assertOk()->assertSee('Employee Background Verification Report')->assertSee('Proof shows old address')
            ->assertSee('Low risk')->assertSee('APPROVED')->assertSee('R. Shah');

        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/account", ['email' => 'kiran.patil@mdbiocoals.com', 'password' => 'Welcome@123'])
            ->assertSessionHasNoErrors();
        $user = User::where('email', 'kiran.patil@mdbiocoals.com')->first();
        $this->assertTrue($user->isEmployee());
        $this->assertSame($this->sales->id, $user->department_id);
        $this->assertSame($this->manager->id, $user->manager_id);
        $this->assertSame($user->id, $onboarding->fresh()->user_id);

        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/account", ['email' => 'again@mdbiocoals.com', 'password' => 'Welcome@123'])->assertForbidden();

        $actions = $onboarding->activities()->pluck('action');
        foreach (['created', 'submitted', 'check_updated', 'reference_logged', 'assessed', 'approved', 'account_created'] as $action) {
            $this->assertContains($action, $actions);
        }
    }

    public function test_rejecting_or_approving_high_risk_needs_a_reason(): void
    {
        [$onboarding, $token] = $this->openCase();
        $this->submitAsCandidate($token);
        $onboarding->checks()->update(['status' => 'verified']);
        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/assess", ['risk_level' => 'high', 'risk_notes' => 'Employment dates do not match']);

        $this->actingAs($this->admin)->post("/new-hires/{$onboarding->id}/decide", ['decision' => 'approved'])->assertSessionHasErrors('decision_notes');
        $this->actingAs($this->admin)->post("/new-hires/{$onboarding->id}/decide", ['decision' => 'rejected'])->assertSessionHasErrors('decision_notes');
        $this->actingAs($this->admin)->post("/new-hires/{$onboarding->id}/decide", ['decision' => 'rejected', 'decision_notes' => 'Falsified experience'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $onboarding->fresh()->status);
    }

    public function test_documents_are_private_to_hr(): void
    {
        [$onboarding, $token] = $this->openCase();
        $this->submitAsCandidate($token);
        $doc = $onboarding->documents()->first();
        $salesEmployee = User::factory()->create(['department_id' => $this->sales->id, 'manager_id' => $this->manager->id])->assignRole(User::EMPLOYEE);

        $this->actingAs($this->hr)->get("/new-hires/{$onboarding->id}/documents/{$doc->id}")->assertOk();
        $this->actingAs($salesEmployee)->get("/new-hires/{$onboarding->id}/documents/{$doc->id}")->assertForbidden();
    }

    public function test_hr_can_add_documents_and_filter_the_list(): void
    {
        [$onboarding] = $this->openCase();
        [$other] = $this->openCase(['candidate_name' => 'Asha Rao', 'candidate_phone' => '9123456780']);

        $this->actingAs($this->hr)->post("/new-hires/{$onboarding->id}/documents", [
            'category' => 'other', 'files' => [UploadedFile::fake()->create('offer.pdf', 100, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->hr->id, $onboarding->documents()->value('uploaded_by'));

        $this->actingAs($this->hr)->get('/new-hires?q=Asha')->assertSee('Asha Rao')->assertDontSee('Kiran Patil');
        $this->actingAs($this->hr)->get("/new-hires/{$other->id}")->assertOk()->assertSee('Waiting for the candidate');
    }
}
