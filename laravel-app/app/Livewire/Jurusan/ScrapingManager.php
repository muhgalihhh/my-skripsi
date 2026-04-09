<?php

namespace App\Livewire\Jurusan;

use App\Models\ScrapingLog;
use App\Models\Skripsi;
use App\Models\TopicModelRun;
use App\Services\FastApiService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.jurusan')]
#[Title('Manajemen Scraping')]
class ScrapingManager extends Component
{
    use WithPagination;

    public int $startYear = 2018;
    public int $endYear = 2026;
    public bool $isProcessing = false;
    public string $statusMessage = '';
    public string $statusType = 'info'; // info, success, error
    public array $apiStatus = [];

    // Async job tracking
    public string $activeJobId = '';
    public int $jobProgress = 0;
    public string $jobStep = '';
    public int $jobScrapedCount = 0;
    public int $jobTotalUrls = 0;
    public int $jobSkippedCount = 0;
    public int $jobFilteredCount = 0;

    // Monitoring modal
    public bool $showMonitoringModal = false;
    public array $monitoringData = [];
    public array $monitoringScrapedItems = [];
    public int $monitoringFoundTotal = 0;
    public int $monitoringScrapedTotal = 0;

    // Cancel confirm modal
    public bool $showCancelConfirm = false;

    // Start scraping confirm modal
    public bool $showStartScrapingConfirm = false;

    // Reset skripsi confirm modal
    public bool $showResetSkripsiConfirm = false;

    // Bulk delete history
    public array $selectedLogIds = [];
    public bool $selectAllLogs = false;
    public bool $showBulkDeleteLogsConfirm = false;

    public function mount(): void
    {
        $this->checkApiStatus();
        $this->resumeActiveJob();
    }

