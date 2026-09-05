<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => '24/7 Home Nursing Care',
                'description' => 'Round-the-clock professional nursing care in the comfort of your home. Our experienced nurses provide comprehensive medical care, medication management, wound care, and vital signs monitoring.',
                'icon' => 'fas fa-user-nurse',
                'image' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800&h=600&fit=crop',
                'order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Post-Surgery Care',
                'description' => 'Specialized post-operative care to ensure smooth recovery at home. We assist with wound care, pain management, mobility support, and follow-up care as per doctor\'s instructions.',
                'icon' => 'fas fa-procedures',
                'image' => 'https://images.unsplash.com/photo-1631217868264-e5b90bb7e133?w=800&h=600&fit=crop',
                'order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Elderly Care',
                'description' => 'Compassionate care for senior citizens including assistance with daily activities, medication reminders, companionship, and monitoring of chronic conditions like diabetes and hypertension.',
                'icon' => 'fas fa-walking',
                'image' => 'https://images.unsplash.com/photo-1559757175-5700dde675bc?w=800&h=600&fit=crop',
                'order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Pediatric Nursing',
                'description' => 'Gentle and expert care for children with special medical needs. Our pediatric nurses are trained to handle various conditions and provide family-centered care.',
                'icon' => 'fas fa-baby',
                'image' => 'https://images.unsplash.com/photo-1584515933487-779824d29309?w=800&h=600&fit=crop',
                'order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Physiotherapy at Home',
                'description' => 'Professional physiotherapy services at your doorstep. We help with rehabilitation, pain management, mobility improvement, and recovery from injuries or surgeries.',
                'icon' => 'fas fa-dumbbell',
                'image' => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=800&h=600&fit=crop',
                'order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Medical Equipment Rental',
                'description' => 'Rent high-quality medical equipment including hospital beds, wheelchairs, oxygen concentrators, nebulizers, and more. Affordable rates with delivery and setup included.',
                'icon' => 'fas fa-wheelchair',
                'image' => 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=800&h=600&fit=crop',
                'order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }
    }
}

