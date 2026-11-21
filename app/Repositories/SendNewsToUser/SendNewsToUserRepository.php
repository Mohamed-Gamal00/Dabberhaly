<?php

namespace App\Repositories\SendNewsToUser;

use App\Helper\Helper;
use App\Models\SendNewsToUser;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class SendNewsToUserRepository implements SendNewsToUserInterface
{
  use Helper;

  protected $sendNewsToUser;
  public function __construct(SendNewsToUser $sendNewsToUser)
  {
    $this->sendNewsToUser = $sendNewsToUser;
  }

  /**
   * Subscribers, optionally filtered by email, 50 per page (the
   * admin list is searchable and paginated).
   */
  public function paginatedSubscribers(string $search = ''): LengthAwarePaginator
  {
    return SendNewsToUser::query()
      ->when($search !== '', fn ($query) => $query->where('subscription_email', 'like', '%' . $search . '%'))
      ->orderBy('id')
      ->paginate(50);
  }

  /** Overall subscriber total, even while a search is active. */
  public function totalSubscribers(): int
  {
    return (int) SendNewsToUser::count();
  }

  public function sendNewsMail($data)
  {
    return SendNewsToUser::whereIn('id', $data['users'])->get();
  }
}
