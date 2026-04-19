<?php

namespace App\Livewire\Jurusan;

use App\Models\TopicModelRun;
use App\Models\TopicModelTopic;
use App\Models\TopicModelTopicDocument;
use App\Services\FastApiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.jurusan')]
#[Title('Visualisasi Topik')]
class VisualizationManager extends Component
{
    #[Url]
    public string $runFilter = '';

    protected int $wordCloudWordsPerTopic = 10;
    protected int $dtmSeriesLimit = 10;
    protected int $trendLimit = 8;
    protected int $mappingRowsLimit = 600;
    protected bool $preferFastApiDta = true;

    public function mount(): void
    {
        /** @var int|null $userId */
        $userId = Auth::id();
        if ($userId === null) {
            return;
        }

        $latestRunId = $this->resolveDefaultRunId($userId);
        if ($latestRunId !== null) {
            $this->runFilter = (string) $latestRunId;
        }
    }

    protected function resolveDefaultRunId(int $userId): ?int
    {
        $latestCompletedRunId = TopicModelRun::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->latest('id')
            ->value('id');

        if ($latestCompletedRunId !== null) {
            return (int) $latestCompletedRunId;
        }

        $latestRunWithDtmDataId = TopicModelTopicDocument::query()
            ->join('topic_model_runs', 'topic_model_runs.id', '=', 'topic_model_topic_documents.topic_model_run_id')
            ->join('skripsi', 'skripsi.id', '=', 'topic_model_topic_documents.skripsi_id')
            ->where('topic_model_runs.user_id', $userId)
            ->where('topic_model_runs.status', 'completed')
            ->whereNotNull('skripsi.year')
            ->orderByDesc('topic_model_topic_documents.topic_model_run_id')
            ->value('topic_model_topic_documents.topic_model_run_id');

        if ($latestRunWithDtmDataId !== null) {
            return (int) $latestRunWithDtmDataId;
        }

        return null;
    }

