<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicModelTopic extends Model
{
  use HasFactory;

  protected $fillable = [
    'topic_model_run_id',
    'topic_id',
    'count',
    'top_words',
    'word_scores',
  ];

  protected $casts = [
    'topic_id' => 'integer',
    'count' => 'integer',
    'top_words' => 'array',
    'word_scores' => 'array',
  ];

  public function run()
  {
    return $this->belongsTo(TopicModelRun::class, 'topic_model_run_id');
  }
}
