<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContactQuery;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        $thanks = 'Thank you for contacting us! We will get back to you soon.';

        // Honeypot field a person never sees. Bots that fill it get the
        // normal reply, so they have no signal to adapt to, but nothing is
        // saved. The name is one autofill does not recognise; each drop is
        // logged (IP only) so a real enquiry lost this way can be spotted.
        // Flooding is rate-limited on the route (throttle:public-forms).
        if ($request->filled('hp_extra')) {
            Log::info('Contact form submission dropped by honeypot.', ['ip' => $request->ip()]);

            return redirect()->back()->with('success', $thanks);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        ContactQuery::create($validated);

        return redirect()->back()->with('success', $thanks);
    }
}
