<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return auth()->user()->notifications()->latest()->take(10)->get();
    }

    public function markAsRead(Request $request)
    {
        auth()->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }
    
    public function unreadCount()
    {
        return response()->json(['count' => auth()->user()->unreadNotifications->count()]);
    }
}
