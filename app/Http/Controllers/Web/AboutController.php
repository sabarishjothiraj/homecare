<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class AboutController extends Controller
{
    /**
     * Display about page
     */
    public function index()
    {
        try {
            $companyName = Setting::get('company_name', 'Home Nursing Care');
            $aboutContent = Setting::get('about_content', '');
            $companyEmail = Setting::get('company_email', '');
            $companyPhone = Setting::get('company_phone', '');
            $companyAddress = Setting::get('company_address', '');
        } catch (\Exception $e) {
            $companyName = 'Home Nursing Care';
            $aboutContent = '';
            $companyEmail = '';
            $companyPhone = '';
            $companyAddress = '';
        }

        return view('web.about', compact(
            'companyName',
            'aboutContent',
            'companyEmail',
            'companyPhone',
            'companyAddress'
        ));
    }
}

