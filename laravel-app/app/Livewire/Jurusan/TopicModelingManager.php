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

    // BERTopic params (default mengikuti hasil tuning terbaik terbaru)
    public array $bertopicParams = [];
    public array $ldaParams = [];
    public string $modelType = 'bertopic';

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
    public array $preprocessingDroppedReport = [];

    // Preview data (in-memory only, tidak disimpan ke DB)
    public array $previewRows = [];

    // Dataset readiness (from FastAPI)
    public array $datasetSummary = [];

    // Model utilities (download + test)
    public bool $modelTestLoading = false;
    public array $modelTestDatasetResult = [];

    // Confirm modal states for resource-intensive actions
    public bool $showRunPreprocessingConfirm = false;
    public bool $showStartTrainingConfirm = false;
    public bool $showTestModelWithDatasetConfirm = false;
    public bool $showCancelPreprocessingConfirm = false;

    // Show preprocessed texts stored in DB (skripsi.cleaned_text / skripsi.processed_text)
    public array $dbPreprocessedRows = [];
    public int $dbPreprocessedLimit = 20;
    public bool $dbPreprocessedHasMore = false;
    public bool $dbPreprocessedLoading = false;

    // Topic mapping modal state
    public bool $showTopicMappingsModal = false;
    public ?int $selectedTopicModalTopicId = null;
    public array $selectedTopicModalTopWords = [];
    public array $selectedTopicModalDocs = [];

    public function mount(): void
    {
        $this->checkApiStatus();
        $this->loadLatestRun();
        $this->loadTrainingParams();
        $this->resumeActiveJobs();
        $this->loadPreprocessingDroppedReport();
        $this->buildPreview();
        $this->loadDatasetSummary();
        $this->loadDbPreprocessedRows();
    }

    /**
     * Load BERTopic training params with precedence:
     * 1) active run snapshot (topic_model_runs.bertopic_params)
     * 2) user default setting (topic_model_settings.bertopic_params / lda_params)
     * 3) metadata.json from analysis output / fallback default
     */
    protected function loadTrainingParams(): void
    {
        $hasBerTopicParams = false;
        $hasLdaParams = false;

        // 1) Active run snapshot
        if ($this->activeRun && is_array($this->activeRun->bertopic_params) && !empty($this->activeRun->bertopic_params)) {
            $this->bertopicParams = $this->activeRun->bertopic_params;
            $hasBerTopicParams = true;
        }

        if ($this->activeRun && is_array($this->activeRun->lda_params) && !empty($this->activeRun->lda_params)) {
            $this->ldaParams = $this->activeRun->lda_params;
            $hasLdaParams = true;
        }

        if ($this->activeRun && in_array($this->activeRun->model_type, ['bertopic', 'lda'], true)) {
            $this->modelType = (string) $this->activeRun->model_type;
        }

        /** @var int|null $userId */
        $userId = Auth::id();

        // 2) User default setting
        if ($userId !== null) {
            $setting = TopicModelSetting::query()->where('user_id', $userId)->first();
            if (!$hasBerTopicParams && $setting && is_array($setting->bertopic_params) && !empty($setting->bertopic_params)) {
                $this->bertopicParams = $setting->bertopic_params;
                $hasBerTopicParams = true;
            }

            if (!$hasLdaParams && $setting && is_array($setting->lda_params) && !empty($setting->lda_params)) {
                $this->ldaParams = $setting->lda_params;
                $hasLdaParams = true;
            }
        }

        // 3) Notebook artifacts/default
        $currentLdaParams = $this->ldaParams;
        if (!$hasBerTopicParams) {
            $this->loadBestParamsFromMetadata();
        }

        if ($hasLdaParams && !empty($currentLdaParams)) {
            $this->ldaParams = $currentLdaParams;
        }

        if (empty($this->ldaParams)) {
            $this->ldaParams = $this->getDefaultLdaParams();
        }

        $this->bertopicParams = $this->normalizeBertopicParams($this->bertopicParams);
        $this->ldaParams = $this->normalizeLdaParams($this->ldaParams);
    }

    /**
     * Persist current BERTopic + LDA params as user's default in DB.
     */
    public function saveTrainingParams(): void
    {
        /** @var int|null $userId */
        $userId = Auth::id();
        if ($userId === null) {
            $this->dispatch('toast', type: 'error', message: 'Silakan login terlebih dahulu.');
            return;
        }

        $this->bertopicParams = $this->normalizeBertopicParams($this->bertopicParams);
        $this->ldaParams = $this->normalizeLdaParams($this->ldaParams);
        $bertopicParamsForStorage = $this->compactBertopicParamsForStorage($this->bertopicParams);
        $ldaParamsForStorage = $this->compactLdaParamsForStorage($this->ldaParams);

        TopicModelSetting::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'bertopic_params' => $bertopicParamsForStorage ?: null,
                'lda_params' => $ldaParamsForStorage ?: null,
            ],
        );

        // If there is an active run that hasn't finished, keep its snapshot in sync
        if ($this->activeRun && in_array($this->activeRun->status, ['pending', 'preprocessing', 'failed'])) {
            $this->activeRun->update([
                'bertopic_params' => $bertopicParamsForStorage ?: null,
                'lda_params' => $ldaParamsForStorage ?: null,
            ]);
            $this->activeRun->refresh();
        }

        $this->dispatch('toast', type: 'success', message: 'Parameter training BERTopic & LDA tersimpan di database.');
    }

    public function resetTrainingParamsToNotebookBest(): void
    {
        $this->loadBestParamsFromMetadata();
        $this->bertopicParams = $this->normalizeBertopicParams($this->bertopicParams);
        $this->ldaParams = $this->normalizeLdaParams($this->ldaParams);
        $bertopicParamsForStorage = $this->compactBertopicParamsForStorage($this->bertopicParams);
        $ldaParamsForStorage = $this->compactLdaParamsForStorage($this->ldaParams);

        if ($this->activeRun && in_array($this->activeRun->status, ['pending', 'preprocessing', 'failed'])) {
            $this->activeRun->update([
                'bertopic_params' => $bertopicParamsForStorage ?: null,
                'lda_params' => $ldaParamsForStorage ?: null,
            ]);
            $this->activeRun->refresh();
        }

        $this->dispatch('toast', type: 'success', message: 'Parameter di-reset ke best params eksperimen terbaru.');
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

        if ($this->activeRun->status === 'preprocessing' && empty($this->activeRun->fastapi_preprocessing_job_id)) {
            $this->isProcessing = true;
            $this->preprocessingMessage = 'Preprocessing masih berjalan. Menunggu sinkronisasi Job ID...';
            $this->statusMessage = 'Preprocessing sedang berjalan. Menunggu sinkronisasi status dari FastAPI.';
            $this->statusType = 'warning';
            return;
        }

        if ($this->activeRun->status === 'training' && empty($this->activeRun->fastapi_training_job_id)) {
            $this->isProcessing = true;
            $this->trainingMessage = 'Training masih berjalan. Menunggu sinkronisasi Job ID...';
            $this->statusMessage = 'Training sedang berjalan. Menunggu sinkronisasi status dari FastAPI.';
            $this->statusType = 'warning';
            return;
        }

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
                $this->loadPreprocessingDroppedReport($jobId);
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
                $this->loadDatasetSummary();
                $this->loadDbPreprocessedRows();
                $this->loadPreprocessingDroppedReport($jobId);
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
                $this->loadPreprocessingDroppedReport($jobId);
                $this->statusMessage = 'Preprocessing gagal: ' . ($status['error'] ?? 'Unknown error');
                $this->statusType = 'error';

                Log::warning('Preprocessing job failed while page was away', ['job_id' => $jobId]);
            } elseif (in_array($state, ['unreachable', 'error', 'not_found', 'unknown'], true)) {
                // Keep tracking when status sync is uncertain to avoid false-negative failure on refresh.
                $this->preprocessingJobId = $jobId;
                $this->isProcessing = true;
                $this->preprocessingMessage = $status['message'] ?? ($this->preprocessingMessage ?: 'Menunggu sinkronisasi status preprocessing...');
                $this->statusMessage = 'Preprocessing masih berjalan atau menunggu sinkronisasi status dari FastAPI.';
                $this->statusType = 'warning';
            } else {
                $this->preprocessingJobId = $jobId;
                $this->isProcessing = true;
                $this->preprocessingMessage = $status['message'] ?? ($this->preprocessingMessage ?: 'Status preprocessing belum sinkron.');
                $this->statusMessage = 'Preprocessing masih dipantau. Menunggu pembaruan status berikutnya.';
                $this->statusType = 'warning';
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
            } elseif (in_array($state, ['unreachable', 'error', 'not_found', 'unknown'], true)) {
                $this->trainingJobId = $jobId;
                $this->isProcessing = true;
                $this->trainingMessage = $status['message'] ?? ($this->trainingMessage ?: 'Menunggu sinkronisasi status training...');
                $this->statusMessage = 'Training masih berjalan atau menunggu sinkronisasi status dari FastAPI.';
                $this->statusType = 'warning';
            } else {
                $this->trainingJobId = $jobId;
                $this->isProcessing = true;
                $this->trainingMessage = $status['message'] ?? ($this->trainingMessage ?: 'Status training belum sinkron.');
                $this->statusMessage = 'Training masih dipantau. Menunggu pembaruan status berikutnya.';
                $this->statusType = 'warning';
            }
        }
    }

    public function loadPreprocessingDroppedReport(?string $jobId = null): void
    {
        $targetJobId = trim((string) ($jobId
            ?? ($this->preprocessingJobId !== '' ? $this->preprocessingJobId : ($this->activeRun?->fastapi_preprocessing_job_id ?? ''))));

        if ($targetJobId === '') {
            $this->preprocessingDroppedReport = [];
            return;
        }

        $fastApi = app(FastApiService::class);
        $report = $fastApi->getPreprocessingDroppedReport($targetJobId);

        if (in_array(($report['status'] ?? ''), ['error', 'not_found', 'unreachable'], true)) {
            return;
        }

        $this->preprocessingDroppedReport = [
            'job_id' => (string) ($report['job_id'] ?? $targetJobId),
            'status' => (string) ($report['status'] ?? 'unknown'),
            'dropped_summary' => is_array($report['dropped_summary'] ?? null) ? $report['dropped_summary'] : [],
            'dropped_records_total' => (int) ($report['dropped_records_total'] ?? 0),
            'dropped_records_sample' => is_array($report['dropped_records_sample'] ?? null) ? $report['dropped_records_sample'] : [],
        ];
    }

    protected function loadBestParamsFromMetadata(): void
    {
        $defaults = $this->getDefaultBertopicParams();
        $this->bertopicParams = $defaults;
        $this->ldaParams = $this->getDefaultLdaParams();

        // Prioritas utama: payload tuning terbaru dari notebook baru.
        if ($this->loadBestParamsFromNotebookPayloads()) {
            return;
        }

        // Fallback lama: output clean notebook.
        $bestParamsCsv = base_path('../analysis/output_clean/csv/step6_best_params.csv');
        if (File::exists($bestParamsCsv)) {
            try {
                $rows = array_map('str_getcsv', file($bestParamsCsv));
                if (count($rows) > 1) {
                    $header = array_map('trim', $rows[0]);
                    $dataRows = array_slice($rows, 1);

                    foreach ($dataRows as $cols) {
                        if (count($cols) < 3) {
                            continue;
                        }

                        $row = array_combine($header, $cols);
                        $model = trim((string) ($row['model'] ?? ''));
                        $param = trim((string) ($row['param'] ?? ''));
                        $rawValue = $row['value'] ?? null;
                        $value = $this->parseBestParamValue($rawValue);

                        if ($model === 'BERTopic') {
                            if ($param === 'umap_n_neighbors') {
                                $this->bertopicParams['umap_params']['n_neighbors'] = (int) $value;
                            } elseif ($param === 'umap_n_components') {
                                $this->bertopicParams['umap_params']['n_components'] = (int) $value;
                            } elseif ($param === 'umap_min_dist') {
                                $this->bertopicParams['umap_params']['min_dist'] = (float) $value;
                            } elseif ($param === 'umap_metric') {
                                $this->bertopicParams['umap_params']['metric'] = (string) $value;
                            } elseif ($param === 'hdbscan_min_cluster_size') {
                                $this->bertopicParams['hdbscan_params']['min_cluster_size'] = (int) $value;
                            } elseif ($param === 'hdbscan_min_samples') {
                                $this->bertopicParams['hdbscan_params']['min_samples'] = (int) $value;
                            } elseif ($param === 'hdbscan_metric') {
                                $this->bertopicParams['hdbscan_params']['metric'] = (string) $value;
                            } elseif ($param === 'hdbscan_method') {
                                $this->bertopicParams['hdbscan_params']['cluster_selection_method'] = (string) $value;
                            } elseif ($param === 'min_topic_size') {
                                $this->bertopicParams['min_topic_size'] = (int) $value;
                            } elseif ($param === 'nr_topics') {
                                $this->bertopicParams['nr_topics'] = $value;
                            } elseif ($param === 'vectorizer_ngram_range' && is_array($value)) {
                                $this->bertopicParams['n_gram_range'] = $value;
                            } elseif ($param === 'vectorizer_min_df') {
                                $this->bertopicParams['vectorizer_min_df'] = is_int($value) || is_float($value)
                                    ? $value
                                    : (float) $value;
                            } elseif ($param === 'vectorizer_max_df') {
                                $this->bertopicParams['vectorizer_max_df'] = is_int($value) || is_float($value)
                                    ? $value
                                    : (float) $value;
                            }
                        }

                        if ($model === 'LDA') {
                            if ($param === 'num_topics') {
                                $this->ldaParams['num_topics'] = (int) $value;
                            } elseif ($param === 'passes') {
                                $this->ldaParams['passes'] = (int) $value;
                            } elseif ($param === 'iterations') {
                                $this->ldaParams['iterations'] = (int) $value;
                            } elseif ($param === 'alpha') {
                                $this->ldaParams['alpha'] = $value;
                            } elseif ($param === 'eta') {
                                $this->ldaParams['eta'] = $value;
                            }
                        }
                    }
                }

                return;
            } catch (\Throwable $e) {
                Log::warning('Failed to parse step6_best_params.csv, fallback to defaults', ['error' => $e->getMessage()]);
            }
        }

        // Fallback lama: metadata file jika masih dipakai.
        $legacyPath = base_path('../analysis/output/models/bertopic_best_model/metadata.json');
        if (!File::exists($legacyPath)) {
            return;
        }

        try {
            $json = json_decode(File::get($legacyPath), true);
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
            $this->ldaParams = $this->getDefaultLdaParams();
        }
    }

    /**
     * Load best params from latest notebook tuning artifacts.
     */
    protected function loadBestParamsFromNotebookPayloads(): bool
    {
        $artifactDir = base_path('../analysis/output/bertopic_tuning');
        if (!File::isDirectory($artifactDir)) {
            return false;
        }

        $bertopicPayload = null;
        $summaryPayload = $this->loadLatestJsonArtifact($artifactDir . '/bertopic_tuning_summary_*.json');
        if (is_array($summaryPayload['best_laravel_payload'] ?? null)) {
            $bertopicPayload = $summaryPayload['best_laravel_payload'];
        }

        if (!is_array($bertopicPayload) || empty($bertopicPayload)) {
            $bertopicPayload = $this->loadLatestJsonArtifact($artifactDir . '/bertopic_laravel_payload_*.json');
        }

        $ldaPayload = $this->loadLatestJsonArtifact($artifactDir . '/lda_laravel_payload_*.json');

        $hasLoaded = false;

        if (is_array($bertopicPayload) && !empty($bertopicPayload)) {
            $this->bertopicParams = array_replace_recursive($this->bertopicParams, $bertopicPayload);
            $hasLoaded = true;
        }

        if (is_array($ldaPayload) && !empty($ldaPayload)) {
            $this->ldaParams = array_replace($this->ldaParams, $ldaPayload);
            $hasLoaded = true;
        }

        return $hasLoaded;
    }

    /**
     * Load latest JSON artifact by glob pattern (descending filename order).
     */
    protected function loadLatestJsonArtifact(string $pattern): ?array
    {
        $files = File::glob($pattern);
        if (!is_array($files) || empty($files)) {
            return null;
        }

        usort($files, static fn(string $a, string $b): int => strcmp(basename($b), basename($a)));

        foreach ($files as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            try {
                $decoded = json_decode(File::get($path), true);
                if (is_array($decoded) && !empty($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Parse value from legacy step6_best_params.csv into scalar/array/null.
     */
    protected function parseBestParamValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        $lower = strtolower($text);

        if ($lower === 'none' || $lower === 'null' || $text === '') {
            return null;
        }

        if ($lower === 'auto') {
            return 'auto';
        }

        if (preg_match('/^\(\s*\d+\s*,\s*\d+\s*\)$/', $text) === 1) {
            $clean = trim($text, '() ');
            $parts = array_map('trim', explode(',', $clean));
            return [intval($parts[0]), intval($parts[1])];
        }

        if (is_numeric($text)) {
            return str_contains($text, '.') ? (float) $text : (int) $text;
        }

        return $text;
    }

    /**
     * Default config aligned with notebook pipeline.
     */
    protected function getDefaultBertopicParams(): array
    {
        return [
            'embedding_model' => 'denaya/indoSBERT-large',
            'min_topic_size' => 12,
            'nr_topics' => 'auto',
            'top_n_words' => 10,
            'n_gram_range' => [1, 2],
            'vectorizer_min_df' => 2,
            'vectorizer_max_df' => 0.95,
            'vectorizer_token_pattern' => '(?u)\\b\\w{3,}\\b',
            'vectorizer_fallback_min_df' => 1,
            'vectorizer_fallback_max_df' => 1.0,
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
                'n_neighbors' => 40,
                'n_components' => 5,
                'min_dist' => 0.0,
                'metric' => 'cosine',
                'random_state' => 42,
            ],
            'hdbscan_params' => [
                'min_cluster_size' => 16,
                'min_samples' => 1,
                'metric' => 'euclidean',
                'cluster_selection_method' => 'eom',
            ],
        ];
    }

    protected function getDefaultLdaParams(): array
    {
        return [
            'num_topics' => 12,
            'passes' => 20,
            'iterations' => 300,
            'chunksize' => 100,
            'random_state' => 42,
            'alpha' => 'asymmetric',
            'eta' => null,
            'no_below' => 2,
            'no_above' => 0.95,
        ];
    }

    protected function normalizeBertopicParams(array $params): array
    {
        $defaults = $this->getDefaultBertopicParams();
        $params = array_replace_recursive($defaults, $params);

        // Keep advanced/internal params stable and tune only official BERTopic params.
        $params['embedding_model'] = $defaults['embedding_model'];
        $params['vectorizer_min_df'] = $defaults['vectorizer_min_df'];
        $params['vectorizer_max_df'] = $defaults['vectorizer_max_df'];
        $params['vectorizer_token_pattern'] = $defaults['vectorizer_token_pattern'];
        $params['vectorizer_fallback_min_df'] = $defaults['vectorizer_fallback_min_df'];
        $params['vectorizer_fallback_max_df'] = $defaults['vectorizer_fallback_max_df'];
        $params['coherence_type'] = $defaults['coherence_type'];
        $params['coherence_tokenization'] = $defaults['coherence_tokenization'];
        $params['coherence_dict_no_below'] = $defaults['coherence_dict_no_below'];
        $params['coherence_dict_no_above'] = $defaults['coherence_dict_no_above'];
        $params['reduce_outliers'] = $defaults['reduce_outliers'];
        $params['reduce_outliers_threshold_ctfidf'] = $defaults['reduce_outliers_threshold_ctfidf'];
        $params['reduce_outliers_use_distributions'] = $defaults['reduce_outliers_use_distributions'];
        $params['reduce_outliers_threshold_distributions'] = $defaults['reduce_outliers_threshold_distributions'];
        $params['use_mmr_representation'] = $defaults['use_mmr_representation'];
        $params['mmr_diversity'] = $defaults['mmr_diversity'];
        $params['embedding_batch_size'] = $defaults['embedding_batch_size'];
        $params['seed'] = $defaults['seed'];

        // Official BERTopic parameters to tune: top_n_words, n_gram_range, min_topic_size, nr_topics.
        $params['min_topic_size'] = $this->toInt($params['min_topic_size'] ?? null, 2, null, $defaults['min_topic_size']);
        $params['nr_topics'] = $this->normalizeNrTopics($params['nr_topics'] ?? null, $defaults['nr_topics']);
        $params['top_n_words'] = $this->toInt($params['top_n_words'] ?? null, 1, null, $defaults['top_n_words']);

        $nGramRange = is_array($params['n_gram_range'] ?? null) ? $params['n_gram_range'] : $defaults['n_gram_range'];
        $nGramMin = $this->toInt($nGramRange[0] ?? null, 1, null, $defaults['n_gram_range'][0]);
        $nGramMax = $this->toInt($nGramRange[1] ?? null, $nGramMin, null, max($nGramMin, (int) $defaults['n_gram_range'][1]));
        $params['n_gram_range'] = [$nGramMin, $nGramMax];

        $umapDefaults = $defaults['umap_params'];
        $umap = is_array($params['umap_params'] ?? null) ? $params['umap_params'] : [];
        $umapMetric = trim((string) ($umap['metric'] ?? $umapDefaults['metric']));
        if ($umapMetric === '') {
            $umapMetric = (string) $umapDefaults['metric'];
        }
        $params['umap_params'] = [
            'n_neighbors' => $this->toInt($umap['n_neighbors'] ?? null, 2, null, $umapDefaults['n_neighbors']),
            'n_components' => $this->toInt($umap['n_components'] ?? null, 2, null, $umapDefaults['n_components']),
            'min_dist' => (float) $umapDefaults['min_dist'],
            'metric' => $umapMetric,
            'random_state' => (int) $umapDefaults['random_state'],
        ];

        $hdbscanDefaults = $defaults['hdbscan_params'];
        $hdbscan = is_array($params['hdbscan_params'] ?? null) ? $params['hdbscan_params'] : [];
        $hdbscanMetric = trim((string) ($hdbscan['metric'] ?? $hdbscanDefaults['metric']));
        if ($hdbscanMetric === '') {
            $hdbscanMetric = (string) $hdbscanDefaults['metric'];
        }
        $params['hdbscan_params'] = [
            'min_cluster_size' => $this->toInt(
                $hdbscan['min_cluster_size'] ?? null,
                2,
                null,
                $hdbscanDefaults['min_cluster_size']
            ),
            'min_samples' => $this->toOptionalInt($hdbscan['min_samples'] ?? null, 1, (int) $hdbscanDefaults['min_samples']),
            'metric' => $hdbscanMetric,
            'cluster_selection_method' => (string) $hdbscanDefaults['cluster_selection_method'],
        ];

        return $params;
    }

    protected function normalizeLdaParams(array $params): array
    {
        $defaults = $this->getDefaultLdaParams();
        $params = array_replace($defaults, $params);

        $params['num_topics'] = $this->toInt($params['num_topics'] ?? null, 2, null, $defaults['num_topics']);
        $params['passes'] = $this->toInt($params['passes'] ?? null, 1, null, $defaults['passes']);
        $params['iterations'] = $this->toInt($params['iterations'] ?? null, 1, null, $defaults['iterations']);
        $params['chunksize'] = $this->toInt($params['chunksize'] ?? null, 1, null, $defaults['chunksize']);
        $params['random_state'] = $this->toInt($params['random_state'] ?? null, 0, null, $defaults['random_state']);
        $params['alpha'] = $this->normalizeAlphaEtaValue($params['alpha'] ?? null, false, $defaults['alpha']);
        $params['eta'] = $this->normalizeAlphaEtaValue($params['eta'] ?? null, true, $defaults['eta']);
        $params['no_below'] = $this->toInt($params['no_below'] ?? null, 1, null, $defaults['no_below']);
        $params['no_above'] = $this->toFloat($params['no_above'] ?? null, 0.0001, 1.0, (float) $defaults['no_above']);

        return $params;
    }

    protected function compactBertopicParamsForStorage(array $params): array
    {
        $normalized = $this->normalizeBertopicParams($params);

        return [
            'min_topic_size' => (int) $normalized['min_topic_size'],
            'nr_topics' => $normalized['nr_topics'],
            'top_n_words' => (int) $normalized['top_n_words'],
            'n_gram_range' => [
                (int) ($normalized['n_gram_range'][0] ?? 1),
                (int) ($normalized['n_gram_range'][1] ?? 2),
            ],
            'umap_params' => [
                'n_neighbors' => (int) ($normalized['umap_params']['n_neighbors'] ?? 40),
                'n_components' => (int) ($normalized['umap_params']['n_components'] ?? 5),
                'metric' => (string) ($normalized['umap_params']['metric'] ?? 'cosine'),
            ],
            'hdbscan_params' => [
                'min_cluster_size' => (int) ($normalized['hdbscan_params']['min_cluster_size'] ?? 16),
                'min_samples' => isset($normalized['hdbscan_params']['min_samples'])
                    ? ($normalized['hdbscan_params']['min_samples'] === null ? null : (int) $normalized['hdbscan_params']['min_samples'])
                    : 1,
                'metric' => (string) ($normalized['hdbscan_params']['metric'] ?? 'euclidean'),
            ],
        ];
    }

    protected function compactLdaParamsForStorage(array $params): array
    {
        $normalized = $this->normalizeLdaParams($params);

        return [
            'num_topics' => (int) $normalized['num_topics'],
            'passes' => (int) $normalized['passes'],
            'iterations' => (int) $normalized['iterations'],
            'chunksize' => (int) $normalized['chunksize'],
            'random_state' => (int) $normalized['random_state'],
            'alpha' => $normalized['alpha'],
            'eta' => $normalized['eta'],
            'no_below' => (int) $normalized['no_below'],
            'no_above' => (float) $normalized['no_above'],
        ];
    }

    protected function normalizeNrTopics(mixed $value, string|int|null $fallback = null): string|int|null
    {
        if ($value === null) {
            return $fallback;
        }

        if (is_int($value)) {
            return max(2, $value);
        }

        $text = trim((string) $value);
        $lower = strtolower($text);

        if ($text === '' || in_array($lower, ['null', 'none'], true)) {
            return null;
        }

        if ($lower === 'auto') {
            return 'auto';
        }

        if (is_numeric($text)) {
            return max(2, (int) round((float) $text));
        }

        return $fallback;
    }

    protected function normalizeAlphaEtaValue(mixed $value, bool $allowNull, string|float|null $fallback): string|float|null
    {
        if ($value === null) {
            return $allowNull ? null : $fallback;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        $lower = strtolower($text);

        if ($text === '' || in_array($lower, ['null', 'none'], true)) {
            return $allowNull ? null : $fallback;
        }

        if (in_array($lower, ['auto', 'symmetric', 'asymmetric'], true)) {
            return $lower;
        }

        if (is_numeric($text)) {
            return (float) $text;
        }

        return $fallback;
    }

    protected function toInt(mixed $value, int $min, ?int $max = null, ?int $fallback = null): int
    {
        $default = $fallback ?? $min;
        $num = is_numeric((string) $value) ? (int) round((float) $value) : $default;
        $num = max($min, $num);

        if ($max !== null) {
            $num = min($max, $num);
        }

        return $num;
    }

    protected function toOptionalInt(mixed $value, int $min, ?int $fallback = null): ?int
    {
        if ($value === null) {
            return $fallback;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return $fallback;
        }

        if (!is_numeric($text)) {
            return $fallback;
        }

        return max($min, (int) round((float) $text));
    }

    protected function toFloat(mixed $value, float $min, ?float $max = null, ?float $fallback = null): float
    {
        $default = $fallback ?? $min;
        $num = is_numeric((string) $value) ? (float) $value : $default;
        $num = max($min, $num);

        if ($max !== null) {
            $num = min($max, $num);
        }

        return $num;
    }

    protected function toNumeric(mixed $value, int|float $fallback): int|float
    {
        if (!is_numeric((string) $value)) {
            return $fallback;
        }

        $num = (float) $value;

        if (abs($num - round($num)) < 0.0000001) {
            return (int) round($num);
        }

        return $num;
    }

    protected function toBool(mixed $value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return $fallback;
        }

        $text = strtolower(trim((string) $value));
        if (in_array($text, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($text, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $fallback;
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
        try {
            $rows = TopicModelDataset::query()
                ->select(['skripsi_id', 'title', 'abstract', 'year'])
                ->whereNotNull('abstract')
                ->whereRaw('LENGTH(abstract) > 30')
                ->orderByDesc('skripsi_id')
                ->limit(5)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Failed to load preview from topic_model_datasets', ['error' => $e->getMessage()]);
            $this->previewRows = [];
            return;
        }

        $service = new \App\Services\LocalTextPreprocessor(
            removeStopwords: true,
            minWordLength: 3,
            language: 'indonesian',
        );

        $this->previewRows = $rows->map(function ($r) use ($service) {
            $raw = (string) $r->abstract;

            return [
                'id' => $r->skripsi_id,
                'year' => $r->year,
                'title' => (string) ($r->title ?? '-'),
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
    public function openRunPreprocessingConfirm(): void
    {
        if (($this->apiStatus['status'] ?? '') !== 'ok') {
            $this->statusType = 'warning';
            $this->statusMessage = 'FastAPI belum aktif. Tidak dapat memulai preprocessing.';
            return;
        }

        $this->showRunPreprocessingConfirm = true;
    }

    public function closeRunPreprocessingConfirm(): void
    {
        $this->showRunPreprocessingConfirm = false;
    }

    public function runPreprocessing(): void
    {
        $this->showRunPreprocessingConfirm = false;

        $this->isProcessing = true;
        $this->statusType = 'info';
        $this->statusMessage = 'Menjalankan preprocessing data...';
        $this->preprocessingDroppedReport = [];
        $this->bertopicParams = $this->normalizeBertopicParams($this->bertopicParams);
        $this->ldaParams = $this->normalizeLdaParams($this->ldaParams);
        $bertopicParamsForStorage = $this->compactBertopicParamsForStorage($this->bertopicParams);
        $ldaParamsForStorage = $this->compactLdaParamsForStorage($this->ldaParams);

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
            'model_type' => $this->modelType,
            'status' => 'preprocessing',
            'bertopic_params' => $bertopicParamsForStorage ?: null,
            'lda_params' => $ldaParamsForStorage ?: null,
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

        $this->loadPreprocessingDroppedReport($this->preprocessingJobId);

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
        $this->loadPreprocessingDroppedReport($this->preprocessingJobId);

        $state = $status['status'] ?? null;

        if ($state === 'completed') {
            $this->activeRun->update([
                'status' => 'pending',
                'total_documents' => (int) ($status['processed'] ?? 0),
            ]);
            $this->activeRun->refresh();
            $this->loadDatasetSummary();
            $this->loadDbPreprocessedRows();
            $this->isProcessing = false;
            $this->statusType = 'success';
            $this->statusMessage = 'Preprocessing selesai.';
            $this->loadPreprocessingDroppedReport((string) ($this->activeRun->fastapi_preprocessing_job_id ?? ''));
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
            $this->loadPreprocessingDroppedReport((string) ($this->activeRun->fastapi_preprocessing_job_id ?? ''));
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
        $this->showCancelPreprocessingConfirm = false;

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

    public function openCancelPreprocessingConfirm(): void
    {
        $this->showCancelPreprocessingConfirm = true;
    }

    public function closeCancelPreprocessingConfirm(): void
    {
        $this->showCancelPreprocessingConfirm = false;
    }

    /**
     * Step 2: Mulai BERTopic training di FastAPI.
     */
    public function openStartTrainingConfirm(): void
    {
        $this->showStartTrainingConfirm = true;
    }

    public function closeStartTrainingConfirm(): void
    {
        $this->showStartTrainingConfirm = false;
    }

    public function startTraining(): void
    {
        $this->showStartTrainingConfirm = false;

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
        $this->statusMessage = sprintf('Memulai training %s...', strtoupper($this->modelType));
        $this->bertopicParams = $this->normalizeBertopicParams($this->bertopicParams);
        $this->ldaParams = $this->normalizeLdaParams($this->ldaParams);
        $bertopicParamsForStorage = $this->compactBertopicParamsForStorage($this->bertopicParams);
        $ldaParamsForStorage = $this->compactLdaParamsForStorage($this->ldaParams);

        $fastApi = app(FastApiService::class);
        if ($this->modelType === 'lda') {
            $params = $this->ldaParams;

            $this->activeRun->update([
                'model_type' => 'lda',
                'lda_params' => $ldaParamsForStorage ?: null,
            ]);

            $resp = $fastApi->startLdaTraining(
                ldaParams: $params,
                description: 'LDA run dari dashboard Jurusan',
            );
        } else {
            $params = $this->bertopicParams;

            $this->activeRun->update([
                'model_type' => 'bertopic',
                'bertopic_params' => $bertopicParamsForStorage ?: null,
            ]);

            $resp = $fastApi->startBerTopicTraining(
                bertopicParams: $params,
                description: 'BERTopic run dari dashboard Jurusan',
            );
        }

        if (!isset($resp['job_id'])) {
            $this->statusType = 'error';
            $this->statusMessage = $resp['message'] ?? 'Gagal memulai training.';
            $this->isProcessing = false;
            return;
        }

        $this->trainingJobId = (string) $resp['job_id'];

        $this->activeRun->update([
            'status' => 'training',
            'model_type' => $this->modelType,
            'fastapi_training_job_id' => $this->trainingJobId,
        ]);

        Log::info('Training job started', [
            'run_id' => $this->activeRun->id,
            'job_id' => $this->trainingJobId,
        ]);

        $this->dispatch('toast', type: 'info', message: sprintf('Training %s dimulai di background...', strtoupper($this->modelType)));
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
    public function openTestModelWithDatasetConfirm(): void
    {
        $this->showTestModelWithDatasetConfirm = true;
    }

    public function closeTestModelWithDatasetConfirm(): void
    {
        $this->showTestModelWithDatasetConfirm = false;
    }

    public function testModelWithDataset(): void
    {
        $this->showTestModelWithDatasetConfirm = false;

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
        $coherenceRaw = $metrics['coherence_cv'] ?? $results['coherence_cv'] ?? null;
        $diversityRaw = $metrics['topic_diversity'] ?? $results['topic_diversity'] ?? null;

        $coherence = is_numeric($coherenceRaw) ? (float) $coherenceRaw : null;
        $diversity = is_numeric($diversityRaw) ? (float) $diversityRaw : null;

        if ($coherence !== null && !is_finite($coherence)) {
            $coherence = null;
        }
        if ($diversity !== null && !is_finite($diversity)) {
            $diversity = null;
        }

        $numTopics = 0;
        if (isset($results['num_topics']) && is_numeric($results['num_topics'])) {
            $numTopics = (int) $results['num_topics'];
        } elseif (isset($metrics['num_topics']) && is_numeric($metrics['num_topics'])) {
            $numTopics = (int) $metrics['num_topics'];
        }

        $totalDocuments = 0;
        $documentTopics = $results['document_topics'] ?? [];
        if (is_array($documentTopics) && !empty($documentTopics)) {
            $totalDocuments = count($documentTopics);
        } elseif (($this->activeRun->total_documents ?? 0) > 0) {
            $totalDocuments = (int) $this->activeRun->total_documents;
        }

        $this->activeRun->update([
            'status' => 'completed',
            'total_documents' => $totalDocuments,
            'num_topics' => $numTopics,
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
        $modelLabel = strtoupper((string) ($this->activeRun->model_type ?? $this->modelType));
        $this->statusMessage = sprintf(
            'Training %s selesai! %d topik ditemukan. Coherence: %.4f | Diversity: %.4f',
            $modelLabel,
            $this->activeRun->num_topics ?? 0,
            $this->activeRun->coherence_cv ?? 0,
            $this->activeRun->topic_diversity ?? 0,
        );

        $this->dispatch('toast', type: 'success', message: sprintf('✅ Training %s selesai!', $modelLabel));
        $this->trainingJobId = '';
    }

    public function openTopicMappingsModal(int $topicRowId): void
    {
        if (!$this->activeRun || $this->activeRun->status !== 'completed') {
            return;
        }

        $topic = TopicModelTopic::query()
            ->with(['documentLinks.skripsi:id,title,year'])
            ->where('id', $topicRowId)
            ->where('topic_model_run_id', $this->activeRun->id)
            ->first();

        if (!$topic) {
            $this->dispatch('toast', type: 'error', message: 'Topik tidak ditemukan.');
            return;
        }

        $docs = $topic->documentLinks
            ->sortBy('skripsi_id')
            ->map(function (TopicModelTopicDocument $link) {
                return [
                    'skripsi_id' => (int) $link->skripsi_id,
                    'title' => (string) ($link->skripsi?->title ?? '-'),
                    'year' => $link->skripsi?->year,
                ];
            })
            ->values()
            ->toArray();

        $this->selectedTopicModalTopicId = (int) $topic->topic_id;
        $this->selectedTopicModalTopWords = is_array($topic->top_words) ? array_slice($topic->top_words, 0, 10) : [];
        $this->selectedTopicModalDocs = $docs;
        $this->showTopicMappingsModal = true;
    }

    public function closeTopicMappingsModal(): void
    {
        $this->showTopicMappingsModal = false;
        $this->selectedTopicModalTopicId = null;
        $this->selectedTopicModalTopWords = [];
        $this->selectedTopicModalDocs = [];
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
