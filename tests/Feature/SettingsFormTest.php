<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The admin Settings page.
 */
class SettingsFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        Config::setValue('otp_email', 'owner@example.com');
    }

    private function requiredFields(array $overrides = []): array
    {
        return $overrides + [
            'company_name' => 'Home Nursing Care',
            'company_email' => 'info@homenursing.com',
            'company_phone' => '+1 234 567 8900',
            'company_address' => '123 Healthcare Street',
            'contract_alert_days' => 7,
            'contract_max_assignments' => 10,
        ];
    }

    /**
     * A content editor: settings.manage, but not otp.config.update.
     */
    private function contentEditor(): User
    {
        $role = Role::create(['name' => 'Content', 'slug' => 'content']);
        $role->permissions()->attach(Permission::create(['name' => 'Settings', 'slug' => 'settings.manage'])->id);
        Permission::create(['name' => 'OTP config', 'slug' => 'otp.config.update']);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * The form looked these up under group names the seeder never used, so
     * it showed blanks - and saving then wrote the blanks over the real text.
     */
    public function test_the_form_shows_the_stored_values(): void
    {
        Setting::set('contract_alert_days', '3');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Quality Care at Your Doorstep')
            ->assertSee('We are a leading home nursing service provider', false)
            ->assertSee('Join our team of healthcare professionals', false)
            ->assertSee('Home Nursing - Professional Healthcare Staffing')
            ->assertSee('value="3"', false)
            ->assertSee('value="owner@example.com"', false);
    }

    public function test_notifications_can_be_switched_off_again(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.settings.update'), $this->requiredFields(['notification_enabled' => '1']));
        $this->assertSame('1', Setting::where('key', 'notification_enabled')->value('value'));

        $this->actingAs($admin)->post(route('admin.settings.update'), $this->requiredFields());
        $this->assertSame('0', Setting::where('key', 'notification_enabled')->value('value'));
    }

    public function test_settings_manage_alone_cannot_change_the_login_approver(): void
    {
        $this->actingAs($this->contentEditor())
            ->post(route('admin.settings.update'), $this->requiredFields([
                'company_name' => 'Renamed Co',
                'otp_email' => 'editor@example.com',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('owner@example.com', Config::getValue('otp_email'));
        $this->assertSame('Renamed Co', Setting::where('key', 'company_name')->value('value'));
    }

    public function test_the_approver_fields_are_read_only_without_the_otp_permission(): void
    {
        $this->actingAs($this->contentEditor())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('needs the OTP configuration permission')
            ->assertSee('maxlength="255" disabled', false);
    }

    public function test_the_otp_permission_allows_changing_the_approver(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.settings.update'), $this->requiredFields(['otp_email' => 'new-owner@example.com']))
            ->assertSessionHasNoErrors();

        $this->assertSame('new-owner@example.com', Config::getValue('otp_email'));
    }

    public function test_an_svg_logo_is_rejected(): void
    {
        Storage::fake('public');

        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.settings.update'), $this->requiredFields(['company_logo' => $svg]))
            ->assertSessionHasErrors('company_logo');

        $this->assertNull(Setting::where('key', 'company_logo')->value('value'));
    }

    public function test_a_png_logo_is_accepted(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.settings.update'), $this->requiredFields([
                'company_logo' => UploadedFile::fake()->image('logo.png'),
            ]))
            ->assertSessionHasNoErrors();

        $path = Setting::where('key', 'company_logo')->value('value');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }
}
