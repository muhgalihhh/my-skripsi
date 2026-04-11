<?php

namespace App\Livewire\Mahasiswa;

use App\Models\TopicModelTopic;
use App\Models\TopicModelTopicDocument;
use App\Services\FastApiService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.mahasiswa')]
#[Title('Detail Rekomendasi Judul Skripsi')]
class TitleRecommendationDetail extends Component
{
    public int $topicRowId;

    public string $userPrompt = '';

    public int $recommendationsCount = 5;

    public array $recommendationItems = [];

    public ?string $recommendationError = null;

    public array $topicContext = [];

    public array $mappedSkripsi = [];

    protected int $mappedRowsLimit = 240;

    public function mount(int $topicRowId): void
    {
        $topic = TopicModelTopic::query()
            ->with('run:id,model_type,status')
            ->where('id', $topicRowId)
            ->whereHas('run', function ($query) {
                $query->where('status', 'completed')
                    ->where('model_type', 'bertopic');
            })
            ->first();

        if (!$topic) {
            abort(404, 'Topik tidak ditemukan.');
        }

        $topicLabel = filled($topic->custom_name)
            ? sprintf('T%s - %s', $topic->topic_id, $topic->custom_name)
            : sprintf('Topik %s', $topic->topic_id);

        $this->topicRowId = (int) $topic->id;
        $this->topicContext = [
            'topic_row_id' => (int) $topic->id,
            'topic_id' => (int) $topic->topic_id,
            'run_id' => (int) $topic->topic_model_run_id,
            'topic_label' => $topicLabel,
            'doc_count' => (int) ($topic->count ?? 0),
            'top_words' => array_slice((array) ($topic->top_words ?? []), 0, 10),
        ];

        $this->mappedSkripsi = $this->loadMappedSkripsi(
            topicRowId: (int) $topic->id,
            runId: (int) $topic->topic_model_run_id,
        );
    }

    public function generateRecommendations(): void
    {
        $this->validate([
            'userPrompt' => 'required|string|min:5|max:1200',
            'recommendationsCount' => 'required|integer|min:3|max:10',
        ]);

        $this->recommendationError = null;
        $this->recommendationItems = [];

        $prompt = $this->normalizeText($this->userPrompt);
        if (!$this->isPromptInContext($prompt)) {
            $this->recommendationError = 'Prompt di luar konteks topik skripsi. Sertakan konteks judul/topik/riset yang relevan dengan kata kunci topik.';
            return;
        }

        $payload = [
            'topic_id' => (int) ($this->topicContext['topic_id'] ?? 0),
            'topic_label' => (string) ($this->topicContext['topic_label'] ?? ''),
            'topic_keywords' => $this->topicContext['top_words'] ?? [],
            'mapped_titles' => $this->extractMappedTitlesForContext(),
            'user_prompt' => $prompt,
            'recommendations_count' => (int) $this->recommendationsCount,
            'strict_context' => true,
        ];

        $response = app(FastApiService::class)->generateSkripsiTitleRecommendations($payload);

        if (($response['status'] ?? '') !== 'ok') {
            $this->recommendationError = trim((string) ($response['message'] ?? 'Gagal menghasilkan rekomendasi judul.'));
            return;
        }

        $items = [];
        $rawItems = is_array($response['recommendations'] ?? null) ? $response['recommendations'] : [];

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $title = $this->normalizeText((string) ($rawItem['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $items[] = [
                'title' => mb_substr($title, 0, 240),
                'rationale' => mb_substr($this->normalizeText((string) ($rawItem['rationale'] ?? '')), 0, 600),
            ];
        }

        if (empty($items)) {
            $this->recommendationError = 'AI tidak mengembalikan rekomendasi judul yang valid.';
            return;
        }

        $this->recommendationItems = $items;
    }

    private function loadMappedSkripsi(int $topicRowId, int $runId): array
    {
        return TopicModelTopicDocument::query()
            ->join('skripsi', 'skripsi.id', '=', 'topic_model_topic_documents.skripsi_id')
            ->where('topic_model_topic_documents.topic_model_topic_id', $topicRowId)
            ->where('topic_model_topic_documents.topic_model_run_id', $runId)
            ->select([
                'topic_model_topic_documents.skripsi_id as skripsi_id',
                'skripsi.title as title',
                'skripsi.author as author',
                'skripsi.year as year',
                'skripsi.url as url',
            ])
            ->orderByDesc('skripsi.year')
            ->orderBy('topic_model_topic_documents.skripsi_id')
            ->limit($this->mappedRowsLimit)
            ->get()
            ->map(function ($row): array {
                return [
                    'skripsi_id' => (int) ($row->skripsi_id ?? 0),
                    'title' => trim((string) ($row->title ?? 'Tanpa judul')),
                    'author' => trim((string) ($row->author ?? '-')),
                    'year' => is_numeric($row->year) ? (int) $row->year : null,
                    'url' => filled($row->url) ? (string) $row->url : null,
                ];
            })
            ->values()
            ->all();
    }

    private function extractMappedTitlesForContext(): array
    {
        $titles = [];

        foreach ($this->mappedSkripsi as $row) {
            $title = $this->normalizeText((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $titles[] = mb_substr($title, 0, 220);
        }

        return array_slice(array_values(array_unique($titles)), 0, 80);
    }

    private function isPromptInContext(string $prompt): bool
    {
        $promptTokens = $this->extractTokens($prompt);
        if (empty($promptTokens)) {
            return false;
        }

        $anchorTokens = [
            'skripsi',
            'judul',
            'penelitian',
            'riset',
            'topik',
            'metode',
            'analisis',
            'model',
            'sistem',
            'dataset',
            'algoritma',
            'klasifikasi',
            'prediksi',
            'deteksi',
            'optimasi',
        ];

        $hasAnchor = !empty(array_intersect($promptTokens, $anchorTokens));

        $contextTokens = [];

        foreach ($this->topicContext['top_words'] ?? [] as $keyword) {
            $contextTokens = array_merge($contextTokens, $this->extractTokens((string) $keyword));
        }

        foreach (array_slice($this->mappedSkripsi, 0, 40) as $row) {
            $contextTokens = array_merge($contextTokens, $this->extractTokens((string) ($row['title'] ?? '')));
        }

        $contextTokens = array_values(array_unique($contextTokens));
        $overlapCount = count(array_intersect($promptTokens, $contextTokens));

        return $overlapCount >= 1 || $hasAnchor;
    }

    private function extractTokens(string $text): array
    {
        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;

        $rawTokens = preg_split('/\s+/u', trim($normalized)) ?: [];

        $stopwords = [
            'dan', 'yang', 'untuk', 'dengan', 'pada', 'dari', 'atau', 'the', 'of', 'in', 'to',
            'di', 'ke', 'sebagai', 'dalam', 'berbasis', 'menggunakan', 'tentang', 'pada', 'agar',
        ];

        return collect($rawTokens)
            ->filter(fn($token) => is_string($token) && mb_strlen($token) >= 3)
            ->reject(fn($token) => in_array($token, $stopwords, true))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    public function render()
    {
        return view('livewire.mahasiswa.title-recommendation-detail', [
            'topicContext' => $this->topicContext,
            'mappedSkripsi' => $this->mappedSkripsi,
            'recommendationItems' => $this->recommendationItems,
            'recommendationError' => $this->recommendationError,
        ]);
    }
}
