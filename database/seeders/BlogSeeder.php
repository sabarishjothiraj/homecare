<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // The super admin from UserSeeder authors the sample posts. Don't
        // invent an account with a known password if it isn't there.
        $admin = User::where('role', 'super_admin')->orderBy('id')->first();

        if (! $admin) {
            $this->command?->warn('BlogSeeder skipped: no super admin user. Run UserSeeder first.');

            return;
        }

        $blogs = [
            [
                'title' => '10 Essential Tips for Post-Surgery Home Care',
                'slug' => '10-essential-tips-for-post-surgery-home-care',
                'excerpt' => 'Recovering from surgery at home requires proper care and attention. Learn the essential tips to ensure a smooth and safe recovery process.',
                'content' => '<p>Recovering from surgery can be challenging, but with the right care at home, you can ensure a smooth healing process. Here are 10 essential tips:</p><h3>1. Follow Your Doctor\'s Instructions</h3><p>Always adhere to the post-operative care instructions provided by your surgeon. This includes medication schedules, wound care, and activity restrictions.</p><h3>2. Keep the Surgical Site Clean</h3><p>Proper wound care is crucial to prevent infections. Clean the area as directed and watch for signs of infection like redness, swelling, or discharge.</p><h3>3. Manage Pain Effectively</h3><p>Take prescribed pain medications as directed. Don\'t wait until pain becomes severe before taking medication.</p><h3>4. Get Adequate Rest</h3><p>Your body needs energy to heal. Ensure you get plenty of sleep and avoid strenuous activities.</p><h3>5. Stay Hydrated and Eat Well</h3><p>Proper nutrition supports healing. Drink plenty of water and eat a balanced diet rich in proteins and vitamins.</p><p>For professional post-surgery care at home, contact our experienced nursing team today.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1631217868264-e5b90bb7e133?w=1200&h=800&fit=crop',
                'author_id' => $admin->id,
                'status' => 'published',
                'published_at' => now()->subDays(5),
                'views' => 245,
            ],
            [
                'title' => 'Understanding Elderly Care: A Comprehensive Guide',
                'slug' => 'understanding-elderly-care-comprehensive-guide',
                'excerpt' => 'As our loved ones age, they may need additional support. This guide covers everything you need to know about providing quality elderly care at home.',
                'content' => '<p>Caring for elderly family members is both a privilege and a responsibility. Here\'s what you need to know:</p><h3>The Importance of Elderly Care</h3><p>As people age, they may face challenges with mobility, memory, and daily activities. Professional elderly care ensures they maintain dignity and quality of life.</p><h3>Types of Elderly Care Services</h3><ul><li>Personal care assistance (bathing, dressing, grooming)</li><li>Medication management</li><li>Companionship and emotional support</li><li>Meal preparation and nutrition monitoring</li><li>Light housekeeping</li></ul><h3>Benefits of Home-Based Elderly Care</h3><p>Seniors often prefer to age in place, surrounded by familiar surroundings and memories. Home care allows them to maintain independence while receiving necessary support.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1559757175-5700dde675bc?w=1200&h=800&fit=crop',
                'author_id' => $admin->id,
                'status' => 'published',
                'published_at' => now()->subDays(12),
                'views' => 189,
            ],
            [
                'title' => 'The Benefits of Pediatric Home Nursing',
                'slug' => 'benefits-of-pediatric-home-nursing',
                'excerpt' => 'Children with special medical needs deserve the best care. Discover how pediatric home nursing can make a difference for your family.',
                'content' => '<p>Pediatric home nursing provides specialized care for children with medical needs in the comfort of their own home.</p><h3>Why Choose Pediatric Home Nursing?</h3><ul><li>Familiar environment reduces stress for children</li><li>Family-centered care approach</li><li>Specialized pediatric nurses</li><li>Flexible scheduling around family routines</li><li>One-on-one attention</li></ul><h3>Conditions We Treat</h3><p>Our pediatric nurses are experienced in caring for children with various conditions including respiratory issues, feeding tubes, post-surgical care, and chronic illnesses.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1584515933487-779824d29309?w=1200&h=800&fit=crop',
                'author_id' => $admin->id,
                'status' => 'published',
                'published_at' => now()->subDays(18),
                'views' => 156,
            ],
            [
                'title' => 'Physiotherapy at Home: What to Expect',
                'slug' => 'physiotherapy-at-home-what-to-expect',
                'excerpt' => 'Home-based physiotherapy offers convenience and personalized care. Learn what to expect from your first session.',
                'content' => '<p>Physiotherapy at home brings professional rehabilitation services to your doorstep.</p><h3>Benefits of Home Physiotherapy</h3><ul><li>No travel required</li><li>Personalized treatment plans</li><li>Comfortable environment</li><li>Family involvement</li><li>Flexible scheduling</li></ul><h3>What We Treat</h3><p>Our physiotherapists help with post-surgery rehabilitation, sports injuries, chronic pain, mobility issues, and stroke recovery.</p><h3>Your First Session</h3><p>During your first visit, the physiotherapist will assess your condition, discuss your goals, and create a customized treatment plan.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=1200&h=800&fit=crop',
                'author_id' => $admin->id,
                'status' => 'published',
                'published_at' => now()->subDays(25),
                'views' => 203,
            ],
            [
                'title' => 'Managing Chronic Diseases at Home',
                'slug' => 'managing-chronic-diseases-at-home',
                'excerpt' => 'Living with a chronic disease requires ongoing care and monitoring. Learn how home nursing can help manage your condition effectively.',
                'content' => '<p>Chronic diseases like diabetes, hypertension, and heart disease require consistent monitoring and care.</p><h3>How Home Nursing Helps</h3><ul><li>Regular vital signs monitoring</li><li>Medication management</li><li>Diet and lifestyle counseling</li><li>Early detection of complications</li><li>Coordination with doctors</li></ul><h3>Common Chronic Conditions We Manage</h3><p>Our nurses are experienced in managing diabetes, hypertension, COPD, heart disease, kidney disease, and arthritis.</p><h3>The Importance of Consistency</h3><p>Regular monitoring and adherence to treatment plans are crucial for managing chronic diseases effectively.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=1200&h=800&fit=crop',
                'author_id' => $admin->id,
                'status' => 'published',
                'published_at' => now()->subDays(30),
                'views' => 178,
            ],
        ];

        // Keyed on the unique slug so a re-run is harmless. withTrashed: a post
        // the office deleted still holds its slug and should stay deleted.
        foreach ($blogs as $blog) {
            Blog::withTrashed()->firstOrCreate(['slug' => $blog['slug']], $blog);
        }
    }
}