    /**
     * Check if there's an active scraping job from a previous session.
     * This makes scraping survive page refreshes.
     */
    public function resumeActiveJob(): void
    {
        // Check if there's a running log in database
        $runningLog = ScrapingLog::where('status', 'running')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($runningLog && $runningLog->fastapi_job_id) {
            $fastApiService = app(FastApiService::class);
            $jobStatus = $fastApiService->getJobStatus($runningLog->fastapi_job_id);

            if (in_array($jobStatus['status'] ?? '', ['pending', 'running'])) {
                // Job is still running in FastAPI — resume tracking
                $this->activeJobId = $runningLog->fastapi_job_id;
                $this->isProcessing = true;
                $this->jobProgress = $jobStatus['progress'] ?? 0;
                $this->jobStep = $jobStatus['current_step'] ?? 'Sedang berjalan...';
                $this->jobScrapedCount = $jobStatus['scraped_count'] ?? 0;
                $this->jobTotalUrls = $jobStatus['total_detail_urls'] ?? 0;
                $this->jobSkippedCount = $jobStatus['skipped_count'] ?? 0;
                $this->jobFilteredCount = $jobStatus['filtered_count'] ?? 0;
                $this->statusMessage = "Scraping sedang berjalan ({$this->jobProgress}%)...";
                $this->statusType = 'info';
            } elseif (($jobStatus['status'] ?? '') === 'completed') {
                // Job completed while page was away — process results
                $this->processCompletedJob($runningLog, $jobStatus);
            } elseif (($jobStatus['status'] ?? '') === 'failed') {
                $runningLog->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => $jobStatus['error_message'] ?? 'Unknown error',
                ]);
            } elseif (($jobStatus['status'] ?? '') === 'unreachable') {
                // FastAPI is down, mark as unknown — user can refresh later
                $this->statusMessage = 'FastAPI tidak dapat dihubungi. Status scraping sebelumnya tidak diketahui.';
                $this->statusType = 'error';
            } else {
                // Job not found in FastAPI (maybe restarted) — mark log as failed
                $runningLog->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => 'Job tidak ditemukan di FastAPI (mungkin service restart)',
                ]);
            }
        }
    }

    public function checkApiStatus(): void
    {
        $fastApiService = app(FastApiService::class);
        $this->apiStatus = $fastApiService->healthCheck();
    }

    public function startScraping(): void
    {
        $this->showStartScrapingConfirm = false;

        $this->validate([
            'startYear' => 'required|integer|min:2000|max:2030',
            'endYear' => 'required|integer|min:2000|max:2030|gte:startYear',
        ]);

        $this->isProcessing = true;
        $this->statusMessage = 'Memulai proses scraping...';
        $this->statusType = 'info';
        $this->jobProgress = 0;
        $this->jobStep = '';

        try {
            $fastApiService = app(FastApiService::class);

            // Start async job in FastAPI
            $result = $fastApiService->startScrapingJob($this->startYear, $this->endYear);

            if (($result['status'] ?? '') === 'accepted') {
                $jobId = $result['job_id'];
                $this->activeJobId = $jobId;

                // Create scraping log with job reference
                ScrapingLog::create([
                    'user_id' => auth()->id(),
                    'trigger_type' => 'manual',
                    'status' => 'running',
                    'started_at' => now(),
                    'fastapi_job_id' => $jobId,
                ]);

                $this->statusMessage = 'Scraping dimulai! Progress akan diupdate secara otomatis...';
                $this->statusType = 'info';
                $this->dispatch('toast', type: 'success', message: 'Scraping berhasil dimulai! Tahun ' . $this->startYear . '-' . $this->endYear);
            } elseif (($result['status'] ?? '') === 'conflict') {
                $this->statusMessage = $result['message'] ?? 'Sudah ada scraping yang berjalan.';
                $this->statusType = 'error';
                $this->dispatch('toast', type: 'warning', message: $result['message'] ?? 'Sudah ada scraping yang sedang berjalan.');

                // Try to resume tracking the active job
                $activeJob = $result['active_job'] ?? null;
                if ($activeJob) {
                    $this->activeJobId = $activeJob['job_id'];
                    $this->jobProgress = $activeJob['progress'] ?? 0;
                }
            } else {
                $this->statusMessage = 'Gagal memulai scraping: ' . ($result['message'] ?? 'Unknown error');
                $this->statusType = 'error';
                $this->isProcessing = false;
                $this->dispatch('toast', type: 'error', message: 'Gagal memulai scraping: ' . ($result['message'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            $this->statusMessage = 'Error: ' . $e->getMessage();
            $this->statusType = 'error';
            $this->isProcessing = false;
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Poll the scraping job progress from FastAPI.
     * Called automatically by Livewire wire:poll.
     */
    public function pollJobProgress(): void
    {
        if (!$this->activeJobId || !$this->isProcessing) {
            return;
        }

        try {
            $fastApiService = app(FastApiService::class);
            $jobStatus = $fastApiService->getJobStatus($this->activeJobId);

            $status = $jobStatus['status'] ?? 'unknown';

            if ($status === 'running' || $status === 'pending') {
                $this->jobProgress = $jobStatus['progress'] ?? 0;
                $this->jobStep = $jobStatus['current_step'] ?? '';
                $this->jobScrapedCount = $jobStatus['scraped_count'] ?? 0;
                $this->jobTotalUrls = $jobStatus['total_detail_urls'] ?? 0;
                $this->jobSkippedCount = $jobStatus['skipped_count'] ?? 0;
                $this->jobFilteredCount = $jobStatus['filtered_count'] ?? 0;
                $this->statusMessage = "Scraping berjalan... ({$this->jobProgress}%)";
                $this->statusType = 'info';

                // Refresh monitoring data if modal is open
                if ($this->showMonitoringModal) {
                    $this->refreshMonitoringData();
                }
            } elseif ($status === 'completed') {
                // Fetch full results with data
                $fullResult = $fastApiService->getJobStatus($this->activeJobId, includeData: true);
                $scrapingLog = ScrapingLog::where('fastapi_job_id', $this->activeJobId)->first();

                if ($scrapingLog) {
                    $this->processCompletedJob($scrapingLog, $fullResult);
                }

                $this->isProcessing = false;
                $this->activeJobId = '';
                $this->showMonitoringModal = false;
            } elseif ($status === 'failed') {
                $errorMsg = $jobStatus['error_message'] ?? 'Unknown error';

                $scrapingLog = ScrapingLog::where('fastapi_job_id', $this->activeJobId)->first();
                if ($scrapingLog) {
                    $scrapingLog->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                        'error_message' => $errorMsg,
                    ]);
                }

                $this->statusMessage = 'Scraping gagal: ' . $errorMsg;
                $this->statusType = 'error';
                $this->isProcessing = false;
                $this->activeJobId = '';
                $this->dispatch('toast', type: 'error', message: 'Scraping gagal: ' . $errorMsg);
            } elseif ($status === 'cancelled') {
                $scrapingLog = ScrapingLog::where('fastapi_job_id', $this->activeJobId)->first();
                if ($scrapingLog) {
                    $scrapingLog->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                        'error_message' => 'Dibatalkan oleh user',
                    ]);
                }

                $this->statusMessage = 'Scraping dibatalkan.';
                $this->statusType = 'error';
                $this->isProcessing = false;
                $this->activeJobId = '';
                $this->dispatch('toast', type: 'warning', message: 'Scraping telah dibatalkan.');
            }
        } catch (\Exception $e) {
            // Don't stop polling on transient errors
            $this->statusMessage = "Memantau progress... (koneksi sementara terganggu)";
        }
    }

    /**
     * Process a completed job: store documents into database.
     */
    protected function processCompletedJob(ScrapingLog $scrapingLog, array $jobStatus): void
    {
        $documents = $jobStatus['result']['data'] ?? [];

        // Debug: log what we received
        \Illuminate\Support\Facades\Log::info('Processing completed scraping job', [
            'job_id' => $scrapingLog->fastapi_job_id,
            'total_documents_received' => count($documents),
            'has_result_key' => isset($jobStatus['result']),
            'has_data_key' => isset($jobStatus['result']['data']),
            'sample_doc_keys' => count($documents) > 0 ? array_keys($documents[0]) : [],
        ]);

        $newAdded = 0;
        $dataUpdated = 0;

        foreach ($documents as $index => $doc) {
            $url = $doc['URL'] ?? $doc['url'] ?? null;
            if (!$url)
                continue;

            $data = [
                'title' => $doc['Judul'] ?? $doc['judul'] ?? $doc['title'] ?? 'Untitled',
                'abstract' => $doc['Abstrak'] ?? $doc['abstrak'] ?? $doc['abstract'] ?? null,
                'conclusion' => $doc['Kesimpulan'] ?? $doc['kesimpulan'] ?? $doc['conclusion'] ?? null,
                'conclusion_source' => $doc['Sumber Kesimpulan'] ?? $doc['sumber_kesimpulan'] ?? $doc['conclusion_source'] ?? null,
                'author' => $doc['Penulis'] ?? $doc['penulis'] ?? $doc['author'] ?? null,
                'year' => $doc['Tahun'] ?? $doc['tahun'] ?? $doc['year'] ?? null,
                'type' => $doc['Tipe'] ?? $doc['tipe'] ?? $doc['type'] ?? null,
                'id_code' => $doc['ID Code'] ?? $doc['id_code'] ?? null,
                'keywords' => $doc['Kata Kunci'] ?? $doc['kata_kunci'] ?? $doc['keywords'] ?? null,
                'subjects' => $doc['Subjects'] ?? $doc['subjects'] ?? null,
                'divisions' => $doc['Divisions'] ?? $doc['divisions'] ?? null,
                'deposit_date' => $doc['Tanggal Deposit'] ?? $doc['deposit_date'] ?? null,
                'modified_date' => $doc['Tanggal Modifikasi'] ?? $doc['modified_date'] ?? null,
                'uri' => $doc['URI'] ?? $doc['uri'] ?? null,
                'pdf_documents' => $doc['Dokumen_PDF'] ?? $doc['pdf_documents'] ?? [],
                'repository_order' => $index + 1,
            ];

            try {
                // Use updateOrCreate: if URL exists, update with fresh data; otherwise create new
                $existing = Skripsi::where('url', $url)->first();
                if ($existing) {
                    $existing->update($data);
                    $dataUpdated++;
                } else {
                    Skripsi::create(array_merge($data, ['url' => $url]));
                    $newAdded++;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to save skripsi document', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $scrapingLog->update([
            'status' => 'completed',
            'completed_at' => now(),
            'total_scraped' => count($documents),
            'new_added' => $newAdded,
            'duplicates_skipped' => 0,
            'data_updated' => $dataUpdated,
        ]);

        $message = "Scraping selesai! " . count($documents) . " dokumen, {$newAdded} baru, {$dataUpdated} diperbarui.";
        $this->statusMessage = "Scraping selesai! Total: " . count($documents) . " dokumen ditemukan, {$newAdded} baru ditambahkan, {$dataUpdated} data diperbarui.";
        $this->statusType = 'success';
        $this->jobProgress = 100;
        $this->jobStep = 'Selesai';
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /**
     * Cancel the active scraping job.
     */
    public function cancelScraping(): void
    {
        if (!$this->activeJobId) {
            return;
        }

        try {
            $fastApiService = app(FastApiService::class);
            $result = $fastApiService->cancelJob($this->activeJobId);

            if (($result['status'] ?? '') === 'cancelled') {
                $scrapingLog = ScrapingLog::where('fastapi_job_id', $this->activeJobId)->first();
                if ($scrapingLog) {
                    $scrapingLog->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                        'error_message' => 'Dibatalkan oleh user',
                    ]);
                }

                $this->statusMessage = 'Scraping berhasil dibatalkan.';
                $this->statusType = 'error';
                $this->dispatch('toast', type: 'warning', message: 'Scraping berhasil dibatalkan.');
            } else {
                $this->statusMessage = 'Gagal membatalkan scraping.';
                $this->statusType = 'error';
                $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan scraping.');
            }
        } catch (\Exception $e) {
            $this->statusMessage = 'Error: ' . $e->getMessage();
            $this->statusType = 'error';
        } finally {
            $this->isProcessing = false;
            $this->activeJobId = '';
            $this->showCancelConfirm = false;
        }
    }

    /**
     * Open cancel confirm modal.
     */
    public function openCancelConfirm(): void
    {
        $this->showCancelConfirm = true;
    }

    /**
     * Open start scraping confirm modal.
     */
    public function openStartScrapingConfirm(): void
    {
        $this->validate([
            'startYear' => 'required|integer|min:2000|max:2030',
            'endYear' => 'required|integer|min:2000|max:2030|gte:startYear',
        ]);

        if (($this->apiStatus['status'] ?? '') !== 'ok') {
            $this->dispatch('toast', type: 'warning', message: 'FastAPI belum aktif. Tidak dapat memulai scraping.');
            return;
        }

        $this->showStartScrapingConfirm = true;
    }

    /**
     * Close start scraping confirm modal.
     */
    public function closeStartScrapingConfirm(): void
    {
        $this->showStartScrapingConfirm = false;
    }

    /**
     * Close cancel confirm modal.
     */
    public function closeCancelConfirm(): void
    {
        $this->showCancelConfirm = false;
    }

    /**
     * Determine if background jobs are still running and block destructive actions.
     */
    protected function hasActiveBackgroundJobs(): bool
    {
        if ($this->isProcessing || $this->activeJobId !== '') {
            return true;
        }

        $fastApi = app(FastApiService::class);

        $runningScrapingLogs = ScrapingLog::query()
            ->where('status', 'running')
            ->get(['id', 'fastapi_job_id']);

        foreach ($runningScrapingLogs as $log) {
            $jobId = trim((string) ($log->fastapi_job_id ?? ''));

            // Stale running log without job id should not block destructive action.
            if ($jobId === '') {
                continue;
            }

            $status = $fastApi->getJobStatus($jobId);
            $state = (string) ($status['status'] ?? 'unknown');

            if (in_array($state, ['pending', 'running'], true)) {
                return true;
            }

            // If FastAPI is unreachable, keep a safe default and block reset.
            if ($state === 'unreachable') {
                return true;
            }
        }

        $candidateRuns = TopicModelRun::query()
            ->whereIn('status', ['preprocessing', 'training'])
            ->get(['id', 'status', 'fastapi_preprocessing_job_id', 'fastapi_training_job_id']);

        foreach ($candidateRuns as $run) {
            if ($run->status === 'preprocessing') {
                $jobId = trim((string) ($run->fastapi_preprocessing_job_id ?? ''));

                // Stale preprocessing row without FastAPI job id should not block reset.
                if ($jobId === '') {
                    continue;
                }

                $status = $fastApi->getPreprocessingStatus($jobId);
                $state = (string) ($status['status'] ?? 'unknown');

                if (in_array($state, ['pending', 'running'], true)) {
                    return true;
                }

                if ($state === 'unreachable') {
                    return true;
                }

                continue;
            }

            if ($run->status === 'training') {
                $jobId = trim((string) ($run->fastapi_training_job_id ?? ''));

                // Stale training row without FastAPI job id should not block reset.
                if ($jobId === '') {
                    continue;
                }

                $status = $fastApi->getTrainingStatus($jobId);
                $state = (string) ($status['status'] ?? 'unknown');

                if (in_array($state, ['pending', 'running'], true)) {
                    return true;
                }

                if ($state === 'unreachable') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Open reset confirm modal.
     */
    public function openResetSkripsiConfirm(): void
    {
        if ($this->hasActiveBackgroundJobs()) {
            $this->statusType = 'error';
            $this->statusMessage = 'Tidak bisa reset database saat scraping/preprocessing/training masih berjalan.';
            $this->dispatch('toast', type: 'warning', message: 'Tunggu semua job selesai sebelum reset database.');
            return;
        }

        $this->showResetSkripsiConfirm = true;
    }

    /**
     * Close reset confirm modal.
     */
    public function closeResetSkripsiConfirm(): void
    {
        $this->showResetSkripsiConfirm = false;
    }

    /**
     * Delete all skripsi data so scraping can be rerun from a clean state.
     */
    public function resetSkripsiData(): void
    {
        if ($this->hasActiveBackgroundJobs()) {
            $this->showResetSkripsiConfirm = false;
            $this->statusType = 'error';
            $this->statusMessage = 'Reset dibatalkan karena masih ada job aktif.';
            $this->dispatch('toast', type: 'warning', message: 'Masih ada job aktif. Reset database dibatalkan.');
            return;
        }

        try {
            $totalBefore = Skripsi::count();

            if ($totalBefore > 0) {
                Skripsi::query()->delete();
            }

            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE skripsi AUTO_INCREMENT = 1');
            }

            $this->showResetSkripsiConfirm = false;
            $this->jobProgress = 0;
            $this->jobStep = '';
            $this->jobScrapedCount = 0;
            $this->jobTotalUrls = 0;
            $this->jobSkippedCount = 0;
            $this->jobFilteredCount = 0;

            if ($totalBefore === 0) {
                $message = 'Database skripsi sudah kosong. Auto increment tetap direset ke 1.';
            } else {
                $message = "Database skripsi berhasil direset. {$totalBefore} data dihapus dan auto increment direset ke 1.";
            }

            $this->statusType = 'success';
            $this->statusMessage = $message;
            $this->resetPage();
            $this->dispatch('toast', type: 'success', message: $message);
        } catch (\Throwable $e) {
            $this->statusType = 'error';
            $this->statusMessage = 'Gagal reset database skripsi: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: 'Gagal reset database skripsi.');
        }
    }

    /**
     * Open monitoring modal and fetch detailed progress data.
     */
    public function openMonitoring(): void
    {
        $this->showMonitoringModal = true;
        $this->refreshMonitoringData();
    }

    /**
     * Close monitoring modal.
     */
    public function closeMonitoring(): void
    {
        $this->showMonitoringModal = false;
        $this->monitoringData = [];
        $this->monitoringScrapedItems = [];
    }

    /**
     * Refresh monitoring data from FastAPI.
     */
    public function refreshMonitoringData(): void
    {
        if (!$this->activeJobId) {
            return;
        }

        try {
            $fastApiService = app(FastApiService::class);
            $jobStatus = $fastApiService->getJobStatus($this->activeJobId, includeMonitoring: true);

            $this->monitoringData = $jobStatus['found_urls'] ?? [];
            $this->monitoringScrapedItems = $jobStatus['scraped_items'] ?? [];
            $this->monitoringFoundTotal = $jobStatus['found_urls_total'] ?? 0;
            $this->monitoringScrapedTotal = $jobStatus['scraped_items_total'] ?? 0;
        } catch (\Exception $e) {
            // Silently fail for monitoring
        }
    }

    public function toggleSelectAllLogs(): void
    {
        if ($this->selectAllLogs) {
            // Collect all log IDs from current page
            $logs = ScrapingLog::latestFirst()->paginate(10);
            $this->selectedLogIds = $logs->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedLogIds = [];
        }
    }

    public function openBulkDeleteLogsConfirm(): void
    {
        if (count($this->selectedLogIds) > 0) {
            $this->showBulkDeleteLogsConfirm = true;
        }
    }

    public function closeBulkDeleteLogsConfirm(): void
    {
        $this->showBulkDeleteLogsConfirm = false;
    }

    public function bulkDeleteLogs(): void
    {
        $count = count($this->selectedLogIds);
        if ($count === 0)
            return;

        ScrapingLog::whereIn('id', $this->selectedLogIds)->delete();

        $this->selectedLogIds = [];
        $this->selectAllLogs = false;
        $this->showBulkDeleteLogsConfirm = false;
        $this->resetPage();
        $this->dispatch('toast', type: 'success', message: "{$count} riwayat scraping berhasil dihapus.");
    }

    public function render()
    {
        $logs = ScrapingLog::with('user')
            ->latestFirst()
            ->paginate(10);

        $totalSkripsi = Skripsi::count();

        return view('livewire.jurusan.scraping-manager', compact('logs', 'totalSkripsi'));
    }
}
