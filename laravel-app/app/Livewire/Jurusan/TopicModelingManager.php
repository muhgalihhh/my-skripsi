<?php

namespace App\Livewire\Jurusan;

use App\Models\Skripsi;
use App\Models\TopicModelRun;
use App\Models\TopicModelTopic;
use App\Services\FastApiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.jurusan')]
#[Title('Topic Modeling')]
class TopicModelingManager extends Component
{
  // UI state
  public bool $isProcessing = false;
  public string $statusMessage = '';
  public string $statusType = 'info'; // info, success, error, warning

  // Preprocessing settings
  public bool $removeStopwords = false;
  public int $minWordLength = 3;
  public string $language = 'indonesian';

  // Topic modeling default params (from analysis metadata best params)
  public array $bertopicParams = [];

  // Current run (DB)
  public ?int $activeRunId = null;
  public ?TopicModelRun $activeRun = null;

  // Training job tracking
  public string $trainingJobId = '';
  public int $trainingProgress = 0;
  public string $trainingMessage = '';

  // Step-by-step preview
  public array $previewRows = [];

  public function mount(): void
  {
    $this->loadBestParamsFromMetadata();
    $this->loadLatestRun();
    $this->buildPreview();
  }

  protected function loadBestParamsFromMetadata(): void
  {
    // Best params from analysis/output/models/bertopic_best_model/metadata.json
    $path = base_path('../analysis/output/models/bertopic_best_model/metadata.json');
    if (!File::exists($path)) {
      // fallback to default preset on FastAPI side
      $this->bertopicParams = [];
      return;
    }

    try {
      $json = json_decode(File::get($path), true);
      $best = $json['best_params'] ?? [];

      // Map best_params -> BERTopicHyperparameters schema payload
      $this->bertopicParams = [
        'embedding_model' => $json['embedding_model'] ?? 'denaya/indoSBERT-large',
        'min_topic_size' => $best['min_topic_size'] ?? 5,
        'nr_topics' => null,
        'top_n_words' => 10,
        'n_gram_range' => [1, 2],
        'embedding_batch_size' => 16,
        'seed' => 42,
        'umap_params' => [
          'n_neighbors' => $best['umap_n_neighbors'] ?? 5,
          'n_components' => $best['umap_n_components'] ?? 10,
          'min_dist' => $best['umap_min_dist'] ?? 0.0,
          'metric' => 'cosine',
          'random_state' => 42,
        ],
        'hdbscan_params' => [
          'min_cluster_size' => $best['hdbscan_min_cluster_size'] ?? 10,
          'min_samples' => null,
          'cluster_selection_method' => 'eom',
        ],
      ];
    } catch (\Throwable $e) {
      $this->bertopicParams = [];
    }
  }

  protected function loadLatestRun(): void
  {
    /** @var int|null $userId */
  $userId = Auth::id();

    if ($userId === null) {
      $this->activeRun = null;
      $this->activeRunId = null;
      return;
    }

    $this->activeRun = TopicModelRun::with('topics')
      ->where('user_id', $userId)
      ->latest('id')
      ->first();

    $this->activeRunId = $this->activeRun?->id;

    if ($this->activeRun && $this->activeRun->status === 'training' && $this->activeRun->fastapi_training_job_id) {
      $this->trainingJobId = $this->activeRun->fastapi_training_job_id;
      $this->isProcessing = true;
    }
  }

