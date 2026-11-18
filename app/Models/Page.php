<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;
  protected $fillable = ['title', 'content', 'title_en', 'content_en'];

  /**
   * English title when the current locale is English and an English
   * title was filled in, otherwise the Arabic title.
   */
  public function localizedTitle(): string
  {
    return app()->getLocale() === 'en' && !empty($this->title_en)
      ? $this->title_en
      : $this->title;
  }

  /**
   * English content when the current locale is English and English
   * content was filled in, otherwise the Arabic content.
   */
  public function localizedContent(): ?string
  {
    return app()->getLocale() === 'en' && !empty($this->content_en)
      ? $this->content_en
      : $this->content;
  }

}
