<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banners = [
            [
                'title' => 'Professional Home Nursing Care',
                'subtitle' => 'Compassionate healthcare services delivered to your doorstep 24/7',
                'image' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=1920&h=600&fit=crop',
                'link' => '/services',
                'button_text' => 'Explore Our Services',
                'order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Expert Care for Your Loved Ones',
                'subtitle' => 'Certified nurses providing personalized care plans for elderly and post-surgery patients',
                'image' => 'https://images.unsplash.com/photo-1559757175-5700dde675bc?w=1920&h=600&fit=crop',
                'link' => '/about',
                'button_text' => 'Learn More',
                'order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Join Our Team of Healthcare Heroes',
                'subtitle' => 'We\'re hiring experienced nurses and caregivers. Competitive pay and flexible schedules.',
                'image' => 'https://images.unsplash.com/photo-1584515933487-779824d29309?w=1920&h=600&fit=crop',
                'link' => '/join',
                'button_text' => 'Apply Now',
                'order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Specialized Pediatric Care',
                'subtitle' => 'Gentle, expert care for children with special medical needs at home',
                'image' => 'https://images.unsplash.com/photo-1631217868264-e5b90bb7e133?w=1920&h=600&fit=crop',
                'link' => '/services',
                'button_text' => 'View Services',
                'order' => 4,
                'is_active' => true,
            ],
            [
                'title' => 'Affordable Quality Healthcare',
                'subtitle' => 'Premium nursing services at competitive rates. Insurance accepted.',
                'image' => 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=1920&h=600&fit=crop',
                'link' => '/contact',
                'button_text' => 'Get a Quote',
                'order' => 5,
                'is_active' => true,
            ],
        ];

        // firstOrCreate so a re-run neither duplicates nor overwrites edits.
        foreach ($banners as $banner) {
            Banner::firstOrCreate(['title' => $banner['title']], $banner);
        }
    }
}
