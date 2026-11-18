<?php

namespace App\Repositories\SendNewsToUser;

use App\Helper\Helper;
use App\Models\SendNewsToUser;

use Illuminate\Support\Facades\Storage;

class SendNewsToUserRepository implements SendNewsToUserInterface
{
  use Helper;

  protected $sendNewsToUser;
  public function __construct(SendNewsToUser $sendNewsToUser)
  {
    $this->sendNewsToUser = $sendNewsToUser;
  }

  public function create()
  {
    return $this->sendNewsToUser->all();
  }

  public function paginateSubscribers(?string $search = null, int $perPage = 50)
  {
    return $this->sendNewsToUser
      ->when($search, fn ($query) => $query->where('subscription_email', 'like', '%' . $search . '%'))
      ->orderBy('id')
      ->paginate($perPage);
  }

  public function sendNewsMail($data)
  {
    return SendNewsToUser::whereIn('id', $data['users'])->get();
  }
}
