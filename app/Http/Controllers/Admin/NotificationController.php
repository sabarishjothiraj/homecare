<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display notifications
     */
    public function index(Request $request)
    {
        $query = Notification::latest();

        // Filter by read status
        if ($request->filled('status')) {
            if ($request->status === 'unread') {
                $query->unread();
            } elseif ($request->status === 'read') {
                $query->read();
            }
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $notifications = $query->paginate(20);
        $unreadCount = Notification::unread()->count();

        return view('admin.notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * Mark notification as read
     */
    public function markRead(Notification $notification)
    {
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllRead()
    {
        Notification::unread()->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }
}

