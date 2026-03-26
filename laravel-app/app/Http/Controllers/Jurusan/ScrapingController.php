<?php

namespace App\Http\Controllers\Jurusan;

use App\Http\Controllers\Controller;
use App\Models\Skripsi;
use App\Models\ScrapingLog;
use App\Services\FastApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ScrapingController extends Controller
{
  protected FastApiService $fastApi;

  public function __construct(FastApiService $fastApi)
  {
    $this->fastApi = $fastApi;
  }

  /**
   * Show scraping management page.
   */
  public function index()
  {
    $logs = ScrapingLog::with('user')
      ->latestFirst()
      ->paginate(15);

    $totalSkripsi = Skripsi::count();
    $apiStatus = $this->fastApi->healthCheck();

    return view('jurusan.scraping.index', compact('logs', 'totalSkripsi', 'apiStatus'));
  }

  /**
   * Start manual scraping.
   */
  public function start(Request $request)
  {
    $request->validate([
      'start_year' => 'nullable|integer|min:2000|max:2030',
      'end_year' => 'nullable|integer|min:2000|max:2030',
    ]);

    $startYear = $request->input('start_year', 2019);
    $endYear = $request->input('end_year', 2026);

    // Create scraping log
    $scrapingLog = ScrapingLog::create([
      'user_id' => Auth::id(),
      'trigger_type' => 'manual',
      'status' => 'running',
      'started_at' => now(),
    ]);

    try {
      // Call FastAPI scraping endpoint
      $result = $this->fastApi->triggerScraping($startYear, $endYear);

      if (isset($result['status']) && $result['status'] === 'success') {
        // Store scraped data into database
        $storeResult = $this->storeScrapedData($result['data'] ?? []);

        $scrapingLog->update([
          'status' => 'completed',
          'total_scraped' => $storeResult['total'],
          'new_added' => $storeResult['new'],
          'duplicates_skipped' => $storeResult['duplicates'],
          'completed_at' => now(),
        ]);

        return redirect()->route('jurusan.scraping.index')
          ->with('success', "Scraping selesai! {$storeResult['new']} data baru ditambahkan, {$storeResult['duplicates']} duplikat dilewati.");
      }

      // Handle failure
      $errorMsg = $result['message'] ?? 'Terjadi kesalahan yang tidak diketahui.';
      $scrapingLog->update([
        'status' => 'failed',
        'error_message' => $errorMsg,
        'completed_at' => now(),
      ]);

      return redirect()->route('jurusan.scraping.index')
        ->with('error', "Scraping gagal: {$errorMsg}");

    } catch (\Exception $e) {
      Log::error('Scraping failed', ['error' => $e->getMessage()]);

      $scrapingLog->update([
        'status' => 'failed',
        'error_message' => $e->getMessage(),
        'completed_at' => now(),
      ]);

      return redirect()->route('jurusan.scraping.index')
        ->with('error', 'Scraping gagal: ' . $e->getMessage());
    }
  }

  /**
   * Store scraped documents into database.
   */
  protected function storeScrapedData(array $documents): array
  {
    $new = 0;
    $duplicates = 0;

    foreach ($documents as $doc) {
      $url = $doc['url'] ?? $doc['URL'] ?? null;

      if (!$url) {
        continue;
      }

      // Check if already exists by URL
      $exists = Skripsi::where('url', $url)->exists();

      if ($exists) {
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