  /**
   * Show preprocessing step-by-step on a few samples (best practice: preview, not full).
   */
  public function buildPreview(): void
  {
    $rows = Skripsi::query()
      ->select(['id', 'title', 'abstract', 'year'])
      ->whereNotNull('abstract')
      ->whereRaw('LENGTH(abstract) > 30')
      ->latest('id')
      ->limit(5)
      ->get();

    $service = new \App\Services\LocalTextPreprocessor(
      removeStopwords: $this->removeStopwords,
      minWordLength: $this->minWordLength,
      language: $this->language,
    );

    $this->previewRows = $rows->map(function ($r) use ($service) {
      $raw = (string) $r->abstract;

      return [
        'id' => $r->id,
        'year' => $r->year,
        'title' => $r->title,
        'raw' => $raw,
        'cleaned' => $service->cleanText($raw),
        'tokenized' => $service->tokenize($service->cleanText($raw)),
        'filtered_tokens' => $service->filterByLength($service->tokenize($service->cleanText($raw))),
        'stopwords_removed' => $service->removeStopwordsFromTokens(
          $service->filterByLength($service->tokenize($service->cleanText($raw)))
        ),
        'final_cleaned_text' => $service->preprocessCleaned($raw),
      ];
    })->toArray();

    // If we already have a run, store preview snapshot (optional)
    if ($this->activeRun) {
      $this->activeRun->update([
        'preprocessing_preview' => $this->previewRows,
      ]);
    }
  }

  /**
   * Step 1: Run preprocessing in FastAPI (creates processed_data.csv for training).
   */
  public function runPreprocessing(): void
  {
    $this->isProcessing = true;
    $this->statusType = 'info';
    $this->statusMessage = 'Menjalankan preprocessing...';

    // Create new run
    /** @var int|null $userId */
  $userId = Auth::id();

    if ($userId === null) {
      $this->statusType = 'error';
      $this->statusMessage = 'Silakan login terlebih dahulu.';
      $this->isProcessing = false;
      return;
    }

    $run = TopicModelRun::create([
      'user_id' => $userId,
      'status' => 'preprocessing',
      'remove_stopwords' => $this->removeStopwords,
      'min_word_length' => $this->minWordLength,
      'language' => $this->language,
      'bertopic_params' => $this->bertopicParams ?: null,
      'started_at' => now(),
      'preprocessing_preview' => $this->previewRows,
    ]);

    $this->activeRun = $run;
    $this->activeRunId = $run->id;

    $fastApi = app(FastApiService::class);
    $resp = $fastApi->runPreprocessing([
      'remove_stopwords' => $this->removeStopwords,
      'use_stemming' => true, // LDA pipeline still generated by FastAPI (not used in sprint)
      'min_word_length' => $this->minWordLength,
      'language' => $this->language,
    ]);

    if (($resp['status'] ?? '') !== 'success') {
      $run->update([
        'status' => 'failed',
        'error_message' => $resp['message'] ?? 'Preprocessing gagal',
        'completed_at' => now(),
      ]);

      $this->statusType = 'error';
      $this->statusMessage = $resp['message'] ?? 'Preprocessing gagal';
      $this->isProcessing = false;
      return;
    }

    $run->update([
      'status' => 'pending',
      'total_documents' => (int) ($resp['total_documents'] ?? 0),
      'total_tokens_before' => (int) ($resp['total_tokens_before'] ?? 0),
      'total_tokens_after_cleaned' => (int) ($resp['total_tokens_after_cleaned'] ?? 0),
    ]);

    $this->statusType = 'success';
    $this->statusMessage = $resp['message'] ?? 'Preprocessing selesai.';
    $this->isProcessing = false;

    $this->dispatch('toast', type: 'success', message: 'Preprocessing selesai.');
  }

  /**
   * Step 2: Start BERTopic training in FastAPI.
   */
  public function startTraining(): void
  {
    if (!$this->activeRun) {
      $this->statusType = 'error';
      $this->statusMessage = 'Jalankan preprocessing terlebih dahulu.';
      return;
    }

    $this->isProcessing = true;
    $this->statusType = 'info';
    $this->statusMessage = 'Memulai training BERTopic...';

    $fastApi = app(FastApiService::class);
    $resp = $fastApi->startBerTopicTraining(
      bertopicParams: $this->bertopicParams,
      description: 'BERTopic run from Jurusan dashboard',
    );

    // FastAPI returns TrainingStatusResponse with job_id + status
    if (!isset($resp['job_id'])) {
      $this->statusType = 'error';
      $this->statusMessage = $resp['message'] ?? 'Gagal memulai training.';
      $this->isProcessing = false;
      return;
    }

    $this->trainingJobId = $resp['job_id'];
    $this->trainingJobId = (string) $this->trainingJobId;

    $this->activeRun->update([
      'status' => 'training',
      'fastapi_training_job_id' => $this->trainingJobId,
    ]);

    $this->dispatch('toast', type: 'success', message: 'Training BERTopic dimulai.');
  }

