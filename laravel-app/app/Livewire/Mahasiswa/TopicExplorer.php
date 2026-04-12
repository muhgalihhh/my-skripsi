<?php

namespace App\Livewire\Mahasiswa;

use App\Models\TopicModelRun;
use App\Models\TopicModelTopic;
use App\Models\TopicModelTopicDocument;
use App\Services\FastApiService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.mahasiswa')]
#[Title('Dashboard Mahasiswa')]
class TopicExplorer extends Component
{
    public string $searchQuery = '';

    public array $smartSearchResult = [];

    protected int $topicCardLimit = 8;
    protected int $mappingRowsLimit = 1200;
    protected int $dtmSeriesLimit = 8;
    protected int $searchTopTopics = 5;
    protected int $searchCandidateLimit = 260;
    protected int $searchResultLimit = 20;
    protected int $wordCloudWordsPerTopic = 10;

    public function mount(): void
    {
        $this->smartSearchResult = $this->emptySmartSearchState();
    }

    #[On('mahasiswa-search-submit')]
    public function runSmartSearchFromTopbar(string $query = ''): void
    {
        $this->searchQuery = trim($query);
        $this->runSmartSearch();
    }

    public function runSmartSearch(): void
    {
        $query = trim($this->searchQuery);
        $queryLength = mb_strlen($query);
        if ($queryLength < 3 || $queryLength > 500) {
            $this->smartSearchResult = [
                'status' => 'error',
                'message' => 'Query smart search harus 3-500 karakter.',
                'query' => $query,
                'predicted_topic' => null,
                'topic_distribution' => [],
                'items' => [],
                'used_fallback' => false,
            ];
            return;
        }

        $this->searchQuery = $query;
        $activeRun = $this->resolveActiveRun();

        if (!$activeRun) {
            $this->smartSearchResult = [
                'status' => 'error',
                'message' => 'Belum ada model BERTopic completed yang bisa dipakai untuk smart search.',
                'query' => $query,
                'predicted_topic' => null,
                'topic_distribution' => [],
                'items' => [],
                'used_fallback' => false,
            ];
            return;
        }

        $jobId = trim((string) ($activeRun->fastapi_training_job_id ?? ''));
        if ($jobId === '') {
            $this->smartSearchResult = [
                'status' => 'error',
                'message' => 'Run aktif belum memiliki job id FastAPI, jadi smart search belum bisa dijalankan.',
                'query' => $query,
                'predicted_topic' => null,
                'topic_distribution' => [],
                'items' => [],
                'used_fallback' => false,
            ];
            return;
        }

        $fastApiResponse = app(FastApiService::class)->inferTopicForQuery($jobId, $query, $this->searchTopTopics);

        $status = (string) ($fastApiResponse['status'] ?? 'ok');
        if (in_array($status, ['error', 'unreachable', 'not_found'], true)) {
            $fallbackItems = $this->buildKeywordFallbackResults($activeRun, $query);

            $this->smartSearchResult = [
                'status' => 'warning',
                'message' => ($fastApiResponse['message'] ?? 'Smart search BERTopic gagal dipanggil.')
                    . ' Menampilkan fallback berbasis kemiripan kata kunci.',
                'query' => $query,
                'predicted_topic' => null,
                'topic_distribution' => [],
                'items' => $fallbackItems,
                'used_fallback' => true,
            ];
            return;
        }

        $this->smartSearchResult = $this->buildSmartSearchResults($activeRun, $query, $fastApiResponse);
    }

    protected function emptySmartSearchState(): array
    {
        return [
            'status' => 'idle',
            'message' => null,
            'query' => null,
            'predicted_topic' => null,
            'topic_distribution' => [],
            'items' => [],
            'used_fallback' => false,
        ];
    }

