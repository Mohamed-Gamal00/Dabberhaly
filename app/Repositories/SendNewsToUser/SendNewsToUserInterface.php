<?php

namespace App\Repositories\SendNewsToUser;

use Illuminate\Pagination\LengthAwarePaginator;

interface SendNewsToUserInterface
{
  public function paginatedSubscribers(string $search = ''): LengthAwarePaginator;
  public function totalSubscribers(): int;
  public function sendNewsMail($params);
}
