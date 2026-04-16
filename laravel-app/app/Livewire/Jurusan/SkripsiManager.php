<?php

namespace App\Livewire\Jurusan;

use App\Models\Skripsi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.jurusan')]
#[Title('Manajemen Skripsi')]
class SkripsiManager extends Component
{
  use WithPagination;
  use WithFileUploads;

  #[Url]
  public string $search = '';

  #[Url]
  public string $yearFilter = '';

  #[Url]
  public string $sortField = 'year';

  #[Url]
  public string $sortDirection = 'desc';

  public int $perPage = 15;

  public $skripsiCsvFile;

  // Detail modal
  public bool $showDetailModal = false;
  public ?int $selectedSkripsiId = null;
  public array $selectedSkripsi = [];

  // Edit modal
  public bool $showEditModal = false;
  public ?int $editSkripsiId = null;
  public string $editTitle = '';
  public string $editAuthor = '';
  public string $editYear = '';
  public string $editAbstract = '';
  public string $editConclusion = '';
  public string $editKeywords = '';
  public string $editSubjects = '';
  public string $editDivisions = '';
  public string $editType = '';
  public string $editIdCode = '';

  // Bulk action
  public array $selectedIds = [];
  public bool $selectAll = false;
  public bool $showBulkDeleteConfirm = false;

  // Delete confirm modal
  public bool $showDeleteConfirm = false;
  public ?int $deleteTargetId = null;

  public function updatingSearch(): void
  {
    $this->resetPage();
    $this->selectedIds = [];
    $this->selectAll = false;
  }

  public function updatingYearFilter(): void
  {
    $this->resetPage();
    $this->selectedIds = [];
    $this->selectAll = false;
  }

  public function toggleSelectAll(): void
  {
    if ($this->selectAll) {
      // Get all IDs on current page
      $this->selectedIds = $this->getFilteredQuery()
        ->paginate($this->perPage)
        ->pluck('id')
        ->map(fn($id) => (string) $id)
        ->toArray();
    } else {
      $this->selectedIds = [];
    }
  }

  public function openBulkDeleteConfirm(): void
  {
    if (count($this->selectedIds) > 0) {
      $this->showBulkDeleteConfirm = true;
    }
  }

  public function closeBulkDeleteConfirm(): void
  {
    $this->showBulkDeleteConfirm = false;
  }

  public function bulkDelete(): void
  {
    $count = count($this->selectedIds);
    if ($count > 0) {
      Skripsi::whereIn('id', $this->selectedIds)->delete();
      $this->selectedIds = [];
      $this->selectAll = false;
      $this->showBulkDeleteConfirm = false;
      $this->dispatch('toast', type: 'success', message: "{$count} data skripsi berhasil dihapus.");
    }
  }

  public function confirmDelete(int $id): void
  {
    $this->deleteTargetId = $id;
    $this->showDeleteConfirm = true;
  }

  public function closeDeleteConfirm(): void
  {
    $this->showDeleteConfirm = false;
    $this->deleteTargetId = null;
  }

  public function executeDelete(): void
  {
    if ($this->deleteTargetId) {
      $this->deleteSkripsi($this->deleteTargetId);
    }
  }

  public function sortBy(string $field): void
  {
    if ($this->sortField === $field) {
      $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      $this->sortField = $field;
      $this->sortDirection = 'asc';
    }
  }

  public function showDetail(int $id): void
  {
    $skripsi = Skripsi::find($id);
    if ($skripsi) {
      $this->selectedSkripsiId = $id;
      $this->selectedSkripsi = $skripsi->toArray();
      $this->showDetailModal = true;
    }
  }

  public function closeDetail(): void
  {
    $this->showDetailModal = false;
    $this->selectedSkripsiId = null;
    $this->selectedSkripsi = [];
  }

  public function openEdit(int $id): void
  {
    $skripsi = Skripsi::find($id);
    if ($skripsi) {
      $this->editSkripsiId = $id;
      $this->editTitle = $skripsi->title ?? '';
      $this->editAuthor = $skripsi->author ?? '';
      $this->editYear = (string) ($skripsi->year ?? '');
      $this->editAbstract = $skripsi->abstract ?? '';
      $this->editConclusion = $skripsi->conclusion ?? '';
      $this->editKeywords = $skripsi->keywords ?? '';
      $this->editSubjects = $skripsi->subjects ?? '';
      $this->editDivisions = $skripsi->divisions ?? '';
      $this->editType = $skripsi->type ?? '';
      $this->editIdCode = $skripsi->id_code ?? '';
      $this->showEditModal = true;
      $this->showDetailModal = false;
    }
  }

