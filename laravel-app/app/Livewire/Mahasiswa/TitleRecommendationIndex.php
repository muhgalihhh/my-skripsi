<?php

namespace App\Livewire\Mahasiswa;

use App\Models\TopicModelRun;
use App\Models\TopicModelTopic;
use App\Models\TopicModelTopicDocument;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.mahasiswa')]
#[Title('Rekomendasi Judul Skripsi')]
class TitleRecommendationIndex extends Component
{
    public string $search = '';

    protected int $topicLimit = 60;

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

    protected function buildTopicCards(TopicModelRun $run, Collection $topics): array
    {
        return $topics->map(function (TopicModelTopic $topic) use ($run): array {
            $topicId = (int) $topic->topic_id;

            return [
                'topic_row_id' => (int) $topic->id,
                'topic_id' => $topicId,
                'run_id' => (int) $run->id,
                'topic_label' => filled($topic->custom_name)
                    ? sprintf('T%s - %s', $topicId, $topic->custom_name)
                    : sprintf('Topik %s', $topicId),
                'doc_count' => (int) ($topic->count ?? 0),
                'top_words' => array_slice((array) ($topic->top_words ?? []), 0, 10),
            ];
        })->values()->all();
    }

    public function render()
    {
        $activeRun = $this->resolveActiveRun();
        $topicCards = [];

        if ($activeRun) {
            $query = TopicModelTopic::query()
                ->where('topic_model_run_id', $activeRun->id)
                ->orderByDesc('count')
                ->orderBy('topic_id');

            $search = trim($this->search);
            if ($search !== '') {
                $query->where(function ($builder) use ($search) {
                    $builder->where('custom_name', 'like', "%{$search}%");

                    if (is_numeric($search)) {
                        $builder->orWhere('topic_id', (int) $search);
                    }
                });
            }

            $topics = $query
                ->limit($this->topicLimit)
                ->get(['id', 'topic_id', 'count', 'top_words', 'custom_name']);

            $topicCards = $this->buildTopicCards($activeRun, $topics);
        }

        return view('livewire.mahasiswa.title-recommendation-index', [
            'activeRun' => $activeRun,
            'topicCards' => $topicCards,
        ]);
    }
}
