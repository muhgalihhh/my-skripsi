<?php

namespace App\Livewire\Jurusan;

use App\Models\Skripsi;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.jurusan')]
#[Title('Manajemen Skripsi')]
class SkripsiManager extends Component
{
  use WithPagination;

  #[Url]
  public string $search = '';

  #[Url]
  public string $yearFilter = '';

  #[Url]
  public string $sortField = 'year';

  #[Url]
  public string $sortDirection = 'desc';

  public int $perPage = 15;

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
