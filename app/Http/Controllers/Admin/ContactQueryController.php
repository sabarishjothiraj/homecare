<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactQuery;
use Illuminate\Http\Request;

class ContactQueryController extends Controller
{
    /**
     * Display contact query listing
     */
    public function index(Request $request)
    {
        $query = ContactQuery::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $queries = $query->latest()->paginate(20);

        // Count by status
        $newCount = ContactQuery::new()->count();
        $respondedCount = ContactQuery::responded()->count();

        return view('admin.contact-queries.index', compact('queries', 'newCount', 'respondedCount'));
    }

    /**
     * Show query details
     */
    public function show(ContactQuery $contactQuery)
    {
        // Mark as read if new
        if ($contactQuery->status === 'new') {
            $contactQuery->update(['status' => 'read']);
        }

        return view('admin.contact-queries.show', compact('contactQuery'));
    }

    /**
     * Update query status
     */
    public function update(Request $request, ContactQuery $contactQuery)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,read,responded',
        ]);

        $contactQuery->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Query status updated successfully.',
                'reload' => true
            ]);
        }

        return redirect()->back()
            ->with('success', 'Query status updated successfully.');
    }

    /**
     * Mark as responded
     */
    public function respond(Request $request, ContactQuery $contactQuery)
    {
        $validated = $request->validate([
            'response_notes' => 'nullable|string',
        ]);

        $contactQuery->update([
            'status' => 'responded',
            'response_notes' => $validated['response_notes'] ?? null,
            'responded_at' => now(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Query marked as responded.',
                'reload' => true
            ]);
        }

        return redirect()->back()
            ->with('success', 'Query marked as responded.');
    }
}

