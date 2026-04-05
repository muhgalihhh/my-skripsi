<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicModelSetting extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'bertopic_params',
  ];

  protected $casts = [
    'bertopic_params' => 'array',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
