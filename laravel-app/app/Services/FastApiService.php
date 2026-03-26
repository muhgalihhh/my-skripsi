<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FastApiService
{
  protected string $baseUrl;

  public function __construct()
  {
    $this->baseUrl = rtrim(config('services.fastapi.base_url', 'http://localhost:8000'), '/');
  }

  /**
   * Check health of FastAPI service.
   */
  public function healthCheck(): array
  {
    try {
      $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/health");

      if ($response->successful()) {
        return $response->json();
      }

      return ['status' => 'error', 'message' => 'FastAPI responded with: ' . $response->status()];
    } catch (\Exception $e) {
      Log::error('FastAPI health check failed: ' . $e->getMessage());
      return ['status' => 'error', 'message' => 'Tidak dapat terhubung ke FastAPI service.'];
    }
  }

  /**
   * Start scraping job (async - returns immediately with job_id).
   */
  public function startScrapingJob(int $startYear = 2019, int $endYear = 2026, ?int $maxPages = null): array
  {
    try {
      $payload = [
        'start_year' => $startYear,
        'end_year' => $endYear,
      ];

      if ($maxPages !== null) {
        $payload['max_pages'] = $maxPages;
      }

      $response = Http::timeout(15)
        ->post("{$this->baseUrl}/api/v1/scraping/start", $payload);

      if ($response->successful()) {
        return $response->json();
      }

      // Handle 409 Conflict (job already running)
      if ($response->status() === 409) {
        $body = $response->json();
        return [
          'status' => 'conflict',
          'message' => $body['detail']['message'] ?? 'Sudah ada scraping yang berjalan.',
          'active_job' => $body['detail']['active_job'] ?? null,
        ];
      }

      Log::error('FastAPI start scraping failed', [
        'status' => $response->status(),
        'body' => $response->body(),
      ]);

      return [
        'status' => 'error',
        'message' => 'Gagal memulai scraping. Status: ' . $response->status(),
      ];
    } catch (\Exception $e) {
      Log::error('FastAPI start scraping request failed: ' . $e->getMessage());
      return [
        'status' => 'error',
        'message' => 'Tidak dapat terhubung ke FastAPI service: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Poll scraping job progress.
   */
  public function getJobStatus(string $jobId, bool $includeData = false, bool $includeMonitoring = false): array
  {
    try {
      $params = [];
      if ($includeData)
        $params['include_data'] = 'true';
      if ($includeMonitoring)
        $params['include_monitoring'] = 'true';
      $query = $params ? '?' . http_build_query($params) : '';
      $response = Http::timeout(10)
        ->get("{$this->baseUrl}/api/v1/scraping/jobs/{$jobId}{$query}");

      if ($response->successful()) {
        return $response->json();
      }

      if ($response->status() === 404) {
        return ['status' => 'not_found', 'message' => "Job {$jobId} tidak ditemukan"];
      }

      return ['status' => 'error', 'message' => 'Gagal mendapatkan status job'];
    } catch (\Exception $e) {
      Log::warning('FastAPI job status check failed: ' . $e->getMessage());
      return ['status' => 'unreachable', 'message' => 'FastAPI tidak dapat dihubungi'];
    }
  }

  /**
   * Cancel a running scraping job.
   */
  public function cancelJob(string $jobId): array
  {
    try {
      $response = Http::timeout(10)
        ->post("{$this->baseUrl}/api/v1/scraping/jobs/{$jobId}/cancel");

      if ($response->successful()) {
        return $response->json();
      }

      return ['status' => 'error', 'message' => 'Gagal membatalkan job'];
    } catch (\Exception $e) {
      return ['status' => 'error', 'message' => 'Tidak dapat terhubung ke FastAPI'];
    }
  }

  /**
   * Get scraping status from FastAPI.
   */
  public function getScrapingStatus(): array
  {
    try {
      $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/scraping/status");

      if ($response->successful()) {
        return $response->json();
      }

      return ['status' => 'unknown'];
    } catch (\Exception $e) {
      return ['status' => 'unreachable'];
    }
  }
}