    protected function resolveDefaultRunId(): ?int
    {
        $latestRunWithMappingId = TopicModelTopicDocument::query()
            ->join('topic_model_runs', 'topic_model_runs.id', '=', 'topic_model_topic_documents.topic_model_run_id')
            ->where('topic_model_runs.status', 'completed')
            ->where('topic_model_runs.model_type', 'bertopic')
            ->orderByDesc('topic_model_topic_documents.topic_model_run_id')
            ->value('topic_model_topic_documents.topic_model_run_id');

        if ($latestRunWithMappingId !== null) {
            return (int) $latestRunWithMappingId;
        }

        $latestCompletedRunId = TopicModelRun::query()
            ->where('status', 'completed')
            ->where('model_type', 'bertopic')
            ->latest('id')
            ->value('id');

        return $latestCompletedRunId !== null ? (int) $latestCompletedRunId : null;
    }

    protected function resolveActiveRun(): ?TopicModelRun
    {
        $latestRunId = $this->resolveDefaultRunId();
        if ($latestRunId !== null) {
            return TopicModelRun::query()
                ->where('id', $latestRunId)
                ->where('status', 'completed')
                ->where('model_type', 'bertopic')
                ->first();
        }

        return TopicModelRun::query()
            ->where('status', 'completed')
            ->where('model_type', 'bertopic')
            ->latest('id')
            ->first();
    }

    protected function buildTopicLabelMap(Collection $topics): array
    {
        $labels = [];
        foreach ($topics as $topic) {
            $labels[(int) $topic->topic_id] = filled($topic->custom_name)
                ? sprintf('T%s - %s', $topic->topic_id, $topic->custom_name)
                : sprintf('Topik %s', $topic->topic_id);
        }

        return $labels;
    }

