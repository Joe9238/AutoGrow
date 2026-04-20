<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class NotificationController extends Controller
{
    // ----------------------------
    // List unread notifications for authenticated user
    // ----------------------------
    public function list(Request $request)
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->where('read', false)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'body', 'created_at']);

        // the timestamps are returned in the databases default format so recreate the list with time formatted as d-m-Y H:i
        $formatted = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'created_at' => $notification->created_at ? $notification->created_at->format('d-m-Y H:i') : null,
            ];
        });
        return response()->json(['notifications' => $formatted]);
    }

    // ----------------------------
    // Delete notification by ID
    // ----------------------------
    public function delete($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::id()) // ensures users can only delete their own notifications
            ->firstOrFail();
        $notification->delete();
        return response()->json(['success' => true]);
    }
}
