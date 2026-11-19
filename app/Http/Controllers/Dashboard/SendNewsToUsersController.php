<?php

namespace App\Http\Controllers\Dashboard;

use App\Events\NewsletterRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Notifications\SendNewsToUserRequest;
use App\Models\SendNewsToUser;
use App\Repositories\SendNewsToUser\SendNewsToUserRepository;
use Illuminate\Support\Facades\Gate;

class SendNewsToUsersController extends Controller
{
    public $SendNewsToUser;

    public function __construct(SendNewsToUserRepository $repo)
    {
        $this->SendNewsToUser = $repo;
    }

    public function create()
    {
        Gate::authorize('news.view');
        $users = $this->SendNewsToUser->create();

        return view('dashboard.send_news_to_users.create', compact('users'));
    }

    public function sendNewsMail(SendNewsToUserRequest $request)
    {
        Gate::authorize('news.view');

        $userIds = (array) $request->input('users', []);
        $title   = $request->input('title');
        $body    = $request->input('body');

        // Dispatch one event per subscriber (chunked, so a large list
        // never loads fully into memory). Nothing is sent here:
        // SendNewsletterMail is ShouldQueue, so dispatching the event
        // only pushes a job to the `jobs` table and returns immediately.
        // The queue worker runs the listener in the background, retries
        // failures with backoff, and records exhausted jobs in
        // `failed_jobs`. The old synchronous loop + usleep(1.1s) per
        // mail — which timed out the admin request on large lists —
        // is intentionally gone.
        SendNewsToUser::whereIn('id', $userIds)
            ->orderBy('id')
            ->chunk(200, function ($users) use ($title, $body) {
                foreach ($users as $user) {
                    event(new NewsletterRequested($user, $title, $body));
                }
            });

        // The old message claimed the newsletter had already been sent,
        // but dispatching only queues the mails — nothing has been sent
        // yet at this point. Say honestly that the request was received.
        return redirect()->back()->with('success', 'تم استلام طلب إرسال النشرة وسيتم إرسالها للمشتركين');
    }
}
