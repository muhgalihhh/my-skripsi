<?php

namespace App\Livewire\Jurusan;

use App\Models\Skripsi;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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
  public string $sortField = 'created_at';

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
      $this->selectedIds = Skripsi::query()
        ->when($this->search, fn($q) => $q->where(function ($q) {
          $q->where('title', 'like', "%{$this->search}%")
            ->orWhere('author', 'like', "%{$this->search}%")
            ->orWhere('keywords', 'like', "%{$this->search}%");
        }))
        ->when($this->yearFilter, fn($q) => $q->where('year', $this->yearFilter))
        ->orderBy($this->sortField, $this->sortDirection)
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

  public function render()
  {
    $query = Skripsi::query();

    // Search
    if ($this->search) {
      $query->where(function ($q) {
        $q->where('title', 'like', "%{$this->search}%")
          ->orWhere('author', 'like', "%{$this->search}%")
          ->orWhere('keywords', 'like', "%{$this->search}%")
          ->orWhere('abstract', 'like', "%{$this->search}%");
      });
    }

    // Year filter
    if ($this->yearFilter) {
      $query->where('year', $this->yearFilter);
    }

    // Sort
    $query->orderBy($this->sortField, $this->sortDirection);

    $skripsiList = $query->paginate($this->perPage);

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
