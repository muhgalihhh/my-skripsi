<?php

namespace Tests\Feature;

use App\Livewire\Jurusan\TopicCurationManager;
use App\Models\TopicModelRun;
use App\Models\TopicModelTopic;
use App\Models\TopicModelTopicDocument;
use App\Models\Skripsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TopicCurationGeminiTest extends TestCase
{
    use RefreshDatabase;

    public function test_jurusan_can_generate_ai_suggestion_for_selected_topic(): void
    {
        [$jurusan, $topic] = $this->createCompletedRunWithTopic();

        config()->set('services.fastapi.base_url', 'http://fastapi.test');

        Http::fake([
            'http://fastapi.test/api/v1/training/topic-curation/generate' => Http::response([
                'custom_name' => 'Topik Ketahanan Pangan',
                'representation_description' => 'Membahas ketahanan pangan daerah berdasarkan pola data akademik dan sosial.',
            ], 200),
        ]);

        $this->actingAs($jurusan);

        Livewire::test(TopicCurationManager::class)
            ->call('openEditModal', $topic->id)
            ->call('generateAiSuggestion')
            ->assertSet('editCustomName', 'Topik Ketahanan Pangan')
            ->assertSet('editRepresentationDescription', 'Membahas ketahanan pangan daerah berdasarkan pola data akademik dan sosial.');

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $titles = (array) ($request['representative_titles'] ?? []);

            return $request->url() === 'http://fastapi.test/api/v1/training/topic-curation/generate'
                && $request['keywords'] === ['ketahanan', 'pangan', 'daerah', 'mahasiswa']
                && in_array('Analisis Ketahanan Pangan Mahasiswa Banyumas', $titles, true)
                && in_array('Strategi Konsumsi Mahasiswa pada Krisis Pangan', $titles, true)
                && count($titles) >= 2;
        });

        $topic->refresh();
        $this->assertNull($topic->custom_name);
        $this->assertNull($topic->representation_description);
    }

    public function test_jurusan_can_still_edit_manually_after_ai_generation_before_saving(): void
    {
        [$jurusan, $topic] = $this->createCompletedRunWithTopic();

        config()->set('services.fastapi.base_url', 'http://fastapi.test');

        Http::fake([
            'http://fastapi.test/api/v1/training/topic-curation/generate' => Http::response([
                'custom_name' => 'Nama AI Awal',
                'representation_description' => 'Deskripsi AI awal.',
            ], 200),
        ]);

        $this->actingAs($jurusan);

        Livewire::test(TopicCurationManager::class)
            ->call('openEditModal', $topic->id)
            ->call('generateAiSuggestion')
            ->set('editCustomName', 'Nama Manual Final')
            ->set('editRepresentationDescription', 'Deskripsi manual final setelah review manager.')
            ->call('saveTopicCuration')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $topic->refresh();
        $this->assertSame('Nama Manual Final', $topic->custom_name);
        $this->assertSame('Deskripsi manual final setelah review manager.', $topic->representation_description);
    }

    /**
     * @return array{0: User, 1: TopicModelTopic}
     */
    private function createCompletedRunWithTopic(): array
    {
        $jurusan = User::factory()->create([
            'role' => 'jurusan',
        ]);

        $run = TopicModelRun::query()->create([
            'user_id' => $jurusan->id,
            'model_type' => 'bertopic',
            'status' => 'completed',
            'num_topics' => 1,
            'completed_at' => now(),
        ]);

        $topic = TopicModelTopic::query()->create([
            'topic_model_run_id' => $run->id,
            'topic_id' => 0,
            'count' => 12,
            'top_words' => ['ketahanan', 'pangan', 'daerah', 'mahasiswa'],
            'word_scores' => [0.2, 0.17, 0.14, 0.11],
        ]);

        $skripsi = Skripsi::query()->create([
            'title' => 'Analisis Ketahanan Pangan Mahasiswa Banyumas',
            'abstract' => 'Penelitian ini menganalisis pola ketahanan pangan mahasiswa berdasarkan faktor ekonomi dan kebiasaan konsumsi.',
            'keywords' => 'ketahanan pangan, konsumsi mahasiswa, ekonomi rumah tangga',
            'subjects' => 'Ketahanan Pangan',
            'divisions' => 'Sosial Ekonomi',
            'url' => 'https://example.test/skripsi/ketahanan-pangan-mahasiswa-banyumas',
        ]);

        TopicModelTopicDocument::query()->create([
            'topic_model_run_id' => $run->id,
            'topic_model_topic_id' => $topic->id,
            'topic_id' => 0,
            'skripsi_id' => $skripsi->id,
        ]);

        $skripsiKedua = Skripsi::query()->create([
            'title' => 'Strategi Konsumsi Mahasiswa pada Krisis Pangan',
            'abstract' => 'Studi ini membahas pola adaptasi konsumsi mahasiswa ketika terjadi gangguan ketersediaan pangan.',
            'keywords' => 'strategi konsumsi, krisis pangan, mahasiswa',
            'subjects' => 'Pangan',
            'divisions' => 'Ekonomi Pembangunan',
            'url' => 'https://example.test/skripsi/strategi-konsumsi-mahasiswa-krisis-pangan',
        ]);

        TopicModelTopicDocument::query()->create([
            'topic_model_run_id' => $run->id,
            'topic_model_topic_id' => $topic->id,
            'topic_id' => 0,
            'skripsi_id' => $skripsiKedua->id,
        ]);

        return [$jurusan, $topic];
    }
}
