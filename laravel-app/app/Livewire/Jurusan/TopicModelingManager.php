<?php

namespace App\Livewire\Jurusan;

use App\Models\Skripsi;
use App\Models\TopicModelRun;
use App\Models\TopicModelSetting;
use App\Models\TopicModelTopic;
use App\Models\TopicModelDataset;
use App\Models\TopicModelTopicDocument;
use App\Services\FastApiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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

    // FastAPI status (align with ScrapingManager UX)
    public array $apiStatus = [];

    // Preprocessing settings (BERTopic = soft clean, LDA = full clean)
    public bool $removeStopwords = true;
    public int $minWordLength = 3;
    public string $language = 'indonesian';

    // BERTopic params (default mengikuti hasil tuning terbaik terbaru)
    public array $bertopicParams = [];

    // Current run (DB)
    public ?int $activeRunId = null;
    public ?TopicModelRun $activeRun = null;

    // Training & Preprocessing job tracking
    public string $trainingJobId = '';
    public int $trainingProgress = 0;
    public string $trainingMessage = '';

    public string $preprocessingJobId = '';
    public int $preprocessingProgress = 0;
    public string $preprocessingMessage = '';

    // Preview data (in-memory only, tidak disimpan ke DB)
    public array $previewRows = [];

    // Dataset readiness (from FastAPI)
    public array $datasetSummary = [];

    // Model utilities (download + test)
    public bool $modelTestLoading = false;
    public array $modelTestDatasetResult = [];

    // Show preprocessed texts stored in DB (skripsi.cleaned_text / skripsi.processed_text)
    public array $dbPreprocessedRows = [];
    public int $dbPreprocessedLimit = 20;
    public bool $dbPreprocessedHasMore = false;
    public bool $dbPreprocessedLoading = false;

    public function mount(): void
    {
        $this->checkApiStatus();
        $this->loadLatestRun();
        $this->loadTrainingParams();
        $this->resumeActiveJobs();
        $this->buildPreview();
        $this->loadDatasetSummary();
        $this->loadDbPreprocessedRows();
    }

    /**
     * Load BERTopic training params with precedence:
     * 1) active run snapshot (topic_model_runs.bertopic_params)
     * 2) user default setting (topic_model_settings.bertopic_params)
     * 3) metadata.json from analysis output / fallback default
     */
    protected function loadTrainingParams(): void
    {
        // 1) Active run snapshot
        if ($this->activeRun && is_array($this->activeRun->bertopic_params) && !empty($this->activeRun->bertopic_params)) {
            $this->bertopicParams = $this->activeRun->bertopic_params;
            return;
        }

        /** @var int|null $userId */
        $userId = Auth::id();

        // 2) User default setting
        if ($userId !== null) {
            $setting = TopicModelSetting::query()->where('user_id', $userId)->first();
            if ($setting && is_array($setting->bertopic_params) && !empty($setting->bertopic_params)) {
                $this->bertopicParams = $setting->bertopic_params;
                return;
            }
        }

        // 3) Metadata/default
        $this->loadBestParamsFromMetadata();
    }

    /**
     * Persist current BERTopic params as user's default in DB.
     */
    public function saveTrainingParams(): void
    {
        /** @var int|null $userId */
        $userId = Auth::id();
        if ($userId === null) {
            $this->dispatch('toast', type: 'error', message: 'Silakan login terlebih dahulu.');
            return;
        }

        TopicModelSetting::query()->updateOrCreate(
            ['user_id' => $userId],
            ['bertopic_params' => $this->bertopicParams ?: null],
        );

        // If there is an active run that hasn't finished, keep its snapshot in sync
        if ($this->activeRun && in_array($this->activeRun->status, ['pending', 'preprocessing', 'failed'])) {
            $this->activeRun->update([
                'bertopic_params' => $this->bertopicParams ?: null,
            ]);
            $this->activeRun->refresh();
        }

        $this->dispatch('toast', type: 'success', message: 'Parameter training BERTopic tersimpan di database.');
    }

    public function loadDbPreprocessedRows(): void
    {
        $this->dbPreprocessedLoading = true;

        try {
            $rows = TopicModelDataset::query()
                ->select(['skripsi_id', 'title', 'year', 'cleaned_text', 'processed_text'])
                ->orderByDesc('skripsi_id')
                ->limit($this->dbPreprocessedLimit + 1)
                ->get();

            $mapped = $rows
                ->take($this->dbPreprocessedLimit)
                ->map(fn($r) => [
                    'id' => $r->skripsi_id,
                    'title' => $r->title,
                    'year' => $r->year,
                    'cleaned_text' => (string) ($r->cleaned_text ?? ''),
                    'processed_text' => (string) ($r->processed_text ?? ''),
                ]);
        } catch (\Throwable $e) {
            // Fallback for environments where topic_model_datasets migration hasn't been run yet.
            $rows = Skripsi::query()
                ->select(['id', 'title', 'year', 'cleaned_text', 'processed_text'])
                ->whereNotNull('cleaned_text')
                ->whereNotNull('processed_text')
                ->orderByDesc('id')
                ->limit($this->dbPreprocessedLimit + 1)
                ->get();

            $mapped = $rows
                ->take($this->dbPreprocessedLimit)
                ->map(fn($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'year' => $r->year,
                    'cleaned_text' => (string) ($r->cleaned_text ?? ''),
                    'processed_text' => (string) ($r->processed_text ?? ''),
                ]);
        }

        $this->dbPreprocessedHasMore = $rows->count() > $this->dbPreprocessedLimit;

        $this->dbPreprocessedRows = $mapped->toArray();

        $this->dbPreprocessedLoading = false;
    }

    public function loadMoreDbPreprocessedRows(): void
    {
        $this->dbPreprocessedLimit += 20;
        $this->loadDbPreprocessedRows();
    }

    public function checkApiStatus(): void
    {
        $fastApiService = app(FastApiService::class);
        $this->apiStatus = $fastApiService->healthCheck();
    }

    public function loadDatasetSummary(): void
    {
        $fastApiService = app(FastApiService::class);
        $this->datasetSummary = $fastApiService->getTrainingDatasetSummary();
    }

    /**
     * Resume active preprocessing/training jobs after page refresh.
     *
     * Similar to ScrapingManager::resumeActiveJob(), this checks the DB
     * for any run with status 'preprocessing' or 'training', then queries
     * FastAPI to verify the actual job state. This makes background jobs
     * survive page refreshes.
     */
    protected function resumeActiveJobs(): void
    {
        if (!$this->activeRun) {
            return;
        }

        $fastApi = app(FastApiService::class);

        // --- Resume preprocessing job ---
        if ($this->activeRun->status === 'preprocessing' && $this->activeRun->fastapi_preprocessing_job_id) {
            $jobId = $this->activeRun->fastapi_preprocessing_job_id;
            $status = $fastApi->getPreprocessingStatus($jobId);

            $state = $status['status'] ?? 'unknown';

            if (in_array($state, ['pending', 'running'])) {
                // Job is still running in FastAPI — resume tracking
                $this->preprocessingJobId = $jobId;
                $this->isProcessing = true;
                $this->preprocessingProgress = (int) round($status['progress'] ?? 0);
                $this->preprocessingMessage = $status['message'] ?? 'Sedang berjalan...';
                $this->statusMessage = "Preprocessing sedang berjalan ({$this->preprocessingProgress}%)...";
                $this->statusType = 'info';

                Log::info('Resumed preprocessing job tracking', ['job_id' => $jobId, 'progress' => $this->preprocessingProgress]);
            } elseif ($state === 'completed') {
                // Job completed while page was away — update DB
                $this->activeRun->update([
                    'status' => 'pending',
                    'total_documents' => (int) ($status['processed'] ?? 0),
                ]);
                $this->activeRun->refresh();
                $this->statusMessage = 'Preprocessing telah selesai. Silakan lanjutkan ke training.';
                $this->statusType = 'success';
                $this->dispatch('toast', type: 'success', message: 'Preprocessing selesai (dari background)!');

                Log::info('Preprocessing job completed while page was away', ['job_id' => $jobId]);
            } elseif ($state === 'failed') {
                // Job failed while page was away
                $this->activeRun->update([
                    'status' => 'failed',
                    'error_message' => $status['error'] ?? $status['message'] ?? 'Preprocessing gagal',
                    'completed_at' => now(),
                ]);
                $this->activeRun->refresh();
                $this->statusMessage = 'Preprocessing gagal: ' . ($status['error'] ?? 'Unknown error');
                $this->statusType = 'error';

                Log::warning('Preprocessing job failed while page was away', ['job_id' => $jobId]);
            } elseif ($state === 'unreachable') {
                // FastAPI is down — keep state, user can refresh later
                $this->preprocessingJobId = $jobId;
                $this->isProcessing = true;
                $this->statusMessage = 'FastAPI tidak dapat dihubungi. Status preprocessing tidak diketahui.';
                $this->statusType = 'warning';
            } else {
                // Job not found in FastAPI (maybe service restarted) — mark as failed
                $this->activeRun->update([
                    'status' => 'failed',
                    'error_message' => 'Job tidak ditemukan di FastAPI (mungkin service restart)',
                    'completed_at' => now(),
                ]);
                $this->activeRun->refresh();
                $this->statusMessage = 'Preprocessing job tidak ditemukan di FastAPI. Silakan jalankan ulang.';
                $this->statusType = 'error';

                Log::warning('Preprocessing job not found in FastAPI', ['job_id' => $jobId]);
            }
            return; // Don't check training if still in preprocessing phase
        }

        // --- Resume training job ---
        if ($this->activeRun->status === 'training' && $this->activeRun->fastapi_training_job_id) {
            $jobId = $this->activeRun->fastapi_training_job_id;
            $status = $fastApi->getTrainingStatus($jobId);

            $state = $status['status'] ?? 'unknown';

            if (in_array($state, ['pending', 'running'])) {
                // Job is still running — resume tracking
                $this->trainingJobId = $jobId;
                $this->isProcessing = true;
                $this->trainingProgress = (int) round($status['progress'] ?? 0);
                $this->trainingMessage = $status['message'] ?? 'Training sedang berjalan...';
                $this->statusMessage = "Training sedang berjalan ({$this->trainingProgress}%)...";
                $this->statusType = 'info';

                Log::info('Resumed training job tracking', ['job_id' => $jobId, 'progress' => $this->trainingProgress]);
            } elseif ($state === 'completed') {
                // Training completed while page was away
                $this->trainingJobId = $jobId;
                $this->storeTrainingResults();
                $this->statusType = 'success';

                Log::info('Training job completed while page was away', ['job_id' => $jobId]);
            } elseif ($state === 'failed') {
                $this->activeRun->update([
                    'status' => 'failed',
                    'error_message' => $status['error'] ?? $status['message'] ?? 'Training gagal',
                    'completed_at' => now(),
                ]);
                $this->activeRun->refresh();
                $this->statusMessage = 'Training gagal: ' . ($status['error'] ?? 'Unknown error');
                $this->statusType = 'error';

                Log::warning('Training job failed while page was away', ['job_id' => $jobId]);
            } elseif ($state === 'unreachable') {
                $this->trainingJobId = $jobId;
                $this->isProcessing = true;
                $this->statusMessage = 'FastAPI tidak dapat dihubungi. Status training tidak diketahui.';
                $this->statusType = 'warning';
            } else {
                $this->activeRun->update([
                    'status' => 'failed',
                    'error_message' => 'Job tidak ditemukan di FastAPI (mungkin service restart)',
                    'completed_at' => now(),
                ]);
                $this->activeRun->refresh();
                $this->statusMessage = 'Training job tidak ditemukan di FastAPI. Silakan jalankan ulang.';
                $this->statusType = 'error';

                Log::warning('Training job not found in FastAPI', ['job_id' => $jobId]);
            }
        }
    }

    protected function loadBestParamsFromMetadata(): void
    {
        $defaults = $this->getDefaultBertopicParams();
        $this->bertopicParams = $defaults;

        // Optional override from notebook metadata file if available.
        $path = base_path('../analysis/output/models/bertopic_best_model/metadata.json');
        if (!File::exists($path)) {
            return;
        }

        try {
            $json = json_decode(File::get($path), true);
            $best = $json['best_params'] ?? [];

            $this->bertopicParams['embedding_model'] = $json['embedding_model'] ?? $defaults['embedding_model'];
            $this->bertopicParams['min_topic_size'] = $best['min_topic_size'] ?? $defaults['min_topic_size'];
            $this->bertopicParams['nr_topics'] = $best['nr_topics'] ?? $defaults['nr_topics'];

            $this->bertopicParams['umap_params']['n_neighbors'] = $best['umap_n_neighbors'] ?? $defaults['umap_params']['n_neighbors'];
            $this->bertopicParams['umap_params']['n_components'] = $best['umap_n_components'] ?? $defaults['umap_params']['n_components'];
            $this->bertopicParams['umap_params']['min_dist'] = $best['umap_min_dist'] ?? $defaults['umap_params']['min_dist'];

            $this->bertopicParams['hdbscan_params']['min_cluster_size'] = $best['hdbscan_min_cluster_size'] ?? $defaults['hdbscan_params']['min_cluster_size'];
            $this->bertopicParams['hdbscan_params']['min_samples'] = $best['hdbscan_min_samples'] ?? $defaults['hdbscan_params']['min_samples'];

            $bestVectorizer = is_array($best['vectorizer'] ?? null) ? $best['vectorizer'] : [];
            $this->bertopicParams['n_gram_range'] = $bestVectorizer['ngram_range'] ?? $defaults['n_gram_range'];
            $this->bertopicParams['vectorizer_min_df'] = $bestVectorizer['min_df'] ?? $defaults['vectorizer_min_df'];
            $this->bertopicParams['vectorizer_max_df'] = $bestVectorizer['max_df'] ?? $defaults['vectorizer_max_df'];
        } catch (\Throwable $e) {
            $this->bertopicParams = $this->getDefaultBertopicParams();
        }
    }

    /**
     * Default config aligned with notebook pipeline.
     */
    protected function getDefaultBertopicParams(): array
    {
        return [
            'embedding_model' => 'denaya/indoSBERT-large',
            'min_topic_size' => 10,
            'nr_topics' => 'auto',
            'top_n_words' => 10,
            'n_gram_range' => [1, 2],
            'vectorizer_min_df' => 2,
            'vectorizer_max_df' => 0.95,
            'vectorizer_token_pattern' => '(?u)\\b\\w{3,}\\b',
            'coherence_type' => 'c_v',
            'coherence_tokenization' => 'vectorizer',
            'coherence_dict_no_below' => 3,
            'coherence_dict_no_above' => 0.95,
            'reduce_outliers' => true,
            'reduce_outliers_threshold_ctfidf' => 0.1,
            'reduce_outliers_use_distributions' => true,
            'reduce_outliers_threshold_distributions' => 0.05,
            'use_mmr_representation' => true,
            'mmr_diversity' => 0.3,
            'embedding_batch_size' => 16,
            'seed' => 42,
            'umap_params' => [
                'n_neighbors' => 75,
                'n_components' => 5,
                'min_dist' => 0.0,
                'metric' => 'cosine',
                'random_state' => 42,
            ],
            'hdbscan_params' => [
                'min_cluster_size' => 12,
                'min_samples' => 1,
                'cluster_selection_method' => 'eom',
            ],
        ];
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

        $this->activeRun = TopicModelRun::with(['topics.documentLinks.skripsi'])
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        $this->activeRunId = $this->activeRun?->id;
    }

    /**
     * Preview preprocessing step-by-step pada beberapa sampel.
     * Data TIDAK disimpan ke DB — hanya in-memory untuk tampilan.
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
        // PENTING: Tidak ada update ke DB untuk previewRows
    }

    /**
     * Step 1: Jalankan preprocessing di FastAPI (menghasilkan processed_data.csv).
     */
    public function runPreprocessing(): void
    {
        $this->isProcessing = true;
        $this->statusType = 'info';
        $this->statusMessage = 'Menjalankan preprocessing data...';

        /** @var int|null $userId */
        $userId = Auth::id();

        if ($userId === null) {
            $this->statusType = 'error';
            $this->statusMessage = 'Silakan login terlebih dahulu.';
            $this->isProcessing = false;
            return;
        }

        // Buat run baru — tanpa preprocessing_preview (sudah dihapus dari DB)
        $run = TopicModelRun::create([
            'user_id' => $userId,
            'model_type' => 'bertopic',
            'status' => 'preprocessing',
            'remove_stopwords' => $this->removeStopwords,
            'min_word_length' => $this->minWordLength,
            'language' => $this->language,
            'bertopic_params' => $this->bertopicParams ?: null,
            'started_at' => now(),
        ]);

        $this->activeRun = $run;
        $this->activeRunId = $run->id;

        $fastApi = app(FastApiService::class);
        $resp = $fastApi->startPreprocessing($run->id);

        if (!isset($resp['job_id'])) {
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

        $this->preprocessingJobId = (string) $resp['job_id'];

        // Assign job id ke run — now succeeds because of $fillable fix
        $run->update([
            'fastapi_preprocessing_job_id' => $this->preprocessingJobId,
        ]);

        Log::info('Preprocessing job started', [
            'run_id' => $run->id,
            'job_id' => $this->preprocessingJobId,
        ]);

        $this->dispatch('toast', type: 'info', message: 'Preprocessing dimulai di background...');
    }

    /**
     * Poll status preprocessing.
     */
    public function pollPreprocessingProgress(): void
    {
        if (!$this->preprocessingJobId || !$this->activeRun) {
            return;
        }

        $fastApi = app(FastApiService::class);
        $status = $fastApi->getPreprocessingStatus($this->preprocessingJobId);

        if (($status['status'] ?? '') === 'unreachable') {
            $this->preprocessingMessage = 'FastAPI tidak dapat dihubungi, mencoba lagi...';
            return;
        }

        $this->preprocessingProgress = (int) round($status['progress'] ?? 0);
        $this->preprocessingMessage = (string) ($status['message'] ?? '');

        $state = $status['status'] ?? null;

        if ($state === 'completed') {
            $this->activeRun->update([
                'status' => 'pending',
                'total_documents' => (int) ($status['processed'] ?? 0),
            ]);
            $this->activeRun->refresh();
            $this->isProcessing = false;
            $this->statusType = 'success';
            $this->statusMessage = 'Preprocessing selesai.';
            $this->dispatch('toast', type: 'success', message: 'Preprocessing selesai! Data siap untuk training.');
            $this->preprocessingJobId = '';
        } elseif ($state === 'failed') {
            $this->activeRun->update([
                'status' => 'failed',
                'error_message' => (string) ($status['error'] ?? $this->preprocessingMessage ?? 'Preprocessing gagal'),
                'completed_at' => now(),
            ]);
            $this->activeRun->refresh();

            $this->statusType = 'error';
            $this->statusMessage = 'Preprocessing gagal: ' . ($status['error'] ?? $this->preprocessingMessage);
            $this->isProcessing = false;
            $this->preprocessingJobId = '';
        } else {
            $this->isProcessing = true;
        }
    }

    /**
     * Cancel the active preprocessing job.
     */
    public function cancelPreprocessing(): void
    {
        if (!$this->preprocessingJobId || !$this->activeRun) {
            return;
        }

        try {
            $fastApi = app(FastApiService::class);
            $result = $fastApi->cancelPreprocessingJob($this->preprocessingJobId);

            if (($result['status'] ?? '') === 'cancelled') {
                $this->activeRun->update([
                    'status' => 'failed',
                    'error_message' => 'Dibatalkan oleh user',
                    'completed_at' => now(),
                ]);
                $this->activeRun->refresh();

                $this->statusMessage = 'Preprocessing berhasil dibatalkan.';
                $this->statusType = 'warning';
                $this->dispatch('toast', type: 'warning', message: 'Preprocessing berhasil dibatalkan.');
            } else {
                $this->statusMessage = 'Gagal membatalkan preprocessing: ' . ($result['message'] ?? 'Unknown error');
                $this->statusType = 'error';
                $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan preprocessing.');
            }
        } catch (\Exception $e) {
            $this->statusMessage = 'Error: ' . $e->getMessage();
            $this->statusType = 'error';
        } finally {
            $this->preprocessingJobId = '';
            $this->isProcessing = false;
        }
    }

    /**
     * Step 2: Mulai BERTopic training di FastAPI.
     */
    public function startTraining(): void
    {
        if (!$this->activeRun) {
            $this->statusType = 'error';
            $this->statusMessage = 'Jalankan preprocessing terlebih dahulu.';
            return;
        }

        if (!in_array($this->activeRun->status, ['pending', 'completed', 'failed'])) {
            $this->statusType = 'warning';
            $this->statusMessage = 'Training sudah berjalan atau preprocessing belum selesai.';
            return;
        }

        $this->isProcessing = true;
        $this->statusType = 'info';
        $this->statusMessage = 'Memulai training BERTopic...';

        $params = $this->bertopicParams;
        if (($params['nr_topics'] ?? null) === '') {
            $params['nr_topics'] = null;
        }

        // Keep DB snapshot aligned with what we send to FastAPI
        $this->activeRun->update([
            'bertopic_params' => $params ?: null,
        ]);

        $fastApi = app(FastApiService::class);
        $resp = $fastApi->startBerTopicTraining(
            bertopicParams: $params,
            description: 'BERTopic run dari dashboard Jurusan',
        );

        if (!isset($resp['job_id'])) {
            $this->statusType = 'error';
            $this->statusMessage = $resp['message'] ?? 'Gagal memulai training.';
            $this->isProcessing = false;
            return;
        }

        $this->trainingJobId = (string) $resp['job_id'];

        $this->activeRun->update([
            'status' => 'training',
            'fastapi_training_job_id' => $this->trainingJobId,
        ]);

        Log::info('Training job started', [
            'run_id' => $this->activeRun->id,
            'job_id' => $this->trainingJobId,
        ]);

        $this->dispatch('toast', type: 'info', message: 'Training BERTopic dimulai di background...');
    }

    /**
     * Download the trained model archive (tar.gz) via Laravel proxy.
     */
    public function downloadModel(): mixed
    {
        if (!$this->activeRun || $this->activeRun->status !== 'completed') {
            $this->statusType = 'warning';
            $this->statusMessage = 'Belum ada model yang selesai untuk di-download.';
            return null;
        }

        $jobId = (string) ($this->activeRun->fastapi_training_job_id ?? '');
        if ($jobId === '') {
            $this->statusType = 'error';
            $this->statusMessage = 'Job ID training tidak ditemukan. Pastikan training dilakukan via FastAPI.';
            return null;
        }

        return redirect()->route('jurusan.topic-modeling.model.download', ['jobId' => $jobId]);
    }

    /**
     * Re-test the trained model against the existing dataset in DB.
     * Compares metrics/keywords with the stored training results.
     */
    public function testModelWithDataset(): void
    {
        if (!$this->activeRun || $this->activeRun->status !== 'completed') {
            $this->statusType = 'warning';
            $this->statusMessage = 'Belum ada hasil training yang bisa di-test.';
            return;
        }

        $jobId = (string) ($this->activeRun->fastapi_training_job_id ?? '');
        if ($jobId === '') {
            $this->statusType = 'error';
            $this->statusMessage = 'Job ID training tidak ditemukan.';
            return;
        }

        $this->modelTestLoading = true;

        try {
            $fastApi = app(FastApiService::class);
            $result = $fastApi->testModelWithDataset($jobId);

            $this->modelTestDatasetResult = $result;

            if (($result['status'] ?? '') === 'unreachable') {
                $this->statusType = 'warning';
                $this->statusMessage = $result['message'] ?? 'FastAPI tidak dapat dihubungi.';
                return;
            }

            if (($result['status'] ?? '') === 'error' || ($result['status'] ?? '') === 'not_found') {
                $this->statusType = 'error';
                $this->statusMessage = $result['message'] ?? 'Gagal melakukan test model.';
                return;
            }

            $keywordRatio = (float) ($result['same']['keyword_match_ratio'] ?? 0);
            $isSameCoherence = $result['same']['coherence_cv'] ?? null;
            $isSameDiversity = $result['same']['topic_diversity'] ?? null;

            $sameLabel = ($isSameCoherence === true && $isSameDiversity === true)
                ? '✅ Sama (metrics match)'
                : '⚠️ Berbeda (metrics berubah)';

            $this->statusType = ($isSameCoherence === true && $isSameDiversity === true) ? 'success' : 'warning';
            $this->statusMessage = $sameLabel . sprintf(' | Keyword match: %.0f%%', $keywordRatio * 100);
        } catch (\Throwable $e) {
            Log::error('Model test-dataset failed', ['error' => $e->getMessage()]);
            $this->statusType = 'error';
            $this->statusMessage = 'Gagal melakukan test model: ' . $e->getMessage();
        } finally {
            $this->modelTestLoading = false;
        }
    }

    /**
     * Poll status training dan simpan hasil ke DB ketika selesai.
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
            $this->activeRun->refresh();

            $this->statusType = 'error';
            $this->statusMessage = 'Training gagal: ' . ($status['error'] ?? $this->trainingMessage);
            $this->isProcessing = false;
            $this->trainingJobId = '';
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

        $metrics = $results['metrics'] ?? [];
        $coherence = isset($metrics['coherence_cv']) ? (float) $metrics['coherence_cv'] : null;
        $diversity = isset($metrics['topic_diversity']) ? (float) $metrics['topic_diversity'] : null;
        if ($coherence !== null && !is_finite($coherence)) {
            $coherence = null;
        }
        if ($diversity !== null && !is_finite($diversity)) {
            $diversity = null;
        }

        $this->activeRun->update([
            'status' => 'completed',
            'num_topics' => (int) ($results['num_topics'] ?? 0),
            'num_outliers' => (int) ($results['num_outliers'] ?? 0),
            'coherence_cv' => $coherence,
            'topic_diversity' => $diversity,
            'training_duration_seconds' => isset($results['training_duration_seconds']) ? (float) $results['training_duration_seconds'] : null,
            'model_path' => (string) ($results['model_path'] ?? ''),
            'completed_at' => now(),
        ]);

        // Simpan topik ke tabel topic_model_topics
        TopicModelTopicDocument::query()
            ->where('topic_model_run_id', $this->activeRun->id)
            ->delete();

        TopicModelTopic::query()
            ->where('topic_model_run_id', $this->activeRun->id)
            ->delete();

        $topics = $results['topic_info'] ?? [];
        $topicIdToRowId = [];
        foreach ($topics as $t) {
            $topicRow = TopicModelTopic::updateOrCreate(
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

            $topicIdToRowId[(int) ($t['topic_id'] ?? 0)] = $topicRow->id;
        }

        // Simpan mapping topic -> list skripsi agar bisa ditelusuri per topik di UI.
        $documentTopics = $results['document_topics'] ?? [];
        if (is_array($documentTopics) && !empty($documentTopics)) {
            $now = now();
            $rows = [];

            foreach ($documentTopics as $item) {
                $topicId = (int) ($item['topic_id'] ?? -1);
                $skripsiId = (int) ($item['skripsi_id'] ?? 0);

                if ($topicId < 0 || $skripsiId <= 0) {
                    continue;
                }

                if (!isset($topicIdToRowId[$topicId])) {
                    continue;
                }

                $rows[] = [
                    'topic_model_run_id' => $this->activeRun->id,
                    'topic_model_topic_id' => $topicIdToRowId[$topicId],
                    'topic_id' => $topicId,
                    'skripsi_id' => $skripsiId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                TopicModelTopicDocument::query()->upsert(
                    $rows,
                    ['topic_model_run_id', 'skripsi_id'],
                    ['topic_model_topic_id', 'topic_id', 'updated_at']
                );
            }
        }

        $this->activeRun->refresh();
        $this->activeRun->load(['topics.documentLinks.skripsi']);

        $this->statusType = 'success';
        $this->statusMessage = sprintf(
            'Training selesai! %d topik ditemukan. Coherence: %.4f | Diversity: %.4f',
            $this->activeRun->num_topics ?? 0,
            $this->activeRun->coherence_cv ?? 0,
            $this->activeRun->topic_diversity ?? 0,
        );

        $this->dispatch('toast', type: 'success', message: '✅ Training BERTopic selesai!');
        $this->trainingJobId = '';
    }

    public function render()
    {
        /** @var int|null $userId */
        $userId = Auth::id();

        if ($userId === null) {
            return view('livewire.jurusan.topic-modeling-manager', [
                'latestRuns' => collect(),
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