    protected function buildTopicCards(TopicModelRun $run, Collection $topics): array
    {
        $rows = TopicModelTopicDocument::query()
            ->join('skripsi', 'skripsi.id', '=', 'topic_model_topic_documents.skripsi_id')
            ->where('topic_model_topic_documents.topic_model_run_id', $run->id)
            ->select([
                'topic_model_topic_documents.topic_id as topic_id',
                'topic_model_topic_documents.skripsi_id as skripsi_id',
                'skripsi.title as title',
                'skripsi.author as author',
                'skripsi.year as year',
                'skripsi.url as url',
            ])
            ->orderBy('topic_model_topic_documents.topic_id')
            ->orderByDesc('skripsi.year')
            ->orderBy('topic_model_topic_documents.skripsi_id')
            ->limit($this->mappingRowsLimit)
            ->get()
            ->groupBy('topic_id');

        $cards = [];

        foreach ($topics->take($this->topicCardLimit) as $topic) {
            $topicId = (int) $topic->topic_id;
            $documents = collect($rows->get($topicId, collect()))
                ->map(function ($row) {
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

            $cards[] = [
                'topic_id' => $topicId,
                'topic_label' => filled($topic->custom_name)
                    ? sprintf('T%s - %s', $topicId, $topic->custom_name)
                    : sprintf('Topik %s', $topicId),
                'doc_count' => (int) ($topic->count ?? 0),
                'top_words' => array_slice($topic->top_words ?? [], 0, 15),
                'documents' => $documents,
            ];
        }

        return $cards;
    }

    protected function buildTopicDistributionPayload(Collection $topics): array
    {
        $topTopics = $topics->take(12);

        return [
            'labels' => $topTopics
                ->map(fn($topic) => filled($topic->custom_name)
                    ? sprintf('T%s - %s', $topic->topic_id, $topic->custom_name)
                    : sprintf('Topik %s', $topic->topic_id))
                ->values()
                ->all(),
            'counts' => $topTopics
                ->map(fn($topic) => (int) ($topic->count ?? 0))
                ->values()
                ->all(),
        ];
    }

    protected function buildWordCloudTopics(Collection $topics): array
    {
        $clouds = [];

        foreach ($topics as $topic) {
            $topWords = [];
            if (is_array($topic->top_words)) {
                foreach ($topic->top_words as $rawWord) {
                    $token = mb_strtolower(trim((string) $rawWord));
                    if ($token === '') {
                        continue;
                    }

                    if (isset($topWords[$token])) {
                        continue;
                    }

                    $topWords[$token] = $token;

                    if (count($topWords) >= $this->wordCloudWordsPerTopic) {
                        break;
                    }
                }
            }

            $topWords = array_values($topWords);
            if (empty($topWords)) {
                continue;
            }

            $scoreMap = $this->buildTopicScoreMap($topWords, is_array($topic->word_scores ?? null) ? $topic->word_scores : []);
            $weights = [];

            foreach ($topWords as $index => $word) {
                $token = trim((string) $word);
                if ($token === '') {
                    continue;
                }

                $normalized = mb_strtolower($token);
                $fallbackScore = max(0.1, (float) (count($topWords) - $index));
                $weights[$normalized] = $scoreMap[$normalized] ?? $fallbackScore;
            }

            if (empty($weights)) {
                continue;
            }

            arsort($weights);

            $maxWeight = max($weights);
            $minWeight = min($weights);

            $words = [];
            foreach ($weights as $word => $weight) {
                $normalized = ($maxWeight > $minWeight)
                    ? (($weight - $minWeight) / ($maxWeight - $minWeight))
                    : 1.0;

                $words[] = [
                    'text' => $word,
                    'weight' => round(18 + ($normalized * 40), 2),
                    'raw_weight' => round((float) $weight, 4),
                ];
            }

            $clouds[] = [
                'topic_id' => (int) $topic->topic_id,
                'label' => filled($topic->custom_name)
                    ? sprintf('T%s - %s', $topic->topic_id, $topic->custom_name)
                    : sprintf('Topik %s', $topic->topic_id),
                'doc_count' => (int) ($topic->count ?? 0),
                'words' => $words,
            ];
        }

        return $clouds;
    }

    protected function buildTopicScoreMap(array $topWords, array $wordScores): array
    {
        $map = [];

        if (empty($wordScores)) {
            return $map;
        }

        if (!array_is_list($wordScores)) {
            foreach ($wordScores as $word => $score) {
                if (!is_numeric($score)) {
                    continue;
                }

                $numericScore = abs((float) $score);
                $map[mb_strtolower((string) $word)] = $numericScore > 0 ? $numericScore : 1.0e-12;
            }

            return $map;
        }

        foreach ($topWords as $index => $word) {
            $score = $wordScores[$index] ?? null;
            if (!is_numeric($score)) {
                continue;
            }

            $numericScore = abs((float) $score);
            $map[mb_strtolower((string) $word)] = $numericScore > 0 ? $numericScore : 1.0e-12;
        }

        return $map;
    }

    protected function buildDtmPayloadFromDbMapping(TopicModelRun $run, Collection $topics): array
    {
        $raw = TopicModelTopicDocument::query()
            ->selectRaw('topic_model_topic_documents.topic_id as topic_id, skripsi.year as year, COUNT(*) as doc_count')
            ->join('skripsi', 'skripsi.id', '=', 'topic_model_topic_documents.skripsi_id')
            ->where('topic_model_topic_documents.topic_model_run_id', $run->id)
            ->whereNotNull('skripsi.year')
            ->groupBy('topic_model_topic_documents.topic_id', 'skripsi.year')
            ->orderBy('skripsi.year')
            ->get();

        if ($raw->isEmpty()) {
            return [
                'years' => [],
                'series' => [],
                'has_data' => false,
                'missing_reason' => 'Belum ada data mapping dokumen-topik berdasarkan tahun.',
            ];
        }

        $topicLabelMap = $this->buildTopicLabelMap($topics);

        $years = $raw->pluck('year')->map(fn($year) => (int) $year)->unique()->sort()->values()->all();

        $topicTotals = $raw
            ->groupBy('topic_id')
            ->map(fn(Collection $rows) => (int) $rows->sum('doc_count'))
            ->sortDesc();

        $selectedTopicIds = $topicTotals
            ->keys()
            ->take($this->dtmSeriesLimit)
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

        $matrix = [];
        $yearTotals = [];

        foreach ($raw as $row) {
            $topicId = (int) $row->topic_id;
            $year = (int) $row->year;
            $count = (int) $row->doc_count;

            $matrix[$topicId][$year] = $count;
            $yearTotals[$year] = ($yearTotals[$year] ?? 0) + $count;
        }

        $series = [];
        foreach ($selectedTopicIds as $topicId) {
            $points = [];
            foreach ($years as $year) {
                $count = (int) ($matrix[$topicId][$year] ?? 0);
                $total = (int) ($yearTotals[$year] ?? 0);
                $sharePct = $total > 0 ? ($count / $total) * 100 : 0;
                $points[] = round($sharePct, 4);
            }

            $series[] = [
                'topic_id' => $topicId,
                'label' => $topicLabelMap[$topicId] ?? sprintf('Topik %s', $topicId),
                'data' => $points,
            ];
        }

        return [
            'years' => $years,
            'series' => $series,
            'has_data' => !empty($series),
            'missing_reason' => null,
        ];
    }

    protected function extractSearchTokens(string $query): array
    {
        $normalized = mb_strtolower($query);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;

        $rawTokens = preg_split('/\s+/u', trim($normalized)) ?: [];

        $stopwords = [
            'dan', 'yang', 'untuk', 'dengan', 'pada', 'dari', 'atau', 'the', 'of', 'in', 'to',
            'di', 'ke', 'sebagai', 'dalam', 'analisis', 'studi', 'berbasis', 'menggunakan',
        ];

        return collect($rawTokens)
            ->filter(fn($token) => is_string($token) && mb_strlen($token) >= 3)
            ->reject(fn($token) => in_array($token, $stopwords, true))
            ->unique()
            ->values()
            ->all();
    }

    protected function buildSmartSearchResults(TopicModelRun $run, string $query, array $inference): array
    {
        $topicWeights = [];
        $distribution = [];

        if (isset($inference['topic_distribution']) && is_array($inference['topic_distribution'])) {
            foreach ($inference['topic_distribution'] as $row) {
                if (!is_array($row) || !isset($row['topic_id'])) {
                    continue;
                }

                $topicId = (int) $row['topic_id'];
                if ($topicId < 0) {
                    continue;
                }

                $similarity = isset($row['similarity']) && is_numeric($row['similarity'])
                    ? max(0.0, min(1.0, (float) $row['similarity']))
                    : 0.0;

                $topicWeights[$topicId] = max($topicWeights[$topicId] ?? 0.0, $similarity);
                $distribution[] = [
                    'topic_id' => $topicId,
                    'similarity' => $similarity,
                    'top_words' => array_slice((array) ($row['top_words'] ?? []), 0, 15),
                ];
            }
        }

        $predictedTopicId = isset($inference['topic_id']) ? (int) $inference['topic_id'] : -1;
        $predictedSimilarity = isset($inference['topic_similarity']) && is_numeric($inference['topic_similarity'])
            ? max(0.0, min(1.0, (float) $inference['topic_similarity']))
            : 0.0;

        if ($predictedTopicId >= 0) {
            $topicWeights[$predictedTopicId] = max($topicWeights[$predictedTopicId] ?? 0.0, $predictedSimilarity);
        }

        arsort($topicWeights);

        $candidateTopicIds = collect(array_keys($topicWeights))
            ->take($this->searchTopTopics)
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

        if (empty($candidateTopicIds) && $predictedTopicId >= 0) {
            $candidateTopicIds = [$predictedTopicId];
        }

        if (empty($candidateTopicIds)) {
            return [
                'status' => 'warning',
                'message' => 'Model tidak menemukan topik yang cukup relevan untuk query ini.',
                'query' => $query,
                'predicted_topic' => null,
                'topic_distribution' => $distribution,
                'items' => [],
                'used_fallback' => false,
            ];
        }

        $topicList = TopicModelTopic::query()
            ->where('topic_model_run_id', $run->id)
            ->get(['topic_id', 'custom_name']);
        $topicLabelMap = $this->buildTopicLabelMap($topicList);

        $candidateRows = TopicModelTopicDocument::query()
            ->join('skripsi', 'skripsi.id', '=', 'topic_model_topic_documents.skripsi_id')
            ->where('topic_model_topic_documents.topic_model_run_id', $run->id)
            ->whereIn('topic_model_topic_documents.topic_id', $candidateTopicIds)
            ->select([
                'topic_model_topic_documents.topic_id as topic_id',
                'topic_model_topic_documents.skripsi_id as skripsi_id',
                'skripsi.title as title',
                'skripsi.abstract as abstract',
                'skripsi.keywords as keywords',
                'skripsi.author as author',
                'skripsi.year as year',
                'skripsi.url as url',
            ])
            ->orderByDesc('skripsi.year')
            ->limit($this->searchCandidateLimit)
            ->get();

        $queryTokens = $this->extractSearchTokens($query);

        $yearSeries = $candidateRows
            ->map(fn($row) => is_numeric($row->year) ? (int) $row->year : null)
            ->filter(fn($year) => $year !== null)
            ->values();

        $yearMin = $yearSeries->isNotEmpty() ? (int) $yearSeries->min() : null;
        $yearMax = $yearSeries->isNotEmpty() ? (int) $yearSeries->max() : null;

        $scored = [];
        foreach ($candidateRows as $row) {
            $topicId = (int) ($row->topic_id ?? -1);
            $topicScore = (float) ($topicWeights[$topicId] ?? 0.0);

            $text = mb_strtolower(trim(
                ((string) ($row->title ?? '')) . ' '
                . ((string) ($row->abstract ?? '')) . ' '
                . ((string) ($row->keywords ?? ''))
            ));

            $hitCount = 0;
            foreach ($queryTokens as $token) {
                if (str_contains($text, $token)) {
                    $hitCount++;
                }
            }

            $keywordScore = count($queryTokens) > 0 ? ($hitCount / count($queryTokens)) : 0.0;

            $yearScore = 0.0;
            if (is_numeric($row->year) && $yearMin !== null && $yearMax !== null && $yearMax > $yearMin) {
                $yearScore = ((int) $row->year - $yearMin) / ($yearMax - $yearMin);
            }

            $score = ($topicScore * 0.72) + ($keywordScore * 0.23) + ($yearScore * 0.05);

            $scored[] = [
                'topic_id' => $topicId,
                'topic_label' => $topicLabelMap[$topicId] ?? sprintf('Topik %s', $topicId),
                'skripsi_id' => (int) ($row->skripsi_id ?? 0),
                'title' => trim((string) ($row->title ?? 'Tanpa judul')),
                'author' => trim((string) ($row->author ?? '-')),
                'year' => is_numeric($row->year) ? (int) $row->year : null,
                'url' => filled($row->url) ? (string) $row->url : null,
                'score' => round($score, 4),
                'topic_similarity' => round($topicScore, 4),
                'keyword_match' => round($keywordScore, 4),
            ];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        $predictedTopic = null;
        if ($predictedTopicId >= 0) {
            $predictedTopic = [
                'topic_id' => $predictedTopicId,
                'topic_label' => $topicLabelMap[$predictedTopicId] ?? sprintf('Topik %s', $predictedTopicId),
                'similarity' => round($predictedSimilarity, 4),
                'top_words' => array_slice((array) ($inference['top_words'] ?? []), 0, 15),
            ];
        }

        return [
            'status' => 'ok',
            'message' => null,
            'query' => $query,
            'predicted_topic' => $predictedTopic,
            'topic_distribution' => $distribution,
            'items' => array_slice($scored, 0, $this->searchResultLimit),
            'used_fallback' => false,
        ];
    }

    protected function buildKeywordFallbackResults(TopicModelRun $run, string $query): array
    {
        $queryTokens = $this->extractSearchTokens($query);

        $topicList = TopicModelTopic::query()
            ->where('topic_model_run_id', $run->id)
            ->get(['topic_id', 'custom_name']);
        $topicLabelMap = $this->buildTopicLabelMap($topicList);

        $rows = TopicModelTopicDocument::query()
            ->join('skripsi', 'skripsi.id', '=', 'topic_model_topic_documents.skripsi_id')
            ->where('topic_model_topic_documents.topic_model_run_id', $run->id)
            ->select([
                'topic_model_topic_documents.topic_id as topic_id',
                'topic_model_topic_documents.skripsi_id as skripsi_id',
                'skripsi.title as title',
                'skripsi.abstract as abstract',
                'skripsi.keywords as keywords',
                'skripsi.author as author',
                'skripsi.year as year',
                'skripsi.url as url',
            ])
            ->orderByDesc('skripsi.year')
            ->limit($this->searchCandidateLimit)
            ->get();

        $scored = [];
        foreach ($rows as $row) {
            $text = mb_strtolower(trim(
                ((string) ($row->title ?? '')) . ' '
                . ((string) ($row->abstract ?? '')) . ' '
                . ((string) ($row->keywords ?? ''))
            ));

            $hitCount = 0;
            foreach ($queryTokens as $token) {
                if (str_contains($text, $token)) {
                    $hitCount++;
                }
            }

            if ($hitCount === 0 && !empty($queryTokens)) {
                continue;
            }

            $keywordScore = count($queryTokens) > 0 ? ($hitCount / count($queryTokens)) : 0.0;
            $topicId = (int) ($row->topic_id ?? -1);

            $scored[] = [
                'topic_id' => $topicId,
                'topic_label' => $topicLabelMap[$topicId] ?? sprintf('Topik %s', $topicId),
                'skripsi_id' => (int) ($row->skripsi_id ?? 0),
                'title' => trim((string) ($row->title ?? 'Tanpa judul')),
                'author' => trim((string) ($row->author ?? '-')),
                'year' => is_numeric($row->year) ? (int) $row->year : null,
                'url' => filled($row->url) ? (string) $row->url : null,
                'score' => round($keywordScore, 4),
                'topic_similarity' => null,
                'keyword_match' => round($keywordScore, 4),
            ];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $this->searchResultLimit);
    }

    public function render()
    {
        $activeRun = $this->resolveActiveRun();

        $topicList = collect();
        $topicCards = [];

        $chartPayload = [
            'distribution' => ['labels' => [], 'counts' => []],
            'wordcloud_topics' => [],
            'dtm' => [
                'years' => [],
                'series' => [],
                'has_data' => false,
                'missing_reason' => 'Data trend belum tersedia.',
            ],
        ];

        if ($activeRun) {
            $topicList = TopicModelTopic::query()
                ->where('topic_model_run_id', $activeRun->id)
                ->orderByDesc('count')
                ->orderBy('topic_id')
                ->get();

            $topicCards = $this->buildTopicCards($activeRun, $topicList);

            $chartPayload = [
                'distribution' => $this->buildTopicDistributionPayload($topicList),
                'wordcloud_topics' => $this->buildWordCloudTopics($topicList),
                'dtm' => $this->buildDtmPayloadFromDbMapping($activeRun, $topicList),
            ];
        }

        return view('livewire.mahasiswa.topic-explorer', [
            'activeRun' => $activeRun,
            'topicList' => $topicList,
            'topicCards' => $topicCards,
            'chartPayload' => $chartPayload,
            'smartSearchResult' => $this->smartSearchResult,
        ]);
    }
}
