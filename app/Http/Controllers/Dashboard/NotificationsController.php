<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Repositories\Notification\NotificationRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    protected $notificationRepository;

    public function __construct(NotificationRepository $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

    public function index()
    {
        $user = Auth::guard('admin')->user();
        $notifications = $this->notificationRepository->getAll($user);

        $counter = 0;

        return view('dashboard.notifications.index', \compact('notifications', 'counter'));
    }


    public function markAsRead(Request $request)
    {
        $user = Auth::guard('admin')->user();

        if ($request->has('notification_id')) {
            $notification = $user->notifications()->find($request->notification_id);
            if ($notification) {
                $notification->markAsRead();
            }
        }

        $url = $request->input('url');

        if ($url) {
            // Extract path and query so redirect always stays on the current domain and port,
            // even if the stored notification URL was absolute (e.g. generated in CLI with http://localhost).
            $parsed = parse_url($url);
            if (!empty($parsed['path'])) {
                $target = $parsed['path'] . (!empty($parsed['query']) ? '?' . $parsed['query'] : '');
                return redirect()->to($target);
            }

            return redirect()->to($url);
        }

        return redirect()->back();
    }
}
