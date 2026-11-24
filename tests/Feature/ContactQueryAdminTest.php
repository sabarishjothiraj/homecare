<?php

namespace Tests\Feature;

use App\Models\ContactQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ContactQueryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    /**
     * show() used to write status "read", which the enum rejects in strict
     * mode, so every new query 500'd when opened.
     */
    public function test_opening_a_new_query_marks_it_in_progress(): void
    {
        $query = $this->makeQuery();

        $this->actingAs($this->admin)->get("/admin/contact-queries/{$query->id}")
            ->assertOk()
            ->assertSee('In Progress');

        $this->assertSame('in_progress', $query->fresh()->status);
    }

    public function test_status_can_be_set_to_every_enum_value_and_old_values_are_rejected(): void
    {
        $query = $this->makeQuery();

        foreach (array_keys(ContactQuery::STATUSES) as $status) {
            $this->actingAs($this->admin)
                ->put("/admin/contact-queries/{$query->id}", ['status' => $status])
                ->assertSessionHasNoErrors();

            $this->assertSame($status, $query->fresh()->status);
        }

        foreach (['read', 'responded'] as $status) {
            $this->actingAs($this->admin)
                ->put("/admin/contact-queries/{$query->id}", ['status' => $status])
                ->assertSessionHasErrors('status');
        }
    }

    /**
     * respond() received an empty model (route segment mismatch) and wrote a
     * non-existent response_notes column, so nothing was ever saved.
     */
    public function test_respond_saves_the_response_on_the_right_query(): void
    {
        $query = $this->makeQuery();
        $other = $this->makeQuery();

        $this->actingAs($this->admin)
            ->from("/admin/contact-queries/{$query->id}")
            ->post("/admin/contact-queries/{$query->id}/respond", ['admin_response' => 'Called back, visit booked.'])
            ->assertRedirect("/admin/contact-queries/{$query->id}")
            ->assertSessionHas('success');

        $query->refresh();
        $this->assertSame('resolved', $query->status);
        $this->assertSame('Called back, visit booked.', $query->admin_response);
        $this->assertSame($this->admin->id, $query->responded_by);
        $this->assertNotNull($query->responded_at);
        $this->assertSame('new', $other->fresh()->status);

        $this->actingAs($this->admin)->get("/admin/contact-queries/{$query->id}")
            ->assertOk()
            ->assertSee('Called back, visit booked.')
            ->assertSee('Resolved');
    }

    public function test_index_counts_and_filters_use_the_stored_statuses(): void
    {
        $this->makeQuery(['name' => 'Fresh Enquiry']);
        $this->makeQuery(['name' => 'Being Handled', 'status' => 'in_progress']);
        $this->makeQuery(['name' => 'Already Answered', 'status' => 'resolved']);

        $this->actingAs($this->admin)->get('/admin/contact-queries')
            ->assertOk()
            ->assertViewHas('newCount', 1)
            ->assertViewHas('inProgressCount', 1)
            ->assertViewHas('resolvedCount', 1);

        $this->actingAs($this->admin)->get('/admin/contact-queries?status=resolved')
            ->assertOk()
            ->assertSee('Already Answered')
            ->assertDontSee('Being Handled');
    }

    public function test_contact_form_saves_a_query_but_drops_honeypot_submissions(): void
    {
        Log::spy();

        $this->get('/contact')
            ->assertOk()
            ->assertSee('name="hp_extra"', false);

        $this->from('/contact')->post('/contact', $this->contactPayload())
            ->assertRedirect('/contact')
            ->assertSessionHas('success');

        $this->from('/contact')->post('/contact', $this->contactPayload(['hp_extra' => 'http://spam.example']))
            ->assertRedirect('/contact')
            ->assertSessionHas('success');

        $this->assertSame(1, ContactQuery::count());

        // Only the IP is logged, never what was submitted.
        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context = []) => str_contains($message, 'honeypot') && $context === ['ip' => '127.0.0.1'])
            ->once();
    }

    public function test_contact_form_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->from('/contact')->post('/contact', $this->contactPayload())
                ->assertRedirect('/contact')
                ->assertSessionHas('success');
        }

        // The sixth goes back to the form with a message, not a bare 429.
        $this->from('/contact')->post('/contact', $this->contactPayload())
            ->assertRedirect('/contact')
            ->assertSessionHas('error')
            ->assertHeader('Retry-After');

        $this->assertSame(5, ContactQuery::count());
    }

    private function makeQuery(array $attributes = []): ContactQuery
    {
        return ContactQuery::create(array_merge([
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'message' => 'Need a night nurse for my father.',
        ], $attributes));
    }

    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'message' => 'Need a night nurse for my father.',
        ], $overrides);
    }
}
