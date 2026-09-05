<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Service;

class ServiceController extends Controller
{
    /**
     * Display services page
     */
    public function index()
    {
        try {
            $services = Service::active()->ordered()->get();
        } catch (\Exception $e) {
            $services = collect([]);
        }

        return view('web.services', compact('services'));
    }
}

