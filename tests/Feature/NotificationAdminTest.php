<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    /**
     * The page posts plain forms; they used to land on a raw JSON body.
     */
    public function test_mark_read_form_redirects_back_and_script_calls_get_json(): void
    {
        $first = $this->makeNotification();
        $second = $this->makeNotification();

        $this->actingAs($this->admin)
            ->from('/admin/notifications')
            ->post("/admin/notifications/{$first->id}/mark-read")
            ->assertRedirect('/admin/notifications')
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->postJson("/admin/notifications/{$second->id}/mark-read")
            ->assertOk()
            ->assertJson(['success' => true]);

        foreach ([$first, $second] as $notification) {
            $notification->refresh();
            $this->assertTrue($notification->is_read);
            $this->assertNotNull($notification->read_at);
        }
    }

    public function test_mark_all_read_redirects_back_and_stamps_read_at(): void
    {
        $this->makeNotification();
        $this->makeNotification();

        $this->actingAs($this->admin)
            ->from('/admin/notifications')
            ->post('/admin/notifications/mark-all-read')
            ->assertRedirect('/admin/notifications')
            ->assertSessionHas('success');

        $this->assertSame(0, Notification::unread()->count());
        $this->assertSame(0, Notification::whereNull('read_at')->count());
    }

    private function makeNotification(): Notification
    {
        return Notification::create([
            'type' => 'new_contact_query',
            'title' => 'New enquiry',
            'message' => 'A visitor sent a message.',
        ]);
    }
}