  public function closeEdit(): void
  {
    $this->showEditModal = false;
    $this->editSkripsiId = null;
    $this->reset(['editTitle', 'editAuthor', 'editYear', 'editAbstract', 'editConclusion', 'editKeywords', 'editSubjects', 'editDivisions', 'editType', 'editIdCode']);
  }

  public function saveEdit(): void
  {
    $this->validate([
      'editTitle' => 'required|min:3',
      'editAuthor' => 'nullable|string',
      'editYear' => 'nullable|integer|min:2000|max:2030',
      'editAbstract' => 'nullable|string',
      'editConclusion' => 'nullable|string',
      'editKeywords' => 'nullable|string',
      'editSubjects' => 'nullable|string',
      'editDivisions' => 'nullable|string',
      'editType' => 'nullable|string',
      'editIdCode' => 'nullable|string',
    ]);

    $skripsi = Skripsi::find($this->editSkripsiId);
    if ($skripsi) {
      $skripsi->update([
        'title' => $this->editTitle,
        'author' => $this->editAuthor ?: null,
        'year' => $this->editYear ?: null,
        'abstract' => $this->editAbstract ?: null,
        'conclusion' => $this->editConclusion ?: null,
        'keywords' => $this->editKeywords ?: null,
        'subjects' => $this->editSubjects ?: null,
        'divisions' => $this->editDivisions ?: null,
        'type' => $this->editType ?: null,
        'id_code' => $this->editIdCode ?: null,
      ]);
    }

    $this->closeEdit();
    $this->dispatch('toast', type: 'success', message: 'Data skripsi berhasil diperbarui.');
  }

  public function deleteSkripsi(int $id): void
  {
    Skripsi::where('id', $id)->delete();
    $this->closeDetail();
    $this->showDeleteConfirm = false;
    $this->deleteTargetId = null;
    $this->dispatch('toast', type: 'success', message: 'Data skripsi berhasil dihapus.');
  }

