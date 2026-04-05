<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicModelDataset extends Model
{
  use HasFactory;

  protected $fillable = [
    'skripsi_id',
    'title',
    'abstract',
    'conclusion',
    'year',
    'cleaned_text',
    'processed_text',
  ];

  protected $casts = [
    'skripsi_id' => 'integer',
    'year' => 'integer',
  ];

  public function skripsi()
  {
    return $this->belongsTo(Skripsi::class, 'skripsi_id');
  }
}
