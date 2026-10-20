<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::role(User::SUPER_ADMIN)->first();
    }

    private function dept(string $name): Department
    {
        return Department::where('name', $name)->first();
    }

    private function position(string $abbr, ?Department $department = null): Position
    {
        return Position::where('abbreviation', $abbr)->where('department_id', $department?->id)->first();
    }

    public function test_the_two_ladders_are_seeded_once(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(12, Position::count());
        $this->assertSame(['User', 'TM', 'AM', 'Manager', 'Director'], Position::forDepartment($this->dept('HR'))->pluck('abbreviation')->all());
        $this->assertSame(['SO', 'AASM', 'ASM', 'RM', 'ZM', 'NH', 'Director'], Position::forDepartment($this->dept('Sales'))->pluck('abbreviation')->all());
        $this->assertSame('Sales Officer (SO)', $this->position('SO', $this->dept('Sales'))->label());
    }

    public function test_the_form_offers_each_department_its_own_positions(): void
    {
        $this->actingAs($this->admin)->get('/users/create')
            ->assertOk()
            ->assertSee('Position / designation')
            ->assertSee('Associate Assistant Sales Manager (AASM)', false)
            ->assertSee('Manager');
    }

    public function test_position_is_saved_and_shown(): void
    {
        $sales = $this->dept('Sales');
        $rm = $this->position('RM', $sales);

        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'role' => User::MANAGER,
            'department_id' => $sales->id, 'position_id' => $rm->id, 'is_active' => '1', 'password' => 'secret123',
        ])->assertRedirect('/users');

        $user = User::where('email', 'ravi@example.com')->first();
        $this->assertSame($rm->id, $user->position_id);

        $this->actingAs($this->admin)->get('/users')->assertSee('Ravi Kumar')->assertSee('title="Regional Manager"', false);
        $this->actingAs($this->admin)->get('/departments')->assertSee('title="Regional Manager"', false);
        $this->actingAs($user)->get('/dashboard')->assertSee('Regional Manager (RM)');
    }

    public function test_a_position_from_another_ladder_is_rejected(): void
    {
        $hr = $this->dept('HR');

        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Asha', 'email' => 'asha@example.com', 'role' => User::MANAGER,
            'department_id' => $hr->id, 'position_id' => $this->position('ZM', $this->dept('Sales'))->id,
            'is_active' => '1', 'password' => 'secret123',
        ])->assertSessionHasErrors('position_id');

        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Asha', 'email' => 'asha@example.com', 'role' => User::MANAGER,
            'position_id' => $this->position('TM')->id, 'is_active' => '1', 'password' => 'secret123',
        ])->assertSessionHasErrors('position_id');

        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Asha', 'email' => 'asha@example.com', 'role' => User::MANAGER,
            'department_id' => $hr->id, 'position_id' => $this->position('TM')->id, 'is_active' => '1', 'password' => 'secret123',
        ])->assertRedirect('/users');

        $this->assertSame('TM', User::where('email', 'asha@example.com')->first()->position->abbreviation);
    }
}
