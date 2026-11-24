<?php

namespace Tests\Feature;

use App\Models\EmployeeField;
use App\Models\JoinRequest;
use Database\Seeders\EmployeeFieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public application form, with the default (seeded) field set: five
 * mandatory document fields that the page does not render.
 */
class JoinFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmployeeFieldSeeder::class);
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Asha',
            'last_name' => 'Nair',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'address' => '1 Main Street, Kochi',
            'date_of_birth' => '1995-04-12',
            'years_of_experience' => 4,
            'emergency_contact_name' => 'Ravi',
            'emergency_contact_phone' => '9876500001',
            'emergency_contact_relationship' => 'Brother',
        ], $overrides);
    }

    public function test_the_visible_fields_are_enough_to_apply(): void
    {
        $this->get('/join')
            ->assertOk()
            ->assertSee('name="first_name"', false)
            ->assertDontSee('name="nursing_license"', false);

        $this->from('/join')->post('/join', $this->application())
            ->assertRedirect('/join')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $request = JoinRequest::sole();
        $this->assertSame('new', $request->status);
        $this->assertSame('Asha', $request->field_data['first_name']);
        $this->assertArrayNotHasKey('nursing_license', $request->field_data);
    }

    public function test_validation_errors_are_shown_on_the_page(): void
    {
        $this->from('/join')->followingRedirects()
            ->post('/join', $this->application(['email' => 'not-an-email', 'date_of_birth' => '']))
            ->assertOk()
            ->assertSee('Your application was not sent')
            ->assertSee('The email field must be a valid email address.')
            ->assertSee('The date of birth field is required.');

        $this->assertSame(0, JoinRequest::count());
    }

    public function test_an_error_flash_is_shown_on_the_page(): void
    {
        $this->withSession(['error' => 'Too many submissions. Please try again in a minute.'])
            ->get('/join')
            ->assertOk()
            ->assertSee('Too many submissions. Please try again in a minute.');
    }

    public function test_a_select_field_only_accepts_its_options(): void
    {
        EmployeeField::where('name', 'specialization')->update([
            'type' => 'select',
            'options' => json_encode(['ICU', 'Elderly', 'Pediatric']),
        ]);

        $this->from('/join')->post('/join', $this->application(['specialization' => 'Surgery']))
            ->assertSessionHasErrors('specialization');

        $this->from('/join')->post('/join', $this->application(['specialization' => 'ICU']))
            ->assertSessionHasNoErrors();

        $this->assertSame('ICU', JoinRequest::sole()->field_data['specialization']);
    }
}
