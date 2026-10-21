<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::role(User::SUPER_ADMIN)->first();
        $this->manager = User::factory()->create(['name' => 'Meena Manager'])->assignRole(User::MANAGER);
        $this->employee = User::factory()->create(['name' => 'Ravi Field', 'manager_id' => $this->manager->id])->assignRole(User::EMPLOYEE);
    }

    private function punch(User $user, string $type, array $extra = [])
    {
        return $this->actingAs($user)->postJson('/attendance', ['type' => $type, 'latitude' => 19.9975, 'longitude' => 73.7898] + $extra);
    }

    public function test_employee_clocks_in_and_out_with_gps_selfie_and_note(): void
    {
        Storage::fake('public');

        $this->punch($this->employee, 'in', ['photo' => UploadedFile::fake()->image('selfie.jpg'), 'notes' => 'Nashik route'])->assertOk();
        $this->punch($this->employee, 'out')->assertOk();

        $records = Attendance::where('user_id', $this->employee->id)->orderBy('id')->get();
        $this->assertSame(['in', 'out'], $records->pluck('type')->all());
        $this->assertSame(19.9975, $records[0]->latitude);
        $this->assertSame('Nashik route', $records[0]->notes);
        $this->assertStringStartsWith('attendance/', $records[0]->photo_path);
        Storage::disk('public')->assertExists($records[0]->photo_path);
        $this->assertNull($records[1]->photo_path);
    }

    public function test_cannot_clock_in_twice_or_clock_out_first(): void
    {
        $this->punch($this->employee, 'out')->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->punch($this->employee, 'in')->assertOk();
        $this->punch($this->employee, 'in')->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->assertSame(1, Attendance::count());
    }

    public function test_location_is_required(): void
    {
        $this->actingAs($this->employee)->postJson('/attendance', ['type' => 'in'])
            ->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_dashboard_and_history_show_own_times(): void
    {
        $this->punch($this->employee, 'in', ['notes' => 'Started at depot']);

        $this->actingAs($this->employee)->get('/dashboard')->assertOk()->assertSee('Clock out')->assertSee('You are clocked in since');
        $this->actingAs($this->employee)->get('/attendance')->assertOk()->assertSee('Started at depot')->assertSee('google.com/maps?q=19.9975,73.7898', false);
    }

    public function test_employees_cannot_open_team_view(): void
    {
        $this->actingAs($this->employee)->get('/attendance/team')->assertForbidden();
    }

    public function test_manager_sees_only_their_team(): void
    {
        $other = User::factory()->create(['name' => 'Outsider Olga'])->assignRole(User::EMPLOYEE);
        $this->punch($this->employee, 'in', ['notes' => 'team note']);
        $this->punch($other, 'in', ['notes' => 'other note']);

        $this->actingAs($this->manager)->get('/attendance/team')
            ->assertOk()->assertSee('team note')->assertDontSee('other note');

        $this->actingAs($this->admin)->get('/attendance/team')
            ->assertOk()->assertSee('team note')->assertSee('other note');

        $this->actingAs($this->admin)->get('/attendance/team?employee='.$other->id)
            ->assertOk()->assertDontSee('team note')->assertSee('other note');
    }

    public function test_date_range_filter(): void
    {
        $this->punch($this->employee, 'in', ['notes' => 'today note']);
        $old = Attendance::create(['user_id' => $this->employee->id, 'type' => 'out', 'latitude' => 1, 'longitude' => 1, 'notes' => 'old note']);
        $old->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->actingAs($this->manager)->get('/attendance/team')->assertSee('today note')->assertDontSee('old note');
        $this->actingAs($this->manager)->get('/attendance/team?from='.now()->subDays(25)->toDateString().'&to='.now()->subDays(15)->toDateString())
            ->assertSee('old note')->assertDontSee('today note');
    }

    public function test_selfie_is_only_visible_to_the_owner_and_their_managers(): void
    {
        Storage::fake('public');
        $this->punch($this->employee, 'in', ['photo' => UploadedFile::fake()->image('selfie.jpg')]);
        $record = Attendance::first();
        $stranger = User::factory()->create()->assignRole(User::EMPLOYEE);

        $this->actingAs($this->employee)->get("/attendance/{$record->id}/photo")->assertOk();
        $this->actingAs($this->manager)->get("/attendance/{$record->id}/photo")->assertOk();
        $this->actingAs($this->admin)->get("/attendance/{$record->id}/photo")->assertOk();
        $this->actingAs($stranger)->get("/attendance/{$record->id}/photo")->assertForbidden();
    }
}