  public function exportCsv(): StreamedResponse
  {
    $query = $this->getRepositoryOrderedQuery();
    $filename = 'data-skripsi-' . now()->format('Ymd-His') . '.csv';

    return response()->streamDownload(function () use ($query) {
      $output = fopen('php://output', 'w');
      if ($output === false) {
        return;
      }

      // BOM agar karakter UTF-8 (mis. Bahasa Indonesia) tampil benar di Excel.
      fwrite($output, "\xEF\xBB\xBF");

      fputcsv($output, [
        'ID',
        'Judul',
        'Penulis',
        'Tahun',
        'Tipe',
        'ID Code',
        'Kata Kunci',
        'Subjects',
        'Divisions',
        'Abstrak',
        'Kesimpulan',
        'Sumber Kesimpulan',
        'URL',
        'Tanggal Deposit',
        'Tanggal Modifikasi',
      ]);

      foreach ($query->cursor() as $skripsi) {
        fputcsv($output, [
          $skripsi->id,
          $skripsi->title,
          $skripsi->author,
          $skripsi->year,
          $skripsi->type,
          $skripsi->id_code,
          $skripsi->keywords,
          $skripsi->subjects,
          $skripsi->divisions,
          $skripsi->abstract,
          $skripsi->conclusion,
          $skripsi->conclusion_source,
          $skripsi->url,
          $skripsi->deposit_date,
          $skripsi->modified_date,
        ]);
      }

      fclose($output);
    }, $filename, [
      'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
  }

  public function exportExcel(): StreamedResponse
  {
    $query = $this->getRepositoryOrderedQuery();
    $filename = 'data-skripsi-' . now()->format('Ymd-His') . '.xls';

    return response()->streamDownload(function () use ($query) {
      echo '<html><head><meta charset="UTF-8"></head><body>';
      echo '<table border="1">';
      echo '<thead><tr>';
      foreach ([
        'ID',
        'Judul',
        'Penulis',
        'Tahun',
        'Tipe',
        'ID Code',
        'Kata Kunci',
        'Subjects',
        'Divisions',
        'Abstrak',
        'Kesimpulan',
        'Sumber Kesimpulan',
        'URL',
        'Tanggal Deposit',
        'Tanggal Modifikasi',
      ] as $heading) {
        echo '<th>' . $this->escapeForExcel($heading) . '</th>';
      }
      echo '</tr></thead><tbody>';

      foreach ($query->cursor() as $skripsi) {
        echo '<tr>';
        foreach ([
          $skripsi->id,
          $skripsi->title,
          $skripsi->author,
          $skripsi->year,
          $skripsi->type,
          $skripsi->id_code,
          $skripsi->keywords,
          $skripsi->subjects,
          $skripsi->divisions,
          $skripsi->abstract,
          $skripsi->conclusion,
          $skripsi->conclusion_source,
          $skripsi->url,
          $skripsi->deposit_date,
          $skripsi->modified_date,
        ] as $value) {
          echo '<td>' . $this->escapeForExcel($value) . '</td>';
        }
        echo '</tr>';
      }

      echo '</tbody></table></body></html>';
    }, $filename, [
      'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
    ]);
  }

  public function importSkripsiCsv(): void
  {
    if (!$this->skripsiCsvFile) {
      $this->dispatch('toast', type: 'error', message: 'Pilih file CSV data skripsi terlebih dahulu.');
      return;
    }

    $filename = (string) ($this->skripsiCsvFile->getClientOriginalName() ?? '');
    if ($filename !== '' && !str_ends_with(strtolower($filename), '.csv')) {
      $this->dispatch('toast', type: 'error', message: 'File data skripsi harus berformat .csv');
      return;
    }

    $path = $this->skripsiCsvFile->getRealPath();
    if (!$path) {
      $this->dispatch('toast', type: 'error', message: 'File upload CSV data skripsi tidak bisa dibaca.');
      return;
    }

    $handle = @fopen($path, 'r');
    if ($handle === false) {
      $this->dispatch('toast', type: 'error', message: 'Gagal membuka file CSV data skripsi.');
      return;
    }

    try {
      $header = fgetcsv($handle);
      if (!is_array($header) || empty($header)) {
        $this->dispatch('toast', type: 'error', message: 'Header CSV data skripsi tidak valid atau kosong.');
        return;
      }

      $headerMap = [];
      foreach ($header as $idx => $rawName) {
        $normalized = $this->normalizeCsvHeader((string) $rawName);
        if ($normalized === '') {
          continue;
        }

        if (!array_key_exists($normalized, $headerMap)) {
          $headerMap[$normalized] = (int) $idx;
        }
      }

      $idIdx = $this->findCsvColumnIndex($headerMap, ['id', 'skripsi_id']);
      $titleIdx = $this->findCsvColumnIndex($headerMap, ['title', 'judul']);

      if ($idIdx === null || $titleIdx === null) {
        $this->dispatch(
          'toast',
          type: 'error',
          message: 'CSV data skripsi wajib punya kolom: id/skripsi_id dan title/judul.'
        );
        return;
      }

      $authorIdx = $this->findCsvColumnIndex($headerMap, ['author', 'penulis']);
      $yearIdx = $this->findCsvColumnIndex($headerMap, ['year', 'tahun']);
      $abstractIdx = $this->findCsvColumnIndex($headerMap, ['abstract', 'abstrak']);
      $conclusionIdx = $this->findCsvColumnIndex($headerMap, ['conclusion', 'kesimpulan']);
      $keywordsIdx = $this->findCsvColumnIndex($headerMap, ['keywords', 'kata_kunci']);
      $subjectsIdx = $this->findCsvColumnIndex($headerMap, ['subjects']);
      $divisionsIdx = $this->findCsvColumnIndex($headerMap, ['divisions']);
      $typeIdx = $this->findCsvColumnIndex($headerMap, ['type', 'tipe']);
      $idCodeIdx = $this->findCsvColumnIndex($headerMap, ['id_code', 'idcode']);
      $urlIdx = $this->findCsvColumnIndex($headerMap, ['url']);
      $uriIdx = $this->findCsvColumnIndex($headerMap, ['uri']);
      $conclusionSourceIdx = $this->findCsvColumnIndex($headerMap, ['conclusion_source', 'sumber_kesimpulan']);
      $depositDateIdx = $this->findCsvColumnIndex($headerMap, ['deposit_date', 'tanggal_deposit']);
      $modifiedDateIdx = $this->findCsvColumnIndex($headerMap, ['modified_date', 'tanggal_modifikasi']);
      $repositoryOrderIdx = $this->findCsvColumnIndex($headerMap, ['repository_order']);
      $cleanedIdx = $this->findCsvColumnIndex($headerMap, ['cleaned_text']);
      $processedIdx = $this->findCsvColumnIndex($headerMap, ['processed_text']);

      $rows = [];
      $validCount = 0;
      $skippedCount = 0;
      $lineOrder = 0;
      $now = now();

      while (($data = fgetcsv($handle)) !== false) {
        if (!is_array($data) || $data === [null]) {
          continue;
        }

        $lineOrder++;

        $idRaw = $this->csvCell($data, $idIdx);
        $title = $this->csvCell($data, $titleIdx);

        if ($idRaw === '' || !is_numeric($idRaw) || $title === '') {
          $skippedCount++;
          continue;
        }

        $skripsiId = (int) round((float) $idRaw);
        if ($skripsiId <= 0) {
          $skippedCount++;
          continue;
        }

        $url = $this->csvCell($data, $urlIdx);
        if ($url === '') {
          $url = $this->csvCell($data, $uriIdx);
        }
        if ($url === '') {
          $url = sprintf('imported://skripsi/%d', $skripsiId);
        }

        $repositoryOrder = $this->csvIntOrNull($this->csvCell($data, $repositoryOrderIdx));
        if ($repositoryOrder === null || $repositoryOrder <= 0) {
          $repositoryOrder = $lineOrder;
        }

        $rows[] = [
          'id' => $skripsiId,
          'title' => $title,
          'abstract' => $this->csvCell($data, $abstractIdx) ?: null,
          'cleaned_text' => $this->csvCell($data, $cleanedIdx) ?: null,
          'processed_text' => $this->csvCell($data, $processedIdx) ?: null,
          'type' => $this->csvCell($data, $typeIdx) ?: null,
          'id_code' => $this->csvCell($data, $idCodeIdx) ?: null,
          'keywords' => $this->csvCell($data, $keywordsIdx) ?: null,
          'subjects' => $this->csvCell($data, $subjectsIdx) ?: null,
          'divisions' => $this->csvCell($data, $divisionsIdx) ?: null,
          'author' => $this->csvCell($data, $authorIdx) ?: null,
          'deposit_date' => $this->csvCell($data, $depositDateIdx) ?: null,
          'modified_date' => $this->csvCell($data, $modifiedDateIdx) ?: null,
          'uri' => $this->csvCell($data, $uriIdx) ?: null,
          'year' => $this->csvIntOrNull($this->csvCell($data, $yearIdx)),
          'repository_order' => $repositoryOrder,
          'url' => $url,
          'conclusion' => $this->csvCell($data, $conclusionIdx) ?: null,
          'conclusion_source' => $this->csvCell($data, $conclusionSourceIdx) ?: null,
          'updated_at' => $now,
          'created_at' => $now,
        ];
        $validCount++;
      }

      if ($validCount <= 0) {
        $this->dispatch('toast', type: 'error', message: 'Tidak ada baris valid data skripsi untuk diimpor dari CSV.');
        return;
      }

      DB::transaction(function () use ($rows): void {
        foreach (array_chunk($rows, 300) as $chunk) {
          Skripsi::query()->upsert(
            $chunk,
            ['id'],
            [
              'title',
              'abstract',
              'cleaned_text',
              'processed_text',
              'type',
              'id_code',
              'keywords',
              'subjects',
              'divisions',
              'author',
              'deposit_date',
              'modified_date',
              'uri',
              'year',
              'repository_order',
              'url',
              'conclusion',
              'conclusion_source',
              'updated_at',
            ]
          );
        }
      });

      $this->skripsiCsvFile = null;
      $this->resetPage();

      $this->dispatch(
        'toast',
        type: 'success',
        message: sprintf('Import CSV data skripsi selesai. %d baris valid diproses, %d baris dilewati.', $validCount, $skippedCount)
      );
    } catch (\Throwable $e) {
      Log::error('CSV import to skripsi failed', ['error' => $e->getMessage()]);
      $this->dispatch('toast', type: 'error', message: 'Gagal import CSV data skripsi: ' . $e->getMessage());
    } finally {
      fclose($handle);
    }
  }

  protected function normalizeCsvHeader(string $header): string
  {
    $normalized = strtolower(trim($header));
    $normalized = str_replace(['-', '/', '.', '(', ')'], '_', $normalized);
    $normalized = preg_replace('/\s+/', '_', $normalized) ?? $normalized;
    return trim($normalized, '_');
  }

  protected function findCsvColumnIndex(array $headerMap, array $candidates): ?int
  {
    foreach ($candidates as $candidate) {
      if (array_key_exists($candidate, $headerMap)) {
        return (int) $headerMap[$candidate];
      }
    }

    return null;
  }

  protected function csvCell(array $row, ?int $index): string
  {
    if ($index === null) {
      return '';
    }

    return trim((string) ($row[$index] ?? ''));
  }

  protected function csvIntOrNull(string $value): ?int
  {
    if ($value === '' || !is_numeric($value)) {
      return null;
    }

    return (int) round((float) $value);
  }

  protected function getFilteredQuery(): Builder
  {
    return Skripsi::query()
      ->when($this->search, fn($q) => $q->where(function ($q) {
        $q->where('title', 'like', "%{$this->search}%")
          ->orWhere('author', 'like', "%{$this->search}%")
          ->orWhere('keywords', 'like', "%{$this->search}%")
          ->orWhere('abstract', 'like', "%{$this->search}%");
      }))
      ->when($this->yearFilter, fn($q) => $q->where('year', $this->yearFilter))
      ->orderBy($this->sortField, $this->sortDirection)
      ->orderBy('id', 'asc');
  }

  /**
   * Export order must follow original scraping listing order from repository.
   * Prefer explicit `repository_order` (stable + deterministic).
   * Falls back to legacy heuristic when the column does not exist yet.
   */
  protected function getRepositoryOrderedQuery(): Builder
  {
    $query = $this->getFilteredQuery()->reorder();

    static $hasRepositoryOrderColumn = null;
    if ($hasRepositoryOrderColumn === null) {
      $hasRepositoryOrderColumn = in_array(
        'repository_order',
        $query->getModel()->getConnection()->getSchemaBuilder()->getColumnListing($query->getModel()->getTable()),
        true,
      );
    }

    if ($hasRepositoryOrderColumn) {
      return $query
        ->orderByRaw('CASE WHEN repository_order IS NULL THEN 1 ELSE 0 END')
        ->orderBy('repository_order', 'asc')
        ->orderBy('id', 'asc');
    }

    $driver = $query->getModel()->getConnection()->getDriverName();

    if ($driver === 'mysql') {
      $query->orderByRaw("COALESCE(
          STR_TO_DATE(deposit_date, '%Y-%m-%d %H:%i:%s'),
          STR_TO_DATE(deposit_date, '%Y-%m-%d'),
          STR_TO_DATE(deposit_date, '%d %b %Y %H:%i:%s'),
          STR_TO_DATE(deposit_date, '%d %b %Y'),
          STR_TO_DATE(deposit_date, '%d %M %Y %H:%i:%s'),
          STR_TO_DATE(deposit_date, '%d %M %Y')
        ) DESC");
    }

    return $query
      ->orderByDesc('year')
      ->orderByRaw("LOWER(COALESCE(author, '')) ASC")
      ->orderByRaw("LOWER(COALESCE(title, '')) ASC")
      ->orderBy('id', 'asc');
  }

  protected function escapeForExcel(mixed $value): string
  {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  public function render()
  {
    $skripsiList = $this->getFilteredQuery()->paginate($this->perPage);

    // Get available years for filter
    $availableYears = Skripsi::selectRaw('DISTINCT year')
      ->whereNotNull('year')
      ->orderBy('year', 'desc')
      ->pluck('year')
      ->toArray();

    $totalSkripsi = Skripsi::count();

    return view('livewire.jurusan.skripsi-manager', compact('skripsiList', 'availableYears', 'totalSkripsi'));
  }
}
