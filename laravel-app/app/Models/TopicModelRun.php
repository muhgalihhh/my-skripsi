<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicModelRun extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'model_type',           // 'bertopic' | 'lda'
    'status',
    'fastapi_training_job_id',
    'fastapi_preprocessing_job_id',
    'remove_stopwords',
    'min_word_length',
    'language',
    'bertopic_params',
    'lda_params',
    'total_documents',
    'num_topics',
    'num_outliers',
    'coherence_cv',
    'topic_diversity',
    'training_duration_seconds',
    'model_path',
    'error_message',
    'started_at',
    'completed_at',
  ];

  protected $casts = [
    'remove_stopwords'          => 'boolean',
    'min_word_length'           => 'integer',
    'total_documents'           => 'integer',
    'bertopic_params'           => 'array',
    'lda_params'                => 'array',
    'num_topics'                => 'integer',
    'num_outliers'              => 'integer',
    'coherence_cv'              => 'float',
    'topic_diversity'           => 'float',
    'training_duration_seconds' => 'float',
    'started_at'                => 'datetime',
    'completed_at'              => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function topics()
  {
    return $this->hasMany(TopicModelTopic::class, 'topic_model_run_id');
  }

  /**
   * Helper: label badge warna berdasarkan status.
   */
  public function getStatusColorAttribute(): string
  {
    return match ($this->status) {
      'completed'    => 'green',
      'training'     => 'blue',
      'preprocessing'=> 'yellow',
      'pending'      => 'gray',
      'failed'       => 'red',
      default        => 'gray',
    };
  }
}
