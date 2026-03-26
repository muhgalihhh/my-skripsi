<?php

namespace App\Livewire\Jurusan;

use App\Models\ScrapingLog;
use App\Models\Skripsi;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.jurusan')]
#[Title('Dashboard')]
class Dashboard extends Component
{
  public function render()
  {
    $totalSkripsi = Skripsi::count();

    $skripsiPerYear = Skripsi::selectRaw('year, COUNT(*) as total')
      ->whereNotNull('year')
      ->groupBy('year')
      ->orderBy('year')
      ->get();

    $recentScrapingLogs = ScrapingLog::with('user')
      ->latestFirst()
      ->limit(5)
      ->get();

    $latestScraping = ScrapingLog::latestFirst()->first();

    return view('livewire.jurusan.dashboard', compact(
      'totalSkripsi',
      'skripsiPerYear',
      'recentScrapingLogs',
      'latestScraping'
    ));
  }
}
