<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    /**
     * Display service listing
     */
    public function index()
    {
        $services = Service::withCount('contracts')->latest()->paginate(20);

        return view('admin.services.index', compact('services'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('admin.services.create');
    }

    /**
     * Store new service
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->has('is_active');

        // Auto-set display order if not provided
        if (!isset($validated['display_order'])) {
            $validated['display_order'] = Service::max('display_order') + 1;
        }

        $service = Service::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service created successfully.',
                'redirect' => route('admin.services.index')
            ]);
        }

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created successfully.');
    }

    /**
     * Show service details
     */
    public function show(Service $service)
    {
        $service->loadCount('contracts');

        return view('admin.services.show', compact('service'));
    }

    /**
     * Show edit form
     */
    public function edit(Service $service)
    {
        return view('admin.services.edit', compact('service'));
    }

    /**
     * Update service
     */
    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->has('is_active');

        $service->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service updated successfully.',
                'redirect' => route('admin.services.index')
            ]);
        }

        return redirect()->route('admin.services.index')
            ->with('success', 'Service updated successfully.');
    }

    /**
     * Delete service
     */
    public function destroy(Service $service)
    {
        // Check if service has contracts
        if ($service->contracts()->exists()) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete service with existing contracts.'
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Cannot delete service with existing contracts.');
        }

        $service->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service deleted successfully.',
                'redirect' => route('admin.services.index')
            ]);
        }

        return redirect()->route('admin.services.index')
            ->with('success', 'Service deleted successfully.');
    }
}

