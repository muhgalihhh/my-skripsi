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
      /** @var \Illuminate\Http\Client\Response $response */
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

      /** @var \Illuminate\Http\Client\Response $response */
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
      /** @var \Illuminate\Http\Client\Response $response */
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
      /** @var \Illuminate\Http\Client\Response $response */
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
      /** @var \Illuminate\Http\Client\Response $response */
      $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/scraping/status");

      if ($response->successful()) {
        return $response->json();
      }

      return ['status' => 'unknown'];
    } catch (\Exception $e) {
      return ['status' => 'unreachable'];
    }
  }

  // ============================================
  // Preprocessing
  // ============================================

  /**
   * Start preprocessing pipeline (dual outputs) in FastAPI as a background job.
   */
  public function startPreprocessing(int $runId): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = Http::timeout(10)
        ->post("{$this->baseUrl}/api/v1/preprocessing/start", ['run_id' => $runId]);

      if ($response->successful()) {
        return $response->json();
      }

      return [
        'status' => 'error',
        'message' => 'Gagal memulai preprocessing. Status: ' . $response->status(),
        'detail' => $response->json() ?? $response->body(),
      ];
    } catch (\Exception $e) {
      Log::error('FastAPI start preprocessing request failed: ' . $e->getMessage());
      return [
        'status' => 'error',
        'message' => 'Tidak dapat terhubung ke FastAPI service: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Poll preprocessing status.
   */
  public function getPreprocessingStatus(string $jobId): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = Http::timeout(10)
        ->get("{$this->baseUrl}/api/v1/preprocessing/jobs/{$jobId}");

      if ($response->successful()) {
        return $response->json();
      }

      if ($response->status() === 404) {
        return ['status' => 'not_found', 'message' => "Preprocessing job {$jobId} tidak ditemukan"];
      }

      return ['status' => 'error', 'message' => 'Gagal mendapatkan status preprocessing'];
    } catch (\Exception $e) {
      Log::warning('FastAPI preprocessing status check failed: ' . $e->getMessage());
      return ['status' => 'unreachable', 'message' => 'FastAPI tidak dapat dihubungi'];
    }
  }

  /**
   * Cancel a running preprocessing job.
   */
  public function cancelPreprocessingJob(string $jobId): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = Http::timeout(10)
        ->post("{$this->baseUrl}/api/v1/preprocessing/jobs/{$jobId}/cancel");

      if ($response->successful()) {
        return $response->json();
      }

      return ['status' => 'error', 'message' => 'Gagal membatalkan job'];
    } catch (\Exception $e) {
      return ['status' => 'error', 'message' => 'Tidak dapat terhubung ke FastAPI'];
    }
  }

  // ============================================
  // Training (BERTopic)
  // ============================================

  /**
   * Get a quick summary of the processed dataset for training.
   */
  public function getTrainingDatasetSummary(): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = Http::timeout(10)
        ->get("{$this->baseUrl}/api/v1/training/dataset/summary");

      if ($response->successful()) {
        return $response->json();
      }

      return ['status' => 'error', 'message' => 'Gagal mengambil summary dataset training'];
    } catch (\Exception $e) {
      Log::warning('FastAPI training dataset summary fetch failed: ' . $e->getMessage());
      return ['status' => 'unreachable', 'message' => 'FastAPI tidak dapat dihubungi'];
    }
  }

  /**
   * Start BERTopic training job.
   *
   * Payload shape follows TrainingRequest schema in FastAPI.
   */
  public function startBerTopicTraining(array $bertopicParams = [], ?string $description = null): array
  {
    try {
      $payload = array_filter([
        'model_type' => 'bertopic',
        'bertopic_params' => $bertopicParams ?: null,
        'description' => $description,
      ], fn($v) => $v !== null);

      /** @var \Illuminate\Http\Client\Response $response */
      $response = Http::timeout(30)
        ->post("{$this->baseUrl}/api/v1/training/start", $payload);

      if ($response->successful()) {
        return $response->json();
      }

      return [
        'status' => 'error',
        'message' => 'Gagal memulai training. Status: ' . $response->status(),
        'detail' => $response->json() ?? $response->body(),
      ];
    } catch (\Exception $e) {
      Log::error('FastAPI start training request failed: ' . $e->getMessage());
      return [
        'status' => 'error',
        'message' => 'Tidak dapat terhubung ke FastAPI service: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Poll training status.
   */
  public function getTrainingStatus(string $jobId): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = Http::timeout(10)
        ->get("{$this->baseUrl}/api/v1/training/status/{$jobId}");

      if ($response->successful()) {
        return $response->json();
      }

      if ($response->status() === 404) {
        return ['status' => 'not_found', 'message' => "Training job {$jobId} tidak ditemukan"];
      }

      return ['status' => 'error', 'message' => 'Gagal mendapatkan status training'];
    } catch (\Exception $e) {
      Log::warning('FastAPI training status check failed: ' . $e->getMessage());
      return ['status' => 'unreachable', 'message' => 'FastAPI tidak dapat dihubungi'];
    }
  }

  /**
   * Get training results JSON from FastAPI.
   */
  public function getTrainingResults(string $jobId): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = Http::timeout(20)
        ->get("{$this->baseUrl}/api/v1/training/results/{$jobId}");

      if ($response->successful()) {
        return $response->json();
      }

      if ($response->status() === 404) {
        return ['status' => 'not_found', 'message' => "Hasil training {$jobId} tidak ditemukan"];
      }

      return ['status' => 'error', 'message' => 'Gagal mendapatkan hasil training'];
    } catch (\Exception $e) {
      Log::warning('FastAPI training results fetch failed: ' . $e->getMessage());
      return ['status' => 'unreachable', 'message' => 'FastAPI tidak dapat dihubungi'];
    }
  }
}
