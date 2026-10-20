<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::role(User::SUPER_ADMIN)->first();
    }

    public function test_the_fifteen_departments_are_seeded_once(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(15, Department::count());
        $this->assertEqualsCanonicalizing(Department::DEFAULTS, Department::pluck('name')->all());
    }

    public function test_super_admin_sees_departments_and_their_people(): void
    {
        $sales = Department::where('name', 'Sales')->first();
        $manager = User::factory()->create(['name' => 'Priya Sales', 'department_id' => $sales->id])->assignRole(User::MANAGER);

        $this->actingAs($this->admin)->get('/departments')
            ->assertOk()
            ->assertSee('Quality Control')
            ->assertSee('Priya Sales');

        $this->actingAs($this->admin)->get('/users?department='.$sales->id)->assertOk()->assertSee('Priya Sales');
    }

    public function test_others_cannot_see_departments(): void
    {
        $manager = User::factory()->create()->assignRole(User::MANAGER);

        $this->actingAs($manager)->get('/departments')->assertForbidden();
    }

    public function test_department_is_saved_when_creating_and_editing_a_user(): void
    {
        $hr = Department::where('name', 'HR')->first();
        $dispatch = Department::where('name', 'Dispatch')->first();

        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Asha', 'email' => 'asha@example.com', 'role' => User::MANAGER,
            'department_id' => $hr->id, 'is_active' => '1', 'password' => 'secret123',
        ])->assertRedirect('/users');

        $user = User::where('email', 'asha@example.com')->first();
        $this->assertSame($hr->id, $user->department_id);

        $this->actingAs($this->admin)->get("/users/{$user->id}/edit")->assertOk()->assertSee('Dispatch');

        $this->actingAs($this->admin)->put("/users/{$user->id}", [
            'name' => 'Asha', 'email' => 'asha@example.com', 'role' => User::MANAGER,
            'department_id' => $dispatch->id, 'is_active' => '1',
        ])->assertRedirect('/users');

        $this->assertSame($dispatch->id, $user->fresh()->department_id);
    }
}
