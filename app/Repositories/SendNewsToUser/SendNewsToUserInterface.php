<?php

namespace App\Repositories\SendNewsToUser;

interface SendNewsToUserInterface
{
  public function create();
  public function sendNewsMail($params);

  /**
   * Subscribers paginated (never load them all): 50 per page,
   * optionally filtered by email search.
   */
  public function paginateSubscribers(?string $search = null, int $perPage = 50);
}
