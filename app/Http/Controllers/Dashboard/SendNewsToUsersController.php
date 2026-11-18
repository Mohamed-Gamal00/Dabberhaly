<?php

namespace App\Http\Controllers\Dashboard;

use App\Events\NewsletterRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Notifications\SendNewsToUserRequest;
use App\Models\SendNewsToUser;
use App\Repositories\SendNewsToUser\SendNewsToUserRepository;
use Illuminate\Http\Request;

class SendNewsToUsersController extends Controller
{
    public $SendNewsToUser;

    public function __construct(SendNewsToUserRepository $repo)
    {
        $this->SendNewsToUser = $repo;
    }

    public function create(Request $request)
    {
        //Gate::authorize('news.view');
        // Paginated on purpose: loading every subscriber at once
        // (SendNewsToUser::all()) breaks the page with tens of
        // thousands of subscribers. 50 per page + email search.
        $search           = $request->query('search');
        $users            = $this->SendNewsToUser->paginateSubscribers($search);
        $totalSubscribers = SendNewsToUser::count();

        return view('dashboard.send_news_to_users.create', compact('users', 'search', 'totalSubscribers'));
    }

    public function sendNewsMail(SendNewsToUserRequest $request)
    {
        $title = $request->input('title');
        $body  = $request->input('body');

        // Dispatch one event per subscriber (chunked, so a large list
        // never loads fully into memory). Nothing is sent here:
        // SendNewsletterMail is ShouldQueue, so dispatching the event
        // only pushes a job to the `jobs` table and returns immediately.
        // The queue worker runs the listener in the background, retries
        // failures with backoff, and records exhausted jobs in
        // `failed_jobs`. The old synchronous loop + usleep(1.1s) per
        // mail — which timed out the admin request on large lists —
        // is intentionally gone.
        //
        // With send_to_all the query streams over every subscriber in
        // chunks — no ID list is ever built or posted, so 50k
        // subscribers cost the request nothing extra.
        $query = SendNewsToUser::query()->orderBy('id');

        if (! $request->boolean('send_to_all')) {
            $query->whereIn('id', (array) $request->input('users', []));
        }

        $query->chunk(200, function ($users) use ($title, $body) {
            foreach ($users as $user) {
                event(new NewsletterRequested($user, $title, $body));
            }
        });

        return redirect()->back()->with('success', 'تم إرسال النشرة بنجاح');
    }
}
