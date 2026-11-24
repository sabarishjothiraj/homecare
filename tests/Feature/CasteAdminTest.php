<?php

namespace Tests\Feature;

use App\Models\Caste;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasteAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    /**
     * destroy() called the removed Caste::patients() relation and always 500'd.
     */
    public function test_an_unused_caste_can_be_deleted(): void
    {
        $caste = Caste::create(['name' => 'Unused', 'is_active' => true, 'display_order' => 1]);

        $this->actingAs($this->admin)
            ->delete("/admin/castes/{$caste->id}")
            ->assertRedirect('/admin/castes')
            ->assertSessionHas('success');

        $this->assertModelMissing($caste);
    }

    public function test_a_caste_held_by_an_employee_is_kept(): void
    {
        $caste = Caste::create(['name' => 'In Use', 'is_active' => true, 'display_order' => 1]);
        Employee::factory()->create(['caste_id' => $caste->id]);

        $this->actingAs($this->admin)
            ->from('/admin/castes')
            ->delete("/admin/castes/{$caste->id}")
            ->assertRedirect('/admin/castes')
            ->assertSessionHas('error');

        $this->assertModelExists($caste);
    }
}
