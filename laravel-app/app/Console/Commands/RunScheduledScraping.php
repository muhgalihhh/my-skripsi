<?php

namespace App\Console\Commands;

use App\Models\ScrapingLog;
use App\Models\Skripsi;
use App\Models\User;
use App\Services\FastApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunScheduledScraping extends Command
{
  /**
   * The name and signature of the console command.
   */
  protected $signature = 'scraping:run
                            {--start-year=2019 : Start year for scraping}
                            {--end-year=2026 : End year for scraping}';

  /**
   * The console command description.
   */
  protected $description = 'Run scheduled scraping from UNSOED repository via FastAPI';

  /**
   * Execute the console command.
   */
  public function handle(FastApiService $fastApi): int
  {
    $startYear = (int) $this->option('start-year');
    $endYear = (int) $this->option('end-year');

    $this->info("🚀 Starting scheduled scraping ({$startYear}-{$endYear})...");

    // Use the first jurusan user as the trigger user
    $adminUser = User::where('role', 'jurusan')->first();

    if (!$adminUser) {
      $this->error('No jurusan user found. Run: php artisan db:seed --class=JurusanSeeder');
      return Command::FAILURE;
    }

    // Create scraping log
    $scrapingLog = ScrapingLog::create([
      'user_id' => $adminUser->id,
      'trigger_type' => 'scheduled',
      'status' => 'running',
      'started_at' => now(),
    ]);

    try {
      // Check FastAPI health
      $health = $fastApi->healthCheck();
      if (($health['status'] ?? '') !== 'ok') {
        throw new \Exception('FastAPI service is not available');
      }

      // Trigger scraping
      $result = $fastApi->triggerScraping($startYear, $endYear);

      if (isset($result['status']) && $result['status'] === 'success') {
        $storeResult = $this->storeScrapedData($result['data'] ?? []);

        $scrapingLog->update([
          'status' => 'completed',
          'total_scraped' => $storeResult['total'],
          'new_added' => $storeResult['new'],
          'duplicates_skipped' => $storeResult['duplicates'],
          'completed_at' => now(),
        ]);

        $this->info("✅ Scraping completed! New: {$storeResult['new']}, Duplicates: {$storeResult['duplicates']}");
        return Command::SUCCESS;
      }

      throw new \Exception($result['message'] ?? 'Unknown scraping error');

    } catch (\Exception $e) {
      $scrapingLog->update([
        'status' => 'failed',
        'error_message' => $e->getMessage(),
        'completed_at' => now(),
      ]);

      $this->error("❌ Scraping failed: {$e->getMessage()}");
      Log::error('Scheduled scraping failed', ['error' => $e->getMessage()]);

      return Command::FAILURE;
    }
  }

  /**
   * Store scraped data into database.
   */
  protected function storeScrapedData(array $documents): array
  {
    $new = 0;
    $duplicates = 0;

    foreach ($documents as $doc) {
      $url = $doc['url'] ?? $doc['URL'] ?? null;
      if (!$url)
        continue;

      if (Skripsi::where('url', $url)->exists()) {
        $duplicates++;
        continue;
      }

      Skripsi::create([
        'title' => $doc['title'] ?? $doc['Judul'] ?? '',
        'abstract' => $doc['abstract'] ?? $doc['Abstrak'] ?? null,
        'type' => $doc['type'] ?? $doc['Tipe'] ?? null,
        'id_code' => $doc['id_code'] ?? $doc['ID Code'] ?? null,
        'keywords' => $doc['keywords'] ?? $doc['Kata Kunci'] ?? null,
        'subjects' => $doc['subjects'] ?? $doc['Subjects'] ?? null,
        'divisions' => $doc['divisions'] ?? $doc['Divisions'] ?? null,
        'author' => $doc['author'] ?? $doc['Penulis'] ?? null,
        'deposit_date' => $doc['deposit_date'] ?? $doc['Tanggal Deposit'] ?? null,
        'modified_date' => $doc['modified_date'] ?? $doc['Tanggal Modifikasi'] ?? null,
        'uri' => $doc['uri'] ?? $doc['URI'] ?? null,
        'year' => $doc['year'] ?? $doc['Tahun'] ?? null,
        'pdf_documents' => $doc['pdf_documents'] ?? $doc['Dokumen_PDF'] ?? null,
        'url' => $url,
        'conclusion' => $doc['conclusion'] ?? $doc['Kesimpulan'] ?? null,
        'conclusion_source' => $doc['conclusion_source'] ?? $doc['Sumber Kesimpulan'] ?? null,
      ]);

      $new++;
    }

    return [
      'total' => count($documents),
      'new' => $new,
      'duplicates' => $duplicates,
    ];
  }
}