    protected function buildWordCloudTopics(Collection $topics): array
    {
        $clouds = [];
        $selectedTopics = $topics;

        foreach ($selectedTopics as $topic) {
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

            $scoreMap = $this->buildTopicScoreMap($topWords, is_array($topic->word_scores) ? $topic->word_scores : []);
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

        // Case 1: associative map {word => score}
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

        // Case 2: list score aligned with top_words index
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

    protected function buildDtmPayload(TopicModelRun $run, Collection $topics): array
    {
        if ($this->preferFastApiDta) {
            $fastApiPayload = $this->buildDtmPayloadFromFastApi($run, $topics);
            if ($fastApiPayload !== null) {
                return $fastApiPayload;
            }
        }

        return $this->buildDtmPayloadFromDbMapping($run, $topics);
    }

    protected function buildDtmPayloadFromFastApi(TopicModelRun $run, Collection $topics): ?array
    {
        if (strtolower((string) ($run->model_type ?? '')) !== 'bertopic') {
            return null;
        }

        $jobId = trim((string) ($run->fastapi_training_job_id ?? ''));
        if ($jobId === '') {
            return null;
        }

        $fastApi = app(FastApiService::class);
        $response = $fastApi->runDynamicTopicAnalysis($jobId);

        $status = (string) ($response['status'] ?? 'ok');
        if (in_array($status, ['error', 'unreachable', 'not_found'], true)) {
            Log::warning('FastAPI DTA unavailable, fallback to DB mapping', [
                'run_id' => $run->id,
                'job_id' => $jobId,
                'status' => $status,
                'message' => $response['message'] ?? null,
            ]);

            return null;
        }

        $topicLabelMap = [];
        foreach ($topics as $topic) {
            $label = filled($topic->custom_name)
                ? sprintf('T%s - %s', $topic->topic_id, $topic->custom_name)
                : sprintf('Topik %s', $topic->topic_id);
            $topicLabelMap[(int) $topic->topic_id] = $label;
        }

        $matrix = [];
        $rows = $response['topics_over_time_raw'] ?? [];

        if (is_array($rows) && !empty($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $topicId = isset($row['Topic']) ? (int) $row['Topic'] : null;
                $year = $this->extractYear($row['Timestamp'] ?? null);
                $freq = isset($row['Frequency']) && is_numeric($row['Frequency']) ? (float) $row['Frequency'] : null;

                if ($topicId === null || $topicId < 0 || $year === null || $freq === null) {
                    continue;
                }

                $matrix[$topicId][$year] = ($matrix[$topicId][$year] ?? 0.0) + $freq;
            }
        }

        if (empty($matrix)) {
            $trendBuckets = [
                $response['emerging_topics'] ?? [],
                $response['declining_topics'] ?? [],
                $response['stable_topics'] ?? [],
            ];

            foreach ($trendBuckets as $items) {
                if (!is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $topicId = isset($item['topic_id']) ? (int) $item['topic_id'] : null;
                    $freqPerYear = $item['frequency_per_year'] ?? null;
                    if ($topicId === null || $topicId < 0 || !is_array($freqPerYear)) {
                        continue;
                    }

                    foreach ($freqPerYear as $yearKey => $freqValue) {
                        $year = $this->extractYear($yearKey);
                        if ($year === null || !is_numeric($freqValue)) {
                            continue;
                        }

                        $matrix[$topicId][$year] = ($matrix[$topicId][$year] ?? 0.0) + (float) $freqValue;
                    }
                }
            }
        }

        if (empty($matrix)) {
            return [
                'years' => [],
                'series' => [],
                'total_docs' => 0,
                'year_min' => null,
                'year_max' => null,
                'has_data' => false,
                'missing_reason' => 'FastAPI DTA tidak mengembalikan data time-series untuk model ini.',
            ];
        }

        $years = collect($matrix)
            ->flatMap(fn(array $yearMap) => array_keys($yearMap))
            ->map(fn($year) => (int) $year)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $topicTotals = collect($matrix)
            ->map(fn(array $yearMap) => array_sum($yearMap))
            ->sortDesc();

        $selectedTopicIds = $topicTotals->keys()->take($this->dtmSeriesLimit)->map(fn($id) => (int) $id)->values()->all();

        $yearTotals = [];
        foreach ($years as $year) {
            $sum = 0.0;
            foreach ($selectedTopicIds as $topicId) {
                $sum += (float) ($matrix[$topicId][$year] ?? 0.0);
            }
            $yearTotals[$year] = $sum;
        }

        $series = [];
        foreach ($selectedTopicIds as $topicId) {
            $points = [];
            $rawCounts = [];

            foreach ($years as $year) {
                $count = (float) ($matrix[$topicId][$year] ?? 0.0);
                $total = (float) ($yearTotals[$year] ?? 0.0);
                $sharePct = $total > 0 ? ($count / $total) * 100 : 0;

                $points[] = round($sharePct, 4);
                $rawCounts[] = round($count, 4);
            }

            $series[] = [
                'topic_id' => $topicId,
                'label' => $topicLabelMap[$topicId] ?? sprintf('Topik %s', $topicId),
                'data' => $points,
                'counts' => $rawCounts,
            ];
        }

        return [
            'years' => $years,
            'series' => $series,
            'total_docs' => (int) round($topicTotals->sum()),
            'year_min' => count($years) > 0 ? min($years) : null,
            'year_max' => count($years) > 0 ? max($years) : null,
            'has_data' => true,
            'missing_reason' => null,
            'source' => 'fastapi_dta',
        ];
    }

    protected function buildDtmPayloadFromDbMapping(TopicModelRun $run, Collection $topics): array
    {
        $mappingCount = TopicModelTopicDocument::query()
            ->where('topic_model_run_id', $run->id)
            ->count();

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
                'total_docs' => 0,
                'year_min' => null,
                'year_max' => null,
                'has_data' => false,
                'missing_reason' => $mappingCount === 0
                    ? 'Run ini belum memiliki mapping dokumen-topik.'
                    : 'Run ini belum memiliki data tahun skripsi yang valid untuk divisualisasikan.',
            ];
        }

        $topicLabelMap = [];
        foreach ($topics as $topic) {
            $label = filled($topic->custom_name)
                ? sprintf('T%s - %s', $topic->topic_id, $topic->custom_name)
                : sprintf('Topik %s', $topic->topic_id);
            $topicLabelMap[(int) $topic->topic_id] = $label;
        }

        $years = $raw->pluck('year')->map(fn($year) => (int) $year)->unique()->sort()->values()->all();

        $topicTotals = $raw
            ->groupBy('topic_id')
            ->map(fn(Collection $rows) => (int) $rows->sum('doc_count'))
            ->sortDesc();

        $selectedTopicIds = $topicTotals->keys()->take($this->dtmSeriesLimit)->map(fn($id) => (int) $id)->values()->all();

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
            $rawCounts = [];

            foreach ($years as $year) {
                $count = (int) ($matrix[$topicId][$year] ?? 0);
                $total = (int) ($yearTotals[$year] ?? 0);
                $sharePct = $total > 0 ? ($count / $total) * 100 : 0;

                $points[] = round($sharePct, 4);
                $rawCounts[] = $count;
            }

            $series[] = [
                'topic_id' => $topicId,
                'label' => $topicLabelMap[$topicId] ?? sprintf('Topik %s', $topicId),
                'data' => $points,
                'counts' => $rawCounts,
            ];
        }