  /**
   * Poll training status and store results in DB when completed.
   */
  public function pollTrainingProgress(): void
  {
    if (!$this->trainingJobId || !$this->activeRun) {
      return;
    }

    $fastApi = app(FastApiService::class);
    $status = $fastApi->getTrainingStatus($this->trainingJobId);

    if (($status['status'] ?? '') === 'unreachable') {
      $this->trainingMessage = 'FastAPI tidak dapat dihubungi, mencoba lagi...';
      return;
    }

    // FastAPI schema: { job_id, status, model_type, progress, message, ... }
    $this->trainingProgress = (int) round($status['progress'] ?? 0);
    $this->trainingMessage = (string) ($status['message'] ?? '');

    $state = $status['status'] ?? null;

    if ($state === 'completed') {
      $this->storeTrainingResults();
      $this->isProcessing = false;
    } elseif ($state === 'failed') {
      $this->activeRun->update([
        'status' => 'failed',
        'error_message' => (string) ($status['error'] ?? $this->trainingMessage ?? 'Training gagal'),
        'completed_at' => now(),
      ]);

      $this->statusType = 'error';
      $this->statusMessage = 'Training gagal: ' . ($status['error'] ?? $this->trainingMessage);
      $this->isProcessing = false;
    } else {
      $this->isProcessing = true;
    }
  }

  protected function storeTrainingResults(): void
  {
    if (!$this->activeRun) {
      return;
    }

    $fastApi = app(FastApiService::class);
    $results = $fastApi->getTrainingResults($this->trainingJobId);

    if (($results['status'] ?? '') === 'not_found' || ($results['status'] ?? '') === 'error') {
      $this->statusType = 'warning';
      $this->statusMessage = 'Training selesai, tapi hasil belum siap. Coba refresh beberapa saat.';
      return;
    }

    // Results shape from TrainingService:
    // { model_type, num_topics, num_outliers, hyperparameters, topic_info, metrics, model_path, ... }
    $metrics = $results['metrics'] ?? [];

    $this->activeRun->update([
      'status' => 'completed',
      'num_topics' => (int) ($results['num_topics'] ?? null),
      'num_outliers' => (int) ($results['num_outliers'] ?? null),
      'coherence_cv' => isset($metrics['coherence_cv']) ? (float) $metrics['coherence_cv'] : null,
      'topic_diversity' => isset($metrics['topic_diversity']) ? (float) $metrics['topic_diversity'] : null,
      'model_path' => (string) ($results['model_path'] ?? null),
      'completed_at' => now(),
    ]);

    // Store topics
    $topics = $results['topic_info'] ?? [];
    foreach ($topics as $t) {
      TopicModelTopic::updateOrCreate(
        [
          'topic_model_run_id' => $this->activeRun->id,
          'topic_id' => (int) ($t['topic_id'] ?? 0),
        ],
        [
          'count' => (int) ($t['count'] ?? 0),
          'top_words' => $t['top_words'] ?? [],
          'word_scores' => $t['word_scores'] ?? [],
        ]
      );
    }

    $this->statusType = 'success';
    $this->statusMessage = 'Training selesai dan hasil disimpan ke database.';
    $this->dispatch('toast', type: 'success', message: 'Training BERTopic selesai.');

    // reload topics relation
    $this->activeRun->load('topics');
  }

  public function render()
  {
    /** @var int|null $userId */
  $userId = Auth::id();

    if ($userId === null) {
      $latestRuns = collect();
      return view('livewire.jurusan.topic-modeling-manager', [
        'latestRuns' => $latestRuns,
      ]);
    }
    $latestRuns = TopicModelRun::withCount('topics')
      ->where('user_id', $userId)
      ->latest('id')
      ->limit(10)
      ->get();

    return view('livewire.jurusan.topic-modeling-manager', [
      'latestRuns' => $latestRuns,
    ]);
  }
}
