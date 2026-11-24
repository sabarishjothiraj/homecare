<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

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

        $validated = $this->prepareForSave($validated);
        // A new row needs a description even when the field was not sent.
        $validated['description'] ??= '';
        $validated['is_active'] = $request->has('is_active');

        // Auto-set display order if not provided
        if (! isset($validated['order'])) {
            $validated['order'] = (int) Service::max('order') + 1;
        }

        $service = Service::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service created successfully.',
                'redirect' => route('admin.services.index'),
            ]);
        }

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created successfully.');
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

        // A blank Display Order keeps the current position.
        $validated = $this->prepareForSave($validated);
        $validated['is_active'] = $request->has('is_active');

        $service->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service updated successfully.',
                'redirect' => route('admin.services.index'),
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
        if ($service->isInUse()) {
            $message = 'Cannot delete a service used by existing contracts. Mark it inactive instead.';

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()->back()
                ->with('error', $message);
        }

        $service->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service deleted successfully.',
                'redirect' => route('admin.services.index'),
            ]);
        }

        return redirect()->route('admin.services.index')
            ->with('success', 'Service deleted successfully.');
    }

    /**
     * Map the form fields onto the services columns.
     */
    private function prepareForSave(array $validated): array
    {
        // The form calls it display_order; the column is `order`.
        if (isset($validated['display_order'])) {
            $validated['order'] = $validated['display_order'];
        }
        unset($validated['display_order']);

        // description is NOT NULL, and an empty textarea arrives as null. An
        // omitted field stays absent so an update leaves the stored text alone.
        if (array_key_exists('description', $validated)) {
            $validated['description'] ??= '';
        }

        return $validated;
    }
}
