<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Banner;
use App\Models\Blog;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    /**
     * A blank Display Order used to query a non-existent display_order column,
     * and a blank description hit the NOT NULL column.
     */
    public function test_service_with_blank_order_and_description_is_created_next_in_order(): void
    {
        Service::factory()->create(['order' => 4]);

        $this->actingAs($this->admin)->post('/admin/services', [
            'name' => 'Wound Care',
            'is_active' => '1',
        ])->assertRedirect(route('admin.services.index'));

        $service = Service::where('name', 'Wound Care')->firstOrFail();
        $this->assertSame(5, (int) $service->order);
        $this->assertSame('', $service->description);
    }

    public function test_service_display_order_is_saved_and_can_be_changed(): void
    {
        $this->actingAs($this->admin)->post('/admin/services', [
            'name' => 'Physiotherapy',
            'description' => 'At-home sessions',
            'display_order' => 7,
        ])->assertSessionHasNoErrors();

        $service = Service::where('name', 'Physiotherapy')->firstOrFail();
        $this->assertSame(7, (int) $service->order);

        $this->actingAs($this->admin)->put("/admin/services/{$service->id}", [
            'name' => 'Physiotherapy',
            'description' => '',
            'display_order' => 2,
        ])->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame(2, (int) $service->order);
        $this->assertSame('', $service->description);

        // Leaving it blank on edit keeps the current position.
        $this->actingAs($this->admin)->put("/admin/services/{$service->id}", [
            'name' => 'Physiotherapy',
            'description' => 'At-home sessions',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, (int) $service->fresh()->order);

        // A request that omits description must not wipe the stored text.
        $this->actingAs($this->admin)->put("/admin/services/{$service->id}", [
            'name' => 'Physiotherapy',
        ])->assertSessionHasNoErrors();

        $this->assertSame('At-home sessions', $service->fresh()->description);
    }

    public function test_service_used_only_on_a_secondary_contract_line_cannot_be_deleted(): void
    {
        $primary = Service::factory()->create();
        $secondary = Service::factory()->create();
        $contract = Contract::factory()->create(['service_id' => $primary->id]);
        ContractItem::factory()->create(['contract_id' => $contract->id, 'service_id' => $primary->id]);
        ContractItem::factory()->create(['contract_id' => $contract->id, 'service_id' => $secondary->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/admin/services/{$secondary->id}")
            ->assertStatus(422);

        $this->assertNotSoftDeleted($secondary);
    }

    public function test_service_referenced_only_by_an_assignment_cannot_be_deleted(): void
    {
        $service = Service::factory()->create();
        Assignment::factory()->create(['service_id' => $service->id]);

        $this->actingAs($this->admin)
            ->delete("/admin/services/{$service->id}")
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($service);
    }

    public function test_unused_service_can_be_deleted(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->admin)
            ->delete("/admin/services/{$service->id}")
            ->assertRedirect(route('admin.services.index'));

        $this->assertSoftDeleted($service);
    }

    public function test_blog_featured_image_rejects_svg(): void
    {
        Storage::fake('public');
        $svg = UploadedFile::fake()->createWithContent(
            'art.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->actingAs($this->admin)->post('/admin/blogs', [
            'title' => 'Banner art',
            'content' => 'Body',
            'status' => 'draft',
            'featured_image' => $svg,
        ])->assertSessionHasErrors('featured_image');

        $this->assertSame(0, Blog::count());
        $this->assertEmpty(Storage::disk('public')->allFiles('blog_images'));
    }

    public function test_blog_featured_image_accepts_raster_images(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post('/admin/blogs', [
            'title' => 'Photo post',
            'content' => 'Body',
            'status' => 'draft',
            'featured_image' => UploadedFile::fake()->image('photo.png'),
        ])->assertSessionHasNoErrors();

        $blog = Blog::firstOrFail();
        Storage::disk('public')->assertExists($blog->featured_image);
    }

    public function test_blog_slugs_are_unique_including_soft_deleted_posts(): void
    {
        $deleted = Blog::create(['title' => 'Elder Care Tips', 'content' => 'x', 'author_id' => $this->admin->id]);
        $deleted->delete();

        foreach ([1, 2] as $i) {
            $this->actingAs($this->admin)->post('/admin/blogs', [
                'title' => 'Elder Care Tips',
                'content' => "Body {$i}",
                'status' => 'published',
            ])->assertSessionHasNoErrors();
        }

        $this->assertEqualsCanonicalizing(
            ['elder-care-tips', 'elder-care-tips-2', 'elder-care-tips-3'],
            Blog::withTrashed()->pluck('slug')->all()
        );
    }

    /**
     * Str::slug() returns '' for Tamil, which made such posts unreachable.
     */
    public function test_non_latin_titles_get_a_reachable_slug(): void
    {
        foreach (['முதியோர் பராமரிப்பு', 'தமிழ் செய்திகள்'] as $title) {
            $this->actingAs($this->admin)->post('/admin/blogs', [
                'title' => $title,
                'content' => 'Body',
                'status' => 'published',
            ])->assertSessionHasNoErrors();
        }

        $slugs = Blog::pluck('slug');
        $this->assertCount(2, $slugs->filter()->unique());

        $blog = Blog::where('title', 'முதியோர் பராமரிப்பு')->firstOrFail();
        $this->get(route('blogs.show', $blog->slug))->assertOk()->assertSee('முதியோர் பராமரிப்பு');
    }

    public function test_blog_meta_description_falls_back_to_decoded_content(): void
    {
        Blog::create([
            'title' => 'Care Guide',
            'slug' => 'care-guide',
            'content' => '<p>Tom&nbsp;&amp;&nbsp;Jerry&#39;s care</p>',
            'author_id' => $this->admin->id,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        // Escaped once: no "&amp;nbsp;" in the tag.
        $this->get('/blogs/care-guide')
            ->assertOk()
            ->assertSee("<meta name=\"description\" content=\"Tom\u{00A0}&amp;\u{00A0}Jerry&#039;s care\">", false);
    }

    public function test_migration_backfills_existing_empty_slugs(): void
    {
        $blog = Blog::create(['title' => 'தமிழ் செய்திகள்', 'content' => 'x', 'author_id' => $this->admin->id]);
        DB::table('blogs')->where('id', $blog->id)->update(['slug' => '']);

        (require database_path('migrations/2026_09_23_000801_backfill_empty_blog_slugs.php'))->up();

        $this->assertSame('post-'.$blog->id, $blog->fresh()->slug);
    }

    public function test_blog_update_keeps_slug_and_resolves_title_collisions(): void
    {
        $first = Blog::create(['title' => 'Post Surgery Care', 'content' => 'x', 'author_id' => $this->admin->id]);
        $second = Blog::create(['title' => 'Diabetes At Home', 'content' => 'x', 'author_id' => $this->admin->id]);

        // Unchanged title: the public URL stays the same.
        $this->actingAs($this->admin)->put("/admin/blogs/{$first->id}", [
            'title' => 'Post Surgery Care',
            'content' => 'Edited',
            'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $this->assertSame('post-surgery-care', $first->fresh()->slug);

        // Renaming to another post's title used to hit the unique index.
        $this->actingAs($this->admin)->put("/admin/blogs/{$second->id}", [
            'title' => 'Post Surgery Care',
            'content' => 'x',
            'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $this->assertSame('post-surgery-care-2', $second->fresh()->slug);
    }

    public function test_blog_image_swap_removes_old_file_only_after_saving(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blog_images/old.png', 'old');
        $blog = Blog::create([
            'title' => 'Care Guide',
            'content' => 'x',
            'author_id' => $this->admin->id,
            'featured_image' => 'blog_images/old.png',
        ]);

        $this->actingAs($this->admin)->put("/admin/blogs/{$blog->id}", [
            'title' => 'Care Guide',
            'content' => 'x',
            'status' => 'draft',
            'featured_image' => UploadedFile::fake()->image('new.jpg'),
        ])->assertSessionHasNoErrors();

        $blog->refresh();
        $this->assertNotSame('blog_images/old.png', $blog->featured_image);
        Storage::disk('public')->assertExists($blog->featured_image);
        Storage::disk('public')->assertMissing('blog_images/old.png');
    }

    public function test_banner_accepts_site_paths_and_absolute_urls(): void
    {
        $banner = $this->banner();

        foreach (['/services', '/', 'https://example.com/offer?x=1'] as $link) {
            $this->actingAs($this->admin)
                ->put("/admin/banners/{$banner->id}", $this->bannerPayload($link))
                ->assertSessionHasNoErrors();

            $this->assertSame($link, $banner->fresh()->link);
        }
    }

    public function test_banner_rejects_script_and_protocol_relative_links(): void
    {
        $banner = $this->banner();

        foreach (['javascript:alert(1)', 'JavaScript:alert(1)', 'data:text/html,x', '//evil.com', '/\\evil.com', 'services'] as $link) {
            $this->actingAs($this->admin)
                ->put("/admin/banners/{$banner->id}", $this->bannerPayload($link))
                ->assertSessionHasErrors('link');
        }

        $this->assertSame('/services', $banner->fresh()->link);
    }

    public function test_cms_admin_and_public_pages_render(): void
    {
        $service = Service::factory()->create(['order' => 3, 'name' => 'Night Nursing']);
        $blog = Blog::create(['title' => 'Care Guide', 'content' => 'x', 'author_id' => $this->admin->id]);
        $banner = $this->banner();

        foreach ([
            '/admin/services', '/admin/services/create', "/admin/services/{$service->id}/edit",
            '/admin/blogs', '/admin/blogs/create', "/admin/blogs/{$blog->id}/edit",
            '/admin/banners', '/admin/banners/create', "/admin/banners/{$banner->id}/edit",
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->get('/admin/services/'.$service->id.'/edit')->assertSee('value="3"', false);
        $this->get('/services')->assertOk()->assertSee('Night Nursing');
        $this->get('/')->assertOk();
    }

    public function test_removed_show_and_welcome_routes_are_gone(): void
    {
        $this->assertFalse(Route::has('admin.blogs.show'));
        $this->assertFalse(Route::has('admin.services.show'));
        $this->get('/welcome')->assertNotFound();
    }

    private function banner(): Banner
    {
        return Banner::create([
            'title' => 'Professional Home Nursing Care',
            'image' => 'https://images.example.com/banner.jpg',
            'link' => '/services',
            'button_text' => 'Explore',
            'order' => 1,
            'is_active' => true,
        ]);
    }

    private function bannerPayload(string $link): array
    {
        return [
            'title' => 'Updated title',
            'link' => $link,
            'button_text' => 'Explore',
            'order' => 1,
            'is_active' => '1',
        ];
    }
}
