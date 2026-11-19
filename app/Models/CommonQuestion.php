<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommonQuestion extends Model
{
  use HasFactory;
  protected $fillable = ['title', 'description', 'title_en', 'description_en'];

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
   * English description when the current locale is English and an
   * English description was filled in, otherwise the Arabic one.
   */
  public function localizedDescription(): ?string
  {
    return app()->getLocale() === 'en' && !empty($this->description_en)
      ? $this->description_en
      : $this->description;
  }
}
