<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trigger_type',
        'status',
        'fastapi_job_id',
        'total_scraped',
        'new_added',
        'data_updated',
        'duplicates_skipped',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who triggered this scraping.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get latest scraping logs.
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
