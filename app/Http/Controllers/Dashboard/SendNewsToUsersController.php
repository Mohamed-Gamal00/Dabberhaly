<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Notifications\SendNewsToUserRequest;
use App\Notifications\NewsletterNotification;
use App\Models\SendNewsToUser;
use App\Repositories\SendNewsToUser\SendNewsToUserRepository;

class SendNewsToUsersController extends Controller
{
    public $SendNewsToUser;

    public function __construct(SendNewsToUserRepository $repo)
    {
        $this->SendNewsToUser = $repo;
    }

    public function create()
    {
        //Gate::authorize('news.view');
        $users = $this->SendNewsToUser->create();

        return view('dashboard.send_news_to_users.create', compact('users'));
    }

    public function sendNewsMail(SendNewsToUserRequest $request)
    {
        $userIds = (array) $request->input('users', []);
        $title   = $request->input('title');
        $body    = $request->input('body');
        $queued  = 0;

        // Queue one notification job per subscriber (chunked, so a large
        // list never loads fully into memory). Nothing is sent here:
        // NewsletterNotification implements ShouldQueue, so notify()
        // only pushes a job to the `jobs` table and returns immediately.
        // The queue worker sends the mails in the background, retries
        // failures with backoff, and records exhausted jobs in
        // `failed_jobs`. The old synchronous loop + usleep(1.1s) per
        // mail — which timed out the admin request on large lists —
        // is intentionally gone.
        SendNewsToUser::whereIn('id', $userIds)
            ->orderBy('id')
            ->chunk(200, function ($users) use ($title, $body, &$queued) {
                foreach ($users as $user) {
                    $user->notify(new NewsletterNotification($title, $body, $user));
                    $queued++;
                }
            });

        return redirect()->back()->with(
            'success',
            "تم استلام النشرة، وستُرسل في الخلفية إلى {$queued} مشترك. تأكد أن الـ queue worker شغال: php artisan queue:work"
        );
    }
}
