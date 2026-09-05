<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Blog;
use App\Models\Setting;
use App\Models\Banner;

class HomeController extends Controller
{
    /**
     * Display home page
     */
    public function index()
    {
        $companyName = Setting::get('company_name', 'Home Nursing Care');
        $companyDescription = Setting::get('company_description', '');
        $companyLogo = Setting::get('company_logo', '');
        $homeIntroTitle = Setting::get('home_intro_title', 'Quality Care at Your Doorstep');
        $homeIntroContent = Setting::get('home_intro_content', '');

        // Get active banners
        $banners = Banner::active()->ordered()->get();

        // Get featured services (first 3 active services)
        $services = Service::active()->ordered()->take(3)->get();

        // Get recent blogs (first 3 published)
        $recentBlogs = Blog::published()->latest('published_at')->take(3)->get();

        return view('web.home', compact(
            'companyName',
            'companyDescription',
            'companyLogo',
            'homeIntroTitle',
            'homeIntroContent',
            'banners',
            'services',
            'recentBlogs'
        ));
    }
}

