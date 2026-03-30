<?php

namespace App\Livewire\Jurusan;

use App\Models\Skripsi;
use App\Models\TopicModelRun;
use App\Models\TopicModelTopic;
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

    // Preprocessing settings (BERTopic = soft clean, LDA = full clean)
    public bool $removeStopwords = false;
    public int $minWordLength = 3;
    public string $language = 'indonesian';

    // BERTopic params (best preset dari eksperimen BT_031)
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

    public function mount(): void
    {
        $this->loadBestParamsFromMetadata();
        $this->loadLatestRun();
        $this->resumeActiveJobs();
        $this->buildPreview();
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
        // Coba baca dari analysis/output/models/bertopic_best_model/metadata.json
        $path = base_path('../analysis/output/models/bertopic_best_model/metadata.json');
        if (!File::exists($path)) {
            // Gunakan best params dari eksperimen BT_031
            $this->bertopicParams = $this->getDefaultBertopicParams();
            return;
        }

        try {
            $json = json_decode(File::get($path), true);
            $best = $json['best_params'] ?? [];

            $this->bertopicParams = [
                'embedding_model'     => $json['embedding_model'] ?? 'denaya/indoSBERT-large',
                'min_topic_size'      => $best['min_topic_size'] ?? 5,
                'nr_topics'           => 10,
                'top_n_words'         => 10,
                'n_gram_range'        => [1, 2],
                'embedding_batch_size'=> 16,
                'seed'                => 42,
                'umap_params' => [
                    'n_neighbors'  => $best['umap_n_neighbors'] ?? 5,
                    'n_components' => $best['umap_n_components'] ?? 5,
                    'min_dist'     => $best['umap_min_dist'] ?? 0.0,
                    'metric'       => 'cosine',
                    'random_state' => 42,
                ],
                'hdbscan_params' => [
                    'min_cluster_size'       => $best['hdbscan_min_cluster_size'] ?? 5,
                    'min_samples'            => 1,
                    'cluster_selection_method' => 'eom',
                ],
            ];
        } catch (\Throwable $e) {
            $this->bertopicParams = $this->getDefaultBertopicParams();
        }
    }

    /**
     * Best config dari eksperimen BT_031 (Coherence=0.625, Diversity=0.933).
     */
    protected function getDefaultBertopicParams(): array
    {
        return [
            'embedding_model'     => 'denaya/indoSBERT-large',
            'min_topic_size'      => 5,
            'nr_topics'           => 10,
            'top_n_words'         => 10,
            'n_gram_range'        => [1, 2],
            'embedding_batch_size'=> 16,
            'seed'                => 42,
            'umap_params' => [
                'n_neighbors'  => 5,
                'n_components' => 5,
                'min_dist'     => 0.0,
                'metric'       => 'cosine',
                'random_state' => 42,
            ],
            'hdbscan_params' => [
                'min_cluster_size'         => 5,
                'min_samples'              => 1,
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

        $this->activeRun = TopicModelRun::with('topics')
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
                'id'                 => $r->id,
                'year'               => $r->year,
                'title'              => $r->title,
                'raw'                => $raw,
                'cleaned'            => $service->cleanText($raw),
                'tokenized'          => $service->tokenize($service->cleanText($raw)),
                'filtered_tokens'    => $service->filterByLength($service->tokenize($service->cleanText($raw))),
                'stopwords_removed'  => $service->removeStopwordsFromTokens(
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
        $this->statusType    = 'info';
        $this->statusMessage = 'Menjalankan preprocessing data...';

        /** @var int|null $userId */
        $userId = Auth::id();

        if ($userId === null) {
            $this->statusType    = 'error';
            $this->statusMessage = 'Silakan login terlebih dahulu.';
            $this->isProcessing  = false;
            return;
        }

        // Buat run baru — tanpa preprocessing_preview (sudah dihapus dari DB)
        $run = TopicModelRun::create([
            'user_id'       => $userId,
            'model_type'    => 'bertopic',
            'status'        => 'preprocessing',
            'remove_stopwords' => $this->removeStopwords,
            'min_word_length'  => $this->minWordLength,
            'language'         => $this->language,
            'bertopic_params'  => $this->bertopicParams ?: null,
            'started_at'       => now(),
        ]);

        $this->activeRun  = $run;
        $this->activeRunId = $run->id;

        $fastApi = app(FastApiService::class);
        $resp    = $fastApi->startPreprocessing($run->id);

        if (!isset($resp['job_id'])) {
            $run->update([
                'status'        => 'failed',
                'error_message' => $resp['message'] ?? 'Preprocessing gagal',
                'completed_at'  => now(),
            ]);

            $this->statusType    = 'error';
            $this->statusMessage = $resp['message'] ?? 'Preprocessing gagal';
            $this->isProcessing  = false;
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
        $status  = $fastApi->getPreprocessingStatus($this->preprocessingJobId);

        if (($status['status'] ?? '') === 'unreachable') {
            $this->preprocessingMessage = 'FastAPI tidak dapat dihubungi, mencoba lagi...';
            return;
        }

        $this->preprocessingProgress = (int) round($status['progress'] ?? 0);
        $this->preprocessingMessage  = (string) ($status['message'] ?? '');

        $state = $status['status'] ?? null;

        if ($state === 'completed') {
            $this->activeRun->update([
                'status'          => 'pending',
                'total_documents' => (int) ($status['processed'] ?? 0),
            ]);
            $this->activeRun->refresh();
            $this->isProcessing = false;
            $this->statusType    = 'success';
            $this->statusMessage = 'Preprocessing selesai.';
            $this->dispatch('toast', type: 'success', message: 'Preprocessing selesai! Data siap untuk training.');
            $this->preprocessingJobId = '';
        } elseif ($state === 'failed') {
            $this->activeRun->update([
                'status'        => 'failed',
                'error_message' => (string) ($status['error'] ?? $this->preprocessingMessage ?? 'Preprocessing gagal'),
                'completed_at'  => now(),
            ]);
            $this->activeRun->refresh();

            $this->statusType    = 'error';
            $this->statusMessage = 'Preprocessing gagal: ' . ($status['error'] ?? $this->preprocessingMessage);
            $this->isProcessing  = false;
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
                    'status'        => 'failed',
                    'error_message' => 'Dibatalkan oleh user',
                    'completed_at'  => now(),
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
            $this->statusType    = 'error';
            $this->statusMessage = 'Jalankan preprocessing terlebih dahulu.';
            return;
        }

        if (!in_array($this->activeRun->status, ['pending', 'completed', 'failed'])) {
            $this->statusType    = 'warning';
            $this->statusMessage = 'Training sudah berjalan atau preprocessing belum selesai.';
            return;
        }

        $this->isProcessing  = true;
        $this->statusType    = 'info';
        $this->statusMessage = 'Memulai training BERTopic...';

        $fastApi = app(FastApiService::class);
        $resp    = $fastApi->startBerTopicTraining(
            bertopicParams: $this->bertopicParams,
            description: 'BERTopic run dari dashboard Jurusan',
        );

        if (!isset($resp['job_id'])) {
            $this->statusType    = 'error';
            $this->statusMessage = $resp['message'] ?? 'Gagal memulai training.';
            $this->isProcessing  = false;
            return;
        }

        $this->trainingJobId = (string) $resp['job_id'];

        $this->activeRun->update([
            'status'                   => 'training',
            'fastapi_training_job_id'  => $this->trainingJobId,
        ]);

        Log::info('Training job started', [
            'run_id' => $this->activeRun->id,
            'job_id' => $this->trainingJobId,
        ]);

        $this->dispatch('toast', type: 'info', message: 'Training BERTopic dimulai di background...');
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
        $status  = $fastApi->getTrainingStatus($this->trainingJobId);

        if (($status['status'] ?? '') === 'unreachable') {
            $this->trainingMessage = 'FastAPI tidak dapat dihubungi, mencoba lagi...';
            return;
        }

        $this->trainingProgress = (int) round($status['progress'] ?? 0);
        $this->trainingMessage  = (string) ($status['message'] ?? '');

        $state = $status['status'] ?? null;

        if ($state === 'completed') {
            $this->storeTrainingResults();
            $this->isProcessing = false;
        } elseif ($state === 'failed') {
            $this->activeRun->update([
                'status'        => 'failed',
                'error_message' => (string) ($status['error'] ?? $this->trainingMessage ?? 'Training gagal'),
                'completed_at'  => now(),
            ]);
            $this->activeRun->refresh();

            $this->statusType    = 'error';
            $this->statusMessage = 'Training gagal: ' . ($status['error'] ?? $this->trainingMessage);
            $this->isProcessing  = false;
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
            $this->statusType    = 'warning';
            $this->statusMessage = 'Training selesai, tapi hasil belum siap. Coba refresh beberapa saat.';
            return;
        }

        $metrics = $results['metrics'] ?? [];

        $this->activeRun->update([
            'status'                    => 'completed',
            'num_topics'                => (int) ($results['num_topics'] ?? 0),
            'num_outliers'              => (int) ($results['num_outliers'] ?? 0),
            'coherence_cv'              => isset($metrics['coherence_cv']) ? (float) $metrics['coherence_cv'] : null,
            'topic_diversity'           => isset($metrics['topic_diversity']) ? (float) $metrics['topic_diversity'] : null,
            'training_duration_seconds' => isset($results['training_duration_seconds']) ? (float) $results['training_duration_seconds'] : null,
            'model_path'                => (string) ($results['model_path'] ?? ''),
            'completed_at'              => now(),
        ]);

        // Simpan topik ke tabel topic_model_topics
        $topics = $results['topic_info'] ?? [];
        foreach ($topics as $t) {
            TopicModelTopic::updateOrCreate(
                [
                    'topic_model_run_id' => $this->activeRun->id,
                    'topic_id'           => (int) ($t['topic_id'] ?? 0),
                ],
                [
                    'count'       => (int) ($t['count'] ?? 0),
                    'top_words'   => $t['top_words'] ?? [],
                    'word_scores' => $t['word_scores'] ?? [],
                ]
            );
        }

        $this->activeRun->refresh();
        $this->activeRun->load('topics');

        $this->statusType    = 'success';
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
