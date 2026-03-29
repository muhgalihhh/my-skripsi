<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicModelRun extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'status',
    'fastapi_training_job_id',
    'remove_stopwords',
    'min_word_length',
    'language',
    'bertopic_params',
    'total_documents',
    'total_tokens_before',
    'total_tokens_after_cleaned',
    'preprocessing_preview',
    'num_topics',
    'num_outliers',
    'coherence_cv',
    'topic_diversity',
    'model_path',
    'error_message',
    'started_at',
    'completed_at',
  ];

  protected $casts = [
    'remove_stopwords' => 'boolean',
    'min_word_length' => 'integer',
    'total_documents' => 'integer',
    'total_tokens_before' => 'integer',
    'total_tokens_after_cleaned' => 'integer',
    'bertopic_params' => 'array',
    'preprocessing_preview' => 'array',
    'num_topics' => 'integer',
    'num_outliers' => 'integer',
    'coherence_cv' => 'float',
    'topic_diversity' => 'float',
    'started_at' => 'datetime',
    'completed_at' => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function topics()
  {
    return $this->hasMany(TopicModelTopic::class, 'topic_model_run_id');
  }
}
