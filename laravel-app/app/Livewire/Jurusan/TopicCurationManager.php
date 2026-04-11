<?php

namespace App\Livewire\Jurusan;

use App\Models\TopicModelRun;
use App\Models\TopicModelTopic;
use App\Models\TopicModelTopicDocument;
use App\Services\FastApiService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.jurusan')]
#[Title('Manajemen Topik')]
class TopicCurationManager extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $runFilter = '';

    public int $perPage = 15;

    // Edit modal state
    public bool $showEditModal = false;
    public ?int $editTopicRowId = null;
    public ?int $editTopicId = null;
    public ?int $editRunId = null;
    public array $editTopWords = [];
    public string $editCustomName = '';
    public string $editRepresentationDescription = '';

    public function mount(): void
    {
        /** @var int|null $userId */
        $userId = Auth::id();

        if ($userId === null) {
            return;
        }

        $latestCompletedRunId = TopicModelRun::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->latest('id')
            ->value('id');

        if ($latestCompletedRunId !== null) {
            $this->runFilter = (string) $latestCompletedRunId;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRunFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->runFilter = '';
        $this->perPage = 15;
        $this->resetPage();
    }

    public function openEditModal(int $topicRowId): void
    {
        /** @var int|null $userId */
        $userId = Auth::id();

        if ($userId === null) {
            $this->dispatch('toast', type: 'error', message: 'Silakan login terlebih dahulu.');
            return;
        }

        $topic = TopicModelTopic::query()
            ->where('id', $topicRowId)
            ->whereHas('run', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'completed');
            })
            ->first();

        if (!$topic) {
            $this->dispatch('toast', type: 'error', message: 'Topik tidak ditemukan.');
            return;
        }

        $this->editTopicRowId = (int) $topic->id;
        $this->editTopicId = (int) $topic->topic_id;
        $this->editRunId = (int) $topic->topic_model_run_id;
        $this->editTopWords = is_array($topic->top_words) ? array_slice($topic->top_words, 0, 10) : [];
        $this->editCustomName = (string) ($topic->custom_name ?? '');
        $this->editRepresentationDescription = (string) ($topic->representation_description ?? '');
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editTopicRowId = null;
        $this->editTopicId = null;
        $this->editRunId = null;
        $this->editTopWords = [];
        $this->editCustomName = '';
        $this->editRepresentationDescription = '';
        $this->resetValidation();
    }

    public function saveTopicCuration(): void
    {
        $this->validate([
            'editCustomName' => 'nullable|string|max:150',
            'editRepresentationDescription' => 'nullable|string|max:2000',
        ]);

        if (!$this->editTopicRowId) {
            $this->dispatch('toast', type: 'error', message: 'Topik belum dipilih.');
            return;
        }

        /** @var int|null $userId */
        $userId = Auth::id();

        if ($userId === null) {
            $this->dispatch('toast', type: 'error', message: 'Silakan login terlebih dahulu.');
            return;
        }

        $topic = TopicModelTopic::query()
            ->where('id', $this->editTopicRowId)
            ->whereHas('run', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'completed');
            })
            ->first();

        if (!$topic) {
            $this->dispatch('toast', type: 'error', message: 'Topik tidak ditemukan atau tidak dapat diedit.');
            return;
        }

        $customName = trim($this->editCustomName);
        $description = trim($this->editRepresentationDescription);

        $topic->update([
            'custom_name' => $customName !== '' ? $customName : null,
            'representation_description' => $description !== '' ? $description : null,
        ]);

        $this->dispatch('toast', type: 'success', message: 'Kurasi topik berhasil disimpan.');
        $this->closeEditModal();
    }

    public function generateAiSuggestion(): void
    {
        if (!$this->editTopicRowId) {
            $this->dispatch('toast', type: 'error', message: 'Topik belum dipilih.');
            return;
        }

        /** @var int|null $userId */
        $userId = Auth::id();

        if ($userId === null) {
            $this->dispatch('toast', type: 'error', message: 'Silakan login terlebih dahulu.');
            return;
        }

        $topic = TopicModelTopic::query()
            ->where('id', $this->editTopicRowId)
            ->whereHas('run', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'completed');
            })
            ->first();

        if (!$topic) {
            $this->dispatch('toast', type: 'error', message: 'Topik tidak ditemukan atau tidak dapat diproses.');
            return;
        }

        $payload = $this->buildTopicCurationContextPayload($topic);
        if (empty($payload['keywords'])) {
            $this->dispatch('toast', type: 'error', message: 'Kata kunci topik kosong, AI tidak bisa diproses.');
            return;
        }

        try {
            $fastApi = app(FastApiService::class);
            $suggestion = $fastApi->generateTopicCurationSuggestion($payload);

            if (($suggestion['status'] ?? '') !== 'ok') {
                $message = trim((string) ($suggestion['message'] ?? 'Gagal membuat saran AI. Silakan coba lagi.'));
                if ($message === '') {
                    $message = 'Gagal membuat saran AI. Silakan coba lagi.';
                }

                throw new \RuntimeException($message);
            }

            $customName = trim((string) ($suggestion['custom_name'] ?? ''));
            $description = trim((string) ($suggestion['representation_description'] ?? ''));

            if ($customName === '' || $description === '') {
                throw new \RuntimeException('Respons AI kosong atau tidak lengkap.');
            }

            $this->editCustomName = $customName;
            $this->editRepresentationDescription = $description;

            $this->dispatch('toast', type: 'success', message: 'Saran AI berhasil dibuat. Silakan review lalu simpan.');
        } catch (Throwable $e) {
            report($e);

            $message = trim($e->getMessage());
            if ($message === '') {
                $message = 'Gagal membuat saran AI. Silakan coba lagi.';
            }

            $this->dispatch('toast', type: 'error', message: $message);
        }
    }

    /**
     * Build topic context for AI using keywords and mapped reference titles.
     *
     * @return array<string, mixed>
     */
    private function buildTopicCurationContextPayload(TopicModelTopic $topic): array
    {
        $keywords = is_array($topic->top_words) ? array_slice($topic->top_words, 0, 10) : [];
        $keywords = array_values(array_filter(array_map(
            static fn($keyword): string => trim((string) $keyword),
            $keywords
        ), static fn(string $keyword): bool => $keyword !== ''));

        $titleCandidates = TopicModelTopicDocument::query()
            ->join('skripsi', 'skripsi.id', '=', 'topic_model_topic_documents.skripsi_id')
            ->where('topic_model_topic_documents.topic_model_topic_id', (int) $topic->id)
            ->where('topic_model_topic_documents.topic_model_run_id', (int) $topic->topic_model_run_id)
            ->orderByDesc('topic_model_topic_documents.id')
            ->pluck('skripsi.title')
            ->all();

        $representativeTitles = [];

        foreach ($titleCandidates as $titleCandidate) {
            $title = $this->normalizeText((string) $titleCandidate);
            if ($title !== '') {
                $representativeTitles[] = mb_substr($title, 0, 180);
            }
        }

        $representativeTitles = array_values(array_unique($representativeTitles));

        $payload = [
            'keywords' => $keywords,
        ];

        if (!empty($representativeTitles)) {
            $payload['representative_titles'] = $representativeTitles;
        }

        return $payload;
    }

    private function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    public function render()
    {
        /** @var int|null $userId */
        $userId = Auth::id();

        if ($userId === null) {
            return view('livewire.jurusan.topic-curation-manager', [
                'completedRuns' => collect(),
                'topics' => TopicModelTopic::query()->whereRaw('1 = 0')->paginate($this->perPage),
                'curatedCount' => 0,
            ]);
        }

        $completedRuns = TopicModelRun::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'model_type', 'num_topics', 'completed_at']);

        $topicsQuery = TopicModelTopic::query()
            ->with(['run:id,user_id,model_type,status,coherence_cv,topic_diversity,completed_at'])
            ->whereHas('run', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'completed');
            });

        if ($this->runFilter !== '') {
            $topicsQuery->where('topic_model_run_id', (int) $this->runFilter);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $topicsQuery->where(function ($query) use ($search) {
                $query->where('custom_name', 'like', "%{$search}%")
                    ->orWhere('representation_description', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $query->orWhere('topic_id', (int) $search)
                        ->orWhere('topic_model_run_id', (int) $search);
                }
            });
        }

        $topics = $topicsQuery
            ->orderByDesc('topic_model_run_id')
            ->orderBy('topic_id')
            ->paginate($this->perPage);

        $curatedCount = TopicModelTopic::query()
            ->whereHas('run', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'completed');
            })
            ->where(function ($query) {
                $query->whereNotNull('custom_name')
                    ->orWhereNotNull('representation_description');
            })
            ->count();

        return view('livewire.jurusan.topic-curation-manager', [
            'completedRuns' => $completedRuns,
            'topics' => $topics,
            'curatedCount' => $curatedCount,
        ]);
    }
}
