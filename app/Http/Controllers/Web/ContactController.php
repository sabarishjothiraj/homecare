<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContactQuery;
use App\Models\Setting;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display contact page
     */
    public function index()
    {
        try {
            $companyEmail = Setting::get('company_email', '');
            $companyPhone = Setting::get('company_phone', '');
            $companyAddress = Setting::get('company_address', '');
        } catch (\Exception $e) {
            $companyEmail = '';
            $companyPhone = '';
            $companyAddress = '';
        }

        return view('web.contact', compact(
            'companyEmail',
            'companyPhone',
            'companyAddress'
        ));
    }

    /**
     * Store contact query
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        ContactQuery::create($validated);

        return redirect()->back()
            ->with('success', 'Thank you for contacting us! We will get back to you soon.');
    }
}