        return [
            'years' => $years,
            'series' => $series,
            'total_docs' => (int) $raw->sum('doc_count'),
            'year_min' => count($years) > 0 ? min($years) : null,
            'year_max' => count($years) > 0 ? max($years) : null,
            'has_data' => true,
            'missing_reason' => null,
            'source' => 'db_mapping',
        ];
    }

    protected function extractYear(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return ($value >= 1900 && $value <= 2100) ? $value : null;
        }

        if (is_string($value)) {
            if (preg_match('/\b(19|20)\d{2}\b/', $value, $matches) === 1) {
                return (int) $matches[0];
            }
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return (int) $value->format('Y');
        }

        return null;
    }

    protected function buildTrendPayload(array $dtmPayload): array
    {
        $series = $dtmPayload['series'] ?? [];
        if (empty($series)) {
            return [];
        }

        $rows = [];
        $dtaTrendThreshold = 0.03;
        $minPoints = 3;

        foreach ($series as $topicSeries) {
            $data = $topicSeries['data'] ?? [];
            if (!is_array($data) || count($data) < 2) {
                continue;
            }

            $yVals = array_values($data);
            $nPoints = count($yVals);

            $start = (float) $yVals[0];
            $end = (float) $yVals[$nPoints - 1];

            $slope = 0.0;
            $relativeSlope = 0.0;
            $trendLabel = 'stable';

            if ($nPoints < $minPoints) {
                $trendLabel = 'insufficient_data';
            } else {
                $xVals = range(0, $nPoints - 1);
                $sumX = array_sum($xVals);
                $sumY = array_sum($yVals);
                $sumXY = 0.0;
                $sumX2 = 0.0;

                for ($i = 0; $i < $nPoints; $i++) {
                    $sumXY += $xVals[$i] * $yVals[$i];
                    $sumX2 += $xVals[$i] * $xVals[$i];
                }

                $denominator = ($nPoints * $sumX2) - ($sumX * $sumX);
                if ($denominator != 0) {
                    $slope = (($nPoints * $sumXY) - ($sumX * $sumY)) / $denominator;
                }

                $baseline = max(array_sum($yVals) / $nPoints, 1.0);
                $relativeSlope = $slope / $baseline;

                if ($relativeSlope >= $dtaTrendThreshold && $end >= $start) {
                    $trendLabel = 'emerging';
                } elseif ($relativeSlope <= -$dtaTrendThreshold && $end <= $start) {
                    $trendLabel = 'declining';
                } else {
                    $trendLabel = 'stable';
                }
            }

            $rows[] = [
                'topic_id' => (int) ($topicSeries['topic_id'] ?? 0),
                'label' => (string) ($topicSeries['label'] ?? 'Topik'),
                'start' => round($start, 4),
                'end' => round($end, 4),
                'relative_slope' => round($relativeSlope, 4),
                'trend_label' => $trendLabel,
            ];
        }

        if (empty($rows)) {
            return [];
        }

        // Sort ascending by relative_slope (minus to plus)
        $rowsCollection = collect($rows)->sortBy('relative_slope')->values()->all();

        return $rowsCollection;
    }

    protected function buildSkripsiTopicMappingPayload(TopicModelRun $run, Collection $topics): array
    {
        $topicLabelMap = [];
        foreach ($topics as $topic) {
            $topicLabelMap[(int) $topic->topic_id] = filled($topic->custom_name)
                ? sprintf('T%s - %s', $topic->topic_id, $topic->custom_name)
                : sprintf('Topik %s', $topic->topic_id);
        }

        $baseQuery = TopicModelTopicDocument::query()
            ->join('skripsi', 'skripsi.id', '=', 'topic_model_topic_documents.skripsi_id')
            ->where('topic_model_topic_documents.topic_model_run_id', $run->id);

        $totalRows = (clone $baseQuery)->count();

        $rawRows = (clone $baseQuery)
            ->select([
                'topic_model_topic_documents.topic_id as topic_id',
                'topic_model_topic_documents.topic_model_topic_id as topic_model_topic_id',
                'topic_model_topic_documents.skripsi_id as skripsi_id',
                'skripsi.title as title',
                'skripsi.author as author',
                'skripsi.year as year',
            ])
            ->orderBy('topic_model_topic_documents.topic_id')
            ->orderByDesc('skripsi.year')
            ->orderBy('topic_model_topic_documents.skripsi_id')
            ->limit($this->mappingRowsLimit)
            ->get();

        if ($rawRows->isEmpty()) {
            return [
                'rows' => [],
                'topic_summary' => [],
                'total_rows' => 0,
                'displayed_rows' => 0,
                'is_truncated' => false,
                'missing_reason' => 'Belum ada mapping skripsi ke topik pada run ini.',
            ];
        }

        $rows = [];
        foreach ($rawRows as $row) {
            $topicId = (int) ($row->topic_id ?? 0);
            $topicLabel = $topicLabelMap[$topicId] ?? sprintf('Topik %s', $topicId);

            $rows[] = [
                'topic_id' => $topicId,
                'topic_label' => $topicLabel,
                'skripsi_id' => (int) ($row->skripsi_id ?? 0),
                'title' => trim((string) ($row->title ?? 'Tanpa judul')),
                'author' => trim((string) ($row->author ?? '-')),
                'year' => is_numeric($row->year) ? (int) $row->year : null,
            ];
        }

        $summaryRows = TopicModelTopicDocument::query()
            ->selectRaw('topic_id, COUNT(*) as doc_count')
            ->where('topic_model_run_id', $run->id)
            ->groupBy('topic_id')
            ->orderByDesc('doc_count')
            ->get();

        $topicSummary = [];
        foreach ($summaryRows as $summary) {
            $summaryTopicId = (int) ($summary->topic_id ?? 0);
            $topicSummary[] = [
                'topic_id' => $summaryTopicId,
                'topic_label' => $topicLabelMap[$summaryTopicId] ?? sprintf('Topik %s', $summaryTopicId),
                'doc_count' => (int) ($summary->doc_count ?? 0),
            ];
        }

        return [
            'rows' => $rows,
            'topic_summary' => $topicSummary,
            'total_rows' => (int) $totalRows,
            'displayed_rows' => count($rows),
            'is_truncated' => $totalRows > count($rows),
            'missing_reason' => null,
        ];
    }

    public function render()
    {
        /** @var int|null $userId */
        $userId = Auth::id();

        if ($userId === null) {
            return view('livewire.jurusan.visualization-manager', [
                'completedRuns' => collect(),
                'activeRun' => null,
                'topicList' => collect(),
                'skripsiMapping' => [
                    'rows' => [],
                    'topic_summary' => [],
                    'total_rows' => 0,
                    'displayed_rows' => 0,
                    'is_truncated' => false,
                    'missing_reason' => null,
                ],
                'chartPayload' => [
                    'wordcloud_topics' => [],
                    'dtm' => ['years' => [], 'series' => [], 'has_data' => false, 'missing_reason' => null],
                    'trend' => [],
                ],
            ]);
        }

        $completedRuns = TopicModelRun::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'model_type', 'num_topics', 'coherence_cv', 'topic_diversity', 'completed_at']);

        $activeRun = null;
        if ($this->runFilter !== '') {
            $activeRun = TopicModelRun::query()
                ->where('id', (int) $this->runFilter)
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->first();
        }

        if (!$activeRun) {
            $activeRun = $completedRuns->first();
        }

        if ($activeRun && $this->runFilter === '') {
            $this->runFilter = (string) $activeRun->id;
        }

        $topicList = collect();
        $skripsiMapping = [
            'rows' => [],
            'topic_summary' => [],
            'total_rows' => 0,
            'displayed_rows' => 0,
            'is_truncated' => false,
            'missing_reason' => null,
        ];
        $chartPayload = [
            'wordcloud_topics' => [],
            'dtm' => ['years' => [], 'series' => [], 'has_data' => false, 'missing_reason' => null],
            'trend' => [],
        ];

        if ($activeRun) {
            $topicList = TopicModelTopic::query()
                ->where('topic_model_run_id', $activeRun->id)
                ->orderByDesc('count')
                ->orderBy('topic_id')
                ->get();

            $dtmPayload = $this->buildDtmPayload($activeRun, $topicList);
            $skripsiMapping = $this->buildSkripsiTopicMappingPayload($activeRun, $topicList);
            $chartPayload = [
                'wordcloud_topics' => $this->buildWordCloudTopics($topicList),
                'dtm' => $dtmPayload,
                'trend' => $this->buildTrendPayload($dtmPayload),
            ];
        }

        return view('livewire.jurusan.visualization-manager', [
            'completedRuns' => $completedRuns,
            'activeRun' => $activeRun,
            'topicList' => $topicList,
            'skripsiMapping' => $skripsiMapping,
            'chartPayload' => $chartPayload,
        ]);
    }
}