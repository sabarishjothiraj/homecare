<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $inProgressCount = ContactQuery::inProgress()->count();
        $resolvedCount = ContactQuery::resolved()->count();

        return view('admin.contact-queries.index', compact('queries', 'newCount', 'inProgressCount', 'resolvedCount'));
    }

    /**
     * Show query details
     */
    public function show(ContactQuery $contactQuery)
    {
        // Opening a new query moves it to "in progress" (the enum has no
        // "read" value, and strict mode rejects anything outside it).
        if ($contactQuery->status === 'new') {
            $contactQuery->update(['status' => 'in_progress']);
        }

        return view('admin.contact-queries.show', compact('contactQuery'));
    }

    /**
     * Update query status
     */
    public function update(Request $request, ContactQuery $contactQuery)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(ContactQuery::STATUSES))],
        ]);

        $contactQuery->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Query status updated successfully.',
                'reload' => true,
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
            'admin_response' => 'nullable|string|max:5000',
        ]);

        // "Responded" is stored as resolved; admin_response is the real
        // column. A blank submission keeps any earlier notes.
        $contactQuery->update([
            'status' => 'resolved',
            'admin_response' => $validated['admin_response'] ?? $contactQuery->admin_response,
            'responded_by' => $request->user()->id,
            'responded_at' => now(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Query marked as responded.',
                'reload' => true,
            ]);
        }

        return redirect()->back()
            ->with('success', 'Query marked as responded.');
    }
}
