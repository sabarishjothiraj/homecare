<?php

namespace Tests\Feature;

use App\Models\EmployeeField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeFieldTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_a_field_saved_without_a_display_order_goes_last(): void
    {
        EmployeeField::create(['name' => 'first_name', 'label' => 'First Name', 'type' => 'text', 'order' => 5]);
        EmployeeField::create(['name' => 'resume', 'label' => 'Resume', 'type' => 'file', 'order' => 9]);

        $this->actingAs($this->admin)->post('/admin/employee-fields', [
            'name' => 'languages',
            'label' => 'Languages Spoken',
            'type' => 'text',
            'is_active' => 1,
        ])->assertRedirect('/admin/employee-fields');

        $this->assertSame(10, EmployeeField::where('name', 'languages')->value('order'));
    }

    public function test_the_display_order_entered_is_saved_and_shown(): void
    {
        $this->actingAs($this->admin)->post('/admin/employee-fields', [
            'name' => 'languages',
            'label' => 'Languages Spoken',
            'type' => 'text',
            'display_order' => 37,
            'is_active' => 1,
        ])->assertRedirect('/admin/employee-fields');

        $field = EmployeeField::where('name', 'languages')->firstOrFail();
        $this->assertSame(37, $field->order);

        $this->get('/admin/employee-fields')->assertOk()->assertSee('>37<', false);
        $this->get("/admin/employee-fields/{$field->id}/edit")->assertOk()->assertSee('value="37"', false);

        // Blank on edit keeps the position.
        $this->put("/admin/employee-fields/{$field->id}", [
            'name' => 'languages',
            'label' => 'Languages',
            'type' => 'text',
            'display_order' => '',
            'is_active' => 1,
        ])->assertRedirect('/admin/employee-fields');
        $this->assertSame(37, $field->fresh()->order);
    }

    public function test_select_options_are_saved_as_a_list_and_render_on_the_join_page(): void
    {
        $this->actingAs($this->admin)->post('/admin/employee-fields', [
            'name' => 'specialization',
            'label' => 'Specialization',
            'type' => 'select',
            'options' => 'ICU, Elderly ,Pediatric,, ',
            'is_active' => 1,
        ])->assertRedirect('/admin/employee-fields');

        $field = EmployeeField::where('name', 'specialization')->firstOrFail();
        $this->assertSame(['ICU', 'Elderly', 'Pediatric'], $field->options);

        $this->get("/admin/employee-fields/{$field->id}/edit")
            ->assertOk()
            ->assertSee('ICU, Elderly, Pediatric');

        $this->get('/join')
            ->assertOk()
            ->assertSee('<option value="Pediatric"', false);
    }

    public function test_the_join_page_survives_options_saved_as_a_plain_string(): void
    {
        // What the old controller stored: the raw string, JSON-encoded by the cast.
        DB::table('employee_fields')->insert([
            'name' => 'gender',
            'label' => 'Gender',
            'type' => 'select',
            'options' => json_encode('Male, Female'),
            'is_active' => true,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/join')
            ->assertOk()
            ->assertSee('<option value="Female"', false);
    }

    public function test_a_select_field_needs_at_least_one_option(): void
    {
        $this->actingAs($this->admin)->post('/admin/employee-fields', [
            'name' => 'shift',
            'label' => 'Preferred Shift',
            'type' => 'select',
            'options' => ' , ',
            'is_active' => 1,
        ])->assertSessionHasErrors('options');

        $this->assertDatabaseMissing('employee_fields', ['name' => 'shift']);
    }

    public function test_a_mandatory_field_can_be_deactivated_and_moved_but_not_redefined(): void
    {
        $field = EmployeeField::create([
            'name' => 'nursing_license',
            'label' => 'Nursing License',
            'type' => 'file',
            'is_required' => true,
            'is_mandatory' => true,
            'is_document' => true,
            'is_active' => true,
            'order' => 100,
        ]);

        // Unchecked "Active" box: is_active is simply not sent.
        $this->actingAs($this->admin)->put("/admin/employee-fields/{$field->id}", [
            'name' => 'hacked',
            'label' => 'Hacked',
            'type' => 'text',
            'display_order' => 150,
        ])->assertRedirect('/admin/employee-fields');

        $field->refresh();
        $this->assertFalse($field->is_active);
        $this->assertSame(150, $field->order);
        $this->assertSame('nursing_license', $field->name);
        $this->assertSame('Nursing License', $field->label);
        $this->assertSame('file', $field->type);
        $this->assertTrue($field->is_required);

        $this->put("/admin/employee-fields/{$field->id}", ['is_active' => 1])->assertRedirect('/admin/employee-fields');
        $this->assertTrue($field->fresh()->is_active);
        $this->assertSame(150, $field->fresh()->order);
    }
}
