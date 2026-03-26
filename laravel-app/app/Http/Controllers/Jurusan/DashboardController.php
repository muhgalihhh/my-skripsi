<?php

namespace App\Http\Controllers\Jurusan;

use App\Http\Controllers\Controller;
use App\Models\Skripsi;
use App\Models\ScrapingLog;
use App\Services\FastApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
  /**
   * Show jurusan dashboard.
   */
  public function index()
  {
    $totalSkripsi = Skripsi::count();
    $skripsiPerYear = Skripsi::selectRaw('year, COUNT(*) as total')
      ->whereNotNull('year')
      ->groupBy('year')
      ->orderBy('year', 'desc')
      ->get();

    $recentScrapingLogs = ScrapingLog::with('user')
      ->latestFirst()
      ->take(10)
      ->get();

    $latestScraping = ScrapingLog::latestFirst()->first();

    return view('jurusan.dashboard', compact(
      'totalSkripsi',
      'skripsiPerYear',
      'recentScrapingLogs',
      'latestScraping'
    ));
  }
}
