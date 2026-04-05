<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicModelTopicDocument extends Model
{
  use HasFactory;

  protected $fillable = [
    'topic_model_run_id',
    'topic_model_topic_id',
    'topic_id',
    'skripsi_id',
  ];

  protected $casts = [
    'topic_model_run_id' => 'integer',
    'topic_model_topic_id' => 'integer',
    'topic_id' => 'integer',
    'skripsi_id' => 'integer',
  ];

  public function run()
  {
    return $this->belongsTo(TopicModelRun::class, 'topic_model_run_id');
  }

  public function topic()
  {
    return $this->belongsTo(TopicModelTopic::class, 'topic_model_topic_id');
  }

  public function skripsi()
  {
    return $this->belongsTo(Skripsi::class, 'skripsi_id');
  }
}
