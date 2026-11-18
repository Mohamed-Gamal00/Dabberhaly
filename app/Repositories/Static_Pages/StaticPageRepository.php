<?php

namespace App\Repositories\Static_Pages;

use App\Helper\Helper;
use App\Models\Page;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\Storage;

class StaticPageRepository implements StaticPageInterface
{
  use Helper;

  protected $page;

  public function __construct(Page $page)
  {
    $this->page = $page;
  }

  public function getMainPages()
  {
    return $this->page->all();
  }


  public function update($data, $id)
  {
    $page = $this->page->findOrFail($id);

    // Purify stored HTML: the content is rendered raw (v-html on the
    // public page, {!! !!} in the admin editor), so anything dangerous
    // saved here would execute for every visitor.
    if (isset($data['content'])) {
      $data['content'] = HtmlSanitizer::purify($data['content']);
    }

    $page->update($data);
    return $page->wasChanged();
  }

}
