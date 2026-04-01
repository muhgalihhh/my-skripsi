<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skripsi extends Model
{
  use HasFactory;

  protected $table = 'skripsi';

  protected $fillable = [
    'title',
    'abstract',
    'cleaned_text',
    'processed_text',
    'type',
    'id_code',
    'keywords',
    'subjects',
    'divisions',
    'author',
    'deposit_date',
    'modified_date',
    'uri',
    'year',
    'pdf_documents',
    'url',
    'conclusion',
    'conclusion_source',
  ];

  protected function casts(): array
  {
    return [
      'pdf_documents' => 'array',
      'year' => 'integer',
    ];
  }

  /**
   * Scope to filter by year.
   */
  public function scopeByYear($query, int $year)
  {
    return $query->where('year', $year);
  }

  /**
   * Scope to filter by year range.
   */
  public function scopeByYearRange($query, int $startYear, int $endYear)
  {
    return $query->whereBetween('year', [$startYear, $endYear]);
  }
}
