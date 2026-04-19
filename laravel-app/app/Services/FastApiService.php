<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FastApiService
{
  protected string $baseUrl;
  protected string $apiKey;

  public function __construct()
  {
    $this->baseUrl = rtrim(config('services.fastapi.base_url', 'http://localhost:8000'), '/');
    $this->apiKey = trim((string) config('services.fastapi.api_key', ''));
  }

  protected function fastApiRequest(int $timeout = 30): PendingRequest
  {
    $request = Http::timeout($timeout);

    if ($this->apiKey !== '') {
      $request = $request->withHeaders([
        'X-API-Key' => $this->apiKey,
      ]);
    }

    return $request;
  }

  /**
   * Check health of FastAPI service.
   */
  public function healthCheck(): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(5)->get("{$this->baseUrl}/api/v1/health");

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
      $response = $this->fastApiRequest(15)
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
      $response = $this->fastApiRequest(10)
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
      $response = $this->fastApiRequest(10)
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
      $response = $this->fastApiRequest(5)->get("{$this->baseUrl}/api/v1/scraping/status");

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
      $response = $this->fastApiRequest(10)
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
      $response = $this->fastApiRequest(10)
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
   * Get dropped-record report for a preprocessing job.
   */
  public function getPreprocessingDroppedReport(string $jobId): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(10)
        ->get("{$this->baseUrl}/api/v1/preprocessing/jobs/{$jobId}/dropped");

      if ($response->successful()) {
        return $response->json();
      }

      if ($response->status() === 404) {
        return ['status' => 'not_found', 'message' => "Preprocessing job {$jobId} tidak ditemukan"];
      }

      return ['status' => 'error', 'message' => 'Gagal mendapatkan laporan data ter-drop'];
    } catch (\Exception $e) {
      Log::warning('FastAPI preprocessing dropped-report check failed: ' . $e->getMessage());
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
      $response = $this->fastApiRequest(10)
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
  // Training (BERTopic/LDA)
  // ============================================

  /**
   * Get a quick summary of the processed dataset for training.
   */
  public function getTrainingDatasetSummary(): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(10)
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
  public function startBerTopicTraining(array $bertopicParams = [], ?string $description = null, ?int $userId = null): array
  {
    try {
      $payload = array_filter([
        'model_type' => 'bertopic',
        'user_id' => $userId,
        'bertopic_params' => $bertopicParams ?: null,
        'description' => $description,
      ], fn($v) => $v !== null);

      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(30)
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
   * Start LDA training job.
   *
   * Payload shape follows TrainingRequest schema in FastAPI.
   */
  public function startLdaTraining(array $ldaParams = [], ?string $description = null, ?int $userId = null): array
  {
    try {
      $payload = array_filter([
        'model_type' => 'lda',
        'user_id' => $userId,
        'lda_params' => $ldaParams ?: null,
        'description' => $description,
      ], fn($v) => $v !== null);

      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(30)
        ->post("{$this->baseUrl}/api/v1/training/start", $payload);

      if ($response->successful()) {
        return $response->json();
      }

      return [
        'status' => 'error',
        'message' => 'Gagal memulai training LDA. Status: ' . $response->status(),
        'detail' => $response->json() ?? $response->body(),
      ];
    } catch (\Exception $e) {
      Log::error('FastAPI start LDA training request failed: ' . $e->getMessage());
      return [
        'status' => 'error',
        'message' => 'Tidak dapat terhubung ke FastAPI service: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Upload BERTopic params JSON to FastAPI for parsing/validation.
   */
  public function uploadBertopicSettingsJson(string $filePath, string $filename, ?int $userId = null): array
  {
    try {
      if (!is_file($filePath)) {
        return ['status' => 'error', 'message' => 'File JSON tidak ditemukan.'];
      }

      /** @var \Illuminate\Http\Client\Response $response */
      $request = $this->fastApiRequest(30)
        ->attach(
          'config_file',
          file_get_contents($filePath),
          $filename !== '' ? $filename : 'bertopic_params.json'
        );

      $formPayload = [];
      if ($userId !== null) {
        $formPayload['user_id'] = $userId;
      }

      /** @var \Illuminate\Http\Client\Response $response */
      $response = $request->post("{$this->baseUrl}/api/v1/training/settings/upload", $formPayload);

      if ($response->successful()) {
        return $response->json();
      }

      return [
        'status' => 'error',
        'message' => 'Gagal upload params. Status: ' . $response->status(),
        'detail' => $response->json() ?? $response->body(),
      ];
    } catch (\Exception $e) {
      Log::error('FastAPI upload BERTopic settings failed: ' . $e->getMessage());
      return [
        'status' => 'error',
        'message' => 'Tidak dapat terhubung ke FastAPI service: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Upload trained model archive (.tar/.tar.gz/.tgz) for import without retraining.
   */
  public function importTrainedModelArchive(string $filePath, string $filename, ?string $modelType = null): array
  {
    try {
      if (!is_file($filePath)) {
        return ['status' => 'error', 'message' => 'File arsip model tidak ditemukan.'];
      }

      $archiveName = trim($filename) !== '' ? $filename : 'model_archive.tar.gz';

      $request = $this->fastApiRequest(600)
        ->attach(
          'model_archive',
          file_get_contents($filePath),
          $archiveName
        );

      $formPayload = [];
      if ($modelType !== null) {
        $normalizedModelType = strtolower(trim($modelType));
        if (in_array($normalizedModelType, ['bertopic', 'lda'], true)) {
          $formPayload['model_type'] = $normalizedModelType;
        }
      }

      /** @var \Illuminate\Http\Client\Response $response */
      $response = $request->post("{$this->baseUrl}/api/v1/training/model/import", $formPayload);

      if ($response->successful()) {
        return $response->json();
      }

      $detail = $response->json('detail');
      $message = null;

      if (is_string($detail) && trim($detail) !== '') {
        $message = $detail;
      } elseif (is_array($detail) && isset($detail['message'])) {
        $message = (string) $detail['message'];
      }

      if ($message === null || trim($message) === '') {
        $message = 'Gagal import model. Status: ' . $response->status();
      }

      return [
        'status' => 'error',
        'message' => $message,
        'detail' => $detail ?? $response->body(),
      ];
    } catch (\Exception $e) {
      Log::error('FastAPI import trained model failed: ' . $e->getMessage());
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
      $response = $this->fastApiRequest(10)
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
   * Cancel a running/pending training job.
   */
  public function cancelTrainingJob(string $jobId): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(10)
        ->post("{$this->baseUrl}/api/v1/training/jobs/{$jobId}/cancel");

      if ($response->successful()) {
        return $response->json();
      }

      if ($response->status() === 404) {
        return ['status' => 'not_found', 'message' => "Training job {$jobId} tidak ditemukan"];
      }

      $detail = $response->json('detail');
      if (is_array($detail) && isset($detail['message'])) {
        return [
          'status' => 'error',
          'message' => (string) $detail['message'],
        ];
      }

      return ['status' => 'error', 'message' => 'Gagal membatalkan training'];
    } catch (\Exception $e) {
      Log::warning('FastAPI cancel training request failed: ' . $e->getMessage());
      return ['status' => 'error', 'message' => 'Tidak dapat terhubung ke FastAPI'];
    }
  }

  /**
   * Get training results JSON from FastAPI.
   */
  public function getTrainingResults(string $jobId): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(20)
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

  /**
   * Re-test a saved model against the existing dataset in DB.
   * Compares metrics/keywords vs stored training results.
   */
  public function testModelWithDataset(string $jobId): array
  {
    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(120)
        ->get("{$this->baseUrl}/api/v1/training/model/{$jobId}/test-dataset");

      if ($response->successful()) {
        return $response->json();
      }

      if ($response->status() === 404) {
        return ['status' => 'not_found', 'message' => "Model/hasil {$jobId} tidak ditemukan"]; 
      }

      $detailPayload = $response->json();
      $detailText = null;

      if (is_array($detailPayload)) {
        $detailValue = $detailPayload['detail'] ?? null;
        if (is_string($detailValue)) {
          $detailText = trim($detailValue);
        } elseif (is_array($detailValue)) {
          $detailText = json_encode($detailValue, JSON_UNESCAPED_UNICODE);
        }
      }

      if ($detailText === null || $detailText === '') {
        $detailText = trim((string) $response->body());
      }

      if (
        $response->status() === 400
        && $detailText !== ''
        && (
          str_contains(strtolower($detailText), 'dataset in db is empty')
          || str_contains(strtolower($detailText), 'run preprocessing first')
          || str_contains(strtolower($detailText), 'no valid records')
        )
      ) {
        return [
          'status' => 'error',
          'message' => 'Data preprocessing belum tersedia untuk test model. Jalankan preprocessing terlebih dahulu.',
          'detail' => $detailText,
        ];
      }

      Log::warning('FastAPI model test-dataset failed', [
        'status' => $response->status(),
        'body' => $response->body(),
      ]);

      return [
        'status' => 'error',
        'message' => $detailText !== ''
          ? ('Gagal test model: ' . $detailText)
          : ('Gagal test model. Status: ' . $response->status()),
      ];
    } catch (\Exception $e) {
      Log::warning('FastAPI model test-dataset request failed: ' . $e->getMessage());
      return ['status' => 'unreachable', 'message' => 'FastAPI tidak dapat dihubungi'];
    }
  }

  /**
   * Run Dynamic Topic Analysis (DTA) using an existing BERTopic model.
   */
  public function runDynamicTopicAnalysis(
    string $jobId,
    ?int $yearStart = null,
    ?int $yearEnd = null,
    bool $evolutionTuning = true,
    bool $globalTuning = true,
  ): array {
    try {
      $payload = [
        'job_id' => $jobId,
        'evolution_tuning' => $evolutionTuning,
        'global_tuning' => $globalTuning,
      ];

      if ($yearStart !== null) {
        $payload['year_start'] = $yearStart;
      }

      if ($yearEnd !== null) {
        $payload['year_end'] = $yearEnd;
      }

      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(120)
        ->post("{$this->baseUrl}/api/v1/evaluation/dta", $payload);

      if ($response->successful()) {
        return $response->json();
      }

      if ($response->status() === 404) {
        return ['status' => 'not_found', 'message' => "Model/hasil {$jobId} tidak ditemukan"]; 
      }

      if ($response->status() === 422) {
        return [
          'status' => 'error',
          'message' => 'Request DTA tidak valid.',
          'detail' => $response->json() ?? $response->body(),
        ];
      }

      Log::warning('FastAPI DTA failed', [
        'status' => $response->status(),
        'body' => $response->body(),
      ]);

      return ['status' => 'error', 'message' => 'Gagal menghitung DTA. Status: ' . $response->status()];
    } catch (\Exception $e) {
      Log::warning('FastAPI DTA request failed: ' . $e->getMessage());
      return ['status' => 'unreachable', 'message' => 'FastAPI tidak dapat dihubungi'];
    }
  }

  /**
   * Generate topic curation suggestion through FastAPI (Python Gemini SDK).
   *
   * @param array<string, mixed> $payload
   */
  public function generateTopicCurationSuggestion(array $payload): array
  {
    $keywords = is_array($payload['keywords'] ?? null) ? $payload['keywords'] : [];

    $cleanKeywords = array_values(array_filter(array_map(
      static fn($keyword): string => trim((string) $keyword),
      $keywords
    ), static fn(string $keyword): bool => $keyword !== ''));

    if (empty($cleanKeywords)) {
      return [
        'status' => 'error',
        'message' => 'Kata kunci topik kosong, AI tidak bisa diproses.',
      ];
    }

    $cleanStringList = static function (mixed $raw, int $maxItems, int $maxChars = 320): array {
      if (!is_array($raw)) {
        return [];
      }

      $items = array_values(array_filter(array_map(
        static function ($item) use ($maxChars): string {
          $text = trim((string) $item);
          if ($text === '') {
            return '';
          }

          return mb_substr($text, 0, $maxChars);
        },
        $raw
      ), static fn(string $text): bool => $text !== ''));

      return array_slice(array_values(array_unique($items)), 0, $maxItems);
    };

    $requestPayload = [
      'keywords' => $cleanKeywords,
    ];

    if (isset($payload['topic_id']) && is_numeric($payload['topic_id'])) {
      $requestPayload['topic_id'] = (int) $payload['topic_id'];
    }

    if (isset($payload['topic_doc_count']) && is_numeric($payload['topic_doc_count'])) {
      $requestPayload['topic_doc_count'] = max(0, (int) $payload['topic_doc_count']);
    }

    $modelType = trim((string) ($payload['model_type'] ?? ''));
    if ($modelType !== '') {
      $requestPayload['model_type'] = mb_substr($modelType, 0, 32);
    }

    $representativeTitles = $cleanStringList($payload['representative_titles'] ?? null, 2000, 180);
    if (!empty($representativeTitles)) {
      $requestPayload['representative_titles'] = $representativeTitles;
    }

    $representativeAbstracts = $cleanStringList($payload['representative_abstracts'] ?? null, 4, 320);
    if (!empty($representativeAbstracts)) {
      $requestPayload['representative_abstracts'] = $representativeAbstracts;
    }

    $broaderTerms = $cleanStringList($payload['broader_terms'] ?? null, 20, 80);
    if (!empty($broaderTerms)) {
      $requestPayload['broader_terms'] = $broaderTerms;
    }

    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(45)
        ->post("{$this->baseUrl}/api/v1/training/topic-curation/generate", $requestPayload);

      if ($response->successful()) {
        $body = $response->json();

        return [
          'status' => 'ok',
          'custom_name' => (string) ($body['custom_name'] ?? ''),
          'representation_description' => (string) ($body['representation_description'] ?? ''),
        ];
      }

      $detail = $response->json('detail');
      if (is_string($detail) && trim($detail) !== '') {
        return ['status' => 'error', 'message' => trim($detail)];
      }

      if (is_array($detail) && isset($detail['message']) && trim((string) $detail['message']) !== '') {
        return ['status' => 'error', 'message' => trim((string) $detail['message'])];
      }

      return ['status' => 'error', 'message' => 'Gagal membuat saran AI di FastAPI.'];
    } catch (\Exception $e) {
      Log::warning('FastAPI topic curation suggestion request failed: ' . $e->getMessage());
      return ['status' => 'unreachable', 'message' => 'FastAPI tidak dapat dihubungi'];
    }
  }

  /**
   * Generate skripsi title recommendations via FastAPI Gemini endpoint.
   *
   * @param array<string, mixed> $payload
   */
  public function generateSkripsiTitleRecommendations(array $payload): array
  {
    $cleanStringList = static function (mixed $raw, int $maxItems, int $maxChars): array {
      if (!is_array($raw)) {
        return [];
      }

      $items = array_values(array_filter(array_map(
        static function ($item) use ($maxChars): string {
          $text = trim((string) $item);
          if ($text === '') {
            return '';
          }

          return mb_substr($text, 0, $maxChars);
        },
        $raw
      ), static fn(string $text): bool => $text !== ''));

      return array_slice(array_values(array_unique($items)), 0, $maxItems);
    };

    $topicKeywords = $cleanStringList($payload['topic_keywords'] ?? null, 20, 80);
    if (empty($topicKeywords)) {
      return [
        'status' => 'error',
        'message' => 'Kata kunci topik kosong, AI tidak bisa diproses.',
      ];
    }

    $userPrompt = trim((string) ($payload['user_prompt'] ?? ''));
    if ($userPrompt === '') {
      return [
        'status' => 'error',
        'message' => 'Prompt rekomendasi tidak boleh kosong.',
      ];
    }

    $requestPayload = [
      'topic_keywords' => $topicKeywords,
      'mapped_titles' => $cleanStringList($payload['mapped_titles'] ?? null, 80, 220),
      'user_prompt' => mb_substr($userPrompt, 0, 1200),
      'recommendations_count' => max(3, min(10, (int) ($payload['recommendations_count'] ?? 5))),
      'strict_context' => (bool) ($payload['strict_context'] ?? true),
    ];

    if (isset($payload['topic_id']) && is_numeric($payload['topic_id'])) {
      $requestPayload['topic_id'] = (int) $payload['topic_id'];
    }

    $topicLabel = trim((string) ($payload['topic_label'] ?? ''));
    if ($topicLabel !== '') {
      $requestPayload['topic_label'] = mb_substr($topicLabel, 0, 180);
    }

    try {
      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(60)
        ->post("{$this->baseUrl}/api/v1/training/title-recommendation/generate", $requestPayload);

      if ($response->successful()) {
        $body = $response->json();
        return [
          'status' => 'ok',
          'context_ok' => (bool) ($body['context_ok'] ?? true),
          'context_message' => isset($body['context_message']) ? (string) $body['context_message'] : null,
          'recommendations' => is_array($body['recommendations'] ?? null) ? $body['recommendations'] : [],
        ];
      }

      $detail = $response->json('detail');
      if (is_string($detail) && trim($detail) !== '') {
        return ['status' => 'error', 'message' => trim($detail)];
      }

      if (is_array($detail) && isset($detail['message']) && trim((string) $detail['message']) !== '') {
        return ['status' => 'error', 'message' => trim((string) $detail['message'])];
      }

      return ['status' => 'error', 'message' => 'Gagal membuat rekomendasi judul di FastAPI.'];
    } catch (\Exception $e) {
      Log::warning('FastAPI title recommendation request failed: ' . $e->getMessage());
      return ['status' => 'unreachable', 'message' => 'FastAPI tidak dapat dihubungi'];
    }
  }

  /**
   * Infer the most relevant BERTopic topic from a free-text query.
   */
  public function inferTopicForQuery(string $jobId, string $query, int $topNTopics = 5): array
  {
    try {
      $payload = [
        'text' => $query,
        'top_n_topics' => max(1, min(10, $topNTopics)),
      ];

      /** @var \Illuminate\Http\Client\Response $response */
      $response = $this->fastApiRequest(60)
        ->post("{$this->baseUrl}/api/v1/training/model/{$jobId}/infer", $payload);

      if ($response->successful()) {
        return $response->json();
      }

      if ($response->status() === 404) {
        return ['status' => 'not_found', 'message' => "Model/hasil {$jobId} tidak ditemukan"];
      }

      if ($response->status() === 422) {
        return [
          'status' => 'error',
          'message' => 'Query smart search tidak valid.',
          'detail' => $response->json() ?? $response->body(),
        ];
      }

      Log::warning('FastAPI topic inference failed', [
        'status' => $response->status(),
        'body' => $response->body(),
      ]);

      return ['status' => 'error', 'message' => 'Gagal melakukan inferensi topik. Status: ' . $response->status()];
    } catch (\Exception $e) {
      Log::warning('FastAPI topic inference request failed: ' . $e->getMessage());
      return ['status' => 'unreachable', 'message' => 'FastAPI tidak dapat dihubungi'];
    }
  }
}
