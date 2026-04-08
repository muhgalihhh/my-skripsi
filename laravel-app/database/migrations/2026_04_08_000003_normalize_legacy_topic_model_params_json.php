<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    $this->normalizeRunParams();
    $this->normalizeSettingParams();
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    // Data normalization migration; intentionally no-op.
  }

  protected function normalizeRunParams(): void
  {
    if (!Schema::hasTable('topic_model_runs')) {
      return;
    }

    DB::table('topic_model_runs')
      ->select(['id', 'bertopic_params', 'lda_params'])
      ->orderBy('id')
      ->chunkById(200, function ($rows): void {
        foreach ($rows as $row) {
          $updates = [];

          $bertopic = $this->decodeJsonObject($row->bertopic_params);
          if ($bertopic !== null) {
            $normalizedBertopic = $this->normalizeBertopicParams($bertopic);
            if ($this->encodeJson($bertopic) !== $this->encodeJson($normalizedBertopic)) {
              $updates['bertopic_params'] = $this->encodeJson($normalizedBertopic);
            }
          }

          $lda = $this->decodeJsonObject($row->lda_params);
          if ($lda !== null) {
            $normalizedLda = $this->normalizeLdaParams($lda);
            if ($this->encodeJson($lda) !== $this->encodeJson($normalizedLda)) {
              $updates['lda_params'] = $this->encodeJson($normalizedLda);
            }
          }

          if (!empty($updates)) {
            DB::table('topic_model_runs')->where('id', $row->id)->update($updates);
          }
        }
      }, 'id');
  }

  protected function normalizeSettingParams(): void
  {
    if (!Schema::hasTable('topic_model_settings')) {
      return;
    }

    DB::table('topic_model_settings')
      ->select(['id', 'bertopic_params', 'lda_params'])
      ->orderBy('id')
      ->chunkById(200, function ($rows): void {
        foreach ($rows as $row) {
          $updates = [];

          $bertopic = $this->decodeJsonObject($row->bertopic_params);
          if ($bertopic !== null) {
            $normalizedBertopic = $this->normalizeBertopicParams($bertopic);
            if ($this->encodeJson($bertopic) !== $this->encodeJson($normalizedBertopic)) {
              $updates['bertopic_params'] = $this->encodeJson($normalizedBertopic);
            }
          }

          $lda = $this->decodeJsonObject($row->lda_params);
          if ($lda !== null) {
            $normalizedLda = $this->normalizeLdaParams($lda);
            if ($this->encodeJson($lda) !== $this->encodeJson($normalizedLda)) {
              $updates['lda_params'] = $this->encodeJson($normalizedLda);
            }
          } elseif ($bertopic !== null) {
            // Backfill LDA defaults for existing settings rows that predate lda_params.
            $updates['lda_params'] = $this->encodeJson($this->getDefaultLdaParams());
          }

          if (!empty($updates)) {
            DB::table('topic_model_settings')->where('id', $row->id)->update($updates);
          }
        }
      }, 'id');
  }

  protected function getDefaultBertopicParams(): array
  {
    return [
      'embedding_model' => 'denaya/indoSBERT-large',
      'min_topic_size' => 12,
      'nr_topics' => 'auto',
      'top_n_words' => 10,
      'n_gram_range' => [1, 2],
      'vectorizer_min_df' => 2,
      'vectorizer_max_df' => 0.95,
      'vectorizer_token_pattern' => '(?u)\\b\\w{3,}\\b',
      'vectorizer_fallback_min_df' => 1,
      'vectorizer_fallback_max_df' => 1.0,
      'coherence_type' => 'c_v',
      'coherence_tokenization' => 'vectorizer',
      'coherence_dict_no_below' => 3,
      'coherence_dict_no_above' => 0.95,
      'reduce_outliers' => true,
      'reduce_outliers_threshold_ctfidf' => 0.1,
      'reduce_outliers_use_distributions' => true,
      'reduce_outliers_threshold_distributions' => 0.05,
      'use_mmr_representation' => true,
      'mmr_diversity' => 0.3,
      'embedding_batch_size' => 16,
      'seed' => 42,
      'umap_params' => [
        'n_neighbors' => 40,
        'n_components' => 5,
        'min_dist' => 0.0,
        'metric' => 'cosine',
        'random_state' => 42,
      ],
      'hdbscan_params' => [
        'min_cluster_size' => 16,
        'min_samples' => 1,
        'metric' => 'euclidean',
        'cluster_selection_method' => 'eom',
      ],
    ];
  }

  protected function getDefaultLdaParams(): array
  {
    return [
      'num_topics' => 12,
      'passes' => 20,
      'iterations' => 300,
      'chunksize' => 100,
      'random_state' => 42,
      'alpha' => 'asymmetric',
      'eta' => null,
      'no_below' => 2,
      'no_above' => 0.95,
    ];
  }

  protected function normalizeBertopicParams(array $params): array
  {
    $defaults = $this->getDefaultBertopicParams();
    $params = array_replace_recursive($defaults, $params);

    // Keep advanced/internal params stable and tune only official BERTopic params.
    $params['embedding_model'] = $defaults['embedding_model'];
    $params['vectorizer_min_df'] = $defaults['vectorizer_min_df'];
    $params['vectorizer_max_df'] = $defaults['vectorizer_max_df'];
    $params['vectorizer_token_pattern'] = $defaults['vectorizer_token_pattern'];
    $params['vectorizer_fallback_min_df'] = $defaults['vectorizer_fallback_min_df'];
    $params['vectorizer_fallback_max_df'] = $defaults['vectorizer_fallback_max_df'];
    $params['coherence_type'] = $defaults['coherence_type'];
    $params['coherence_tokenization'] = $defaults['coherence_tokenization'];
    $params['coherence_dict_no_below'] = $defaults['coherence_dict_no_below'];
    $params['coherence_dict_no_above'] = $defaults['coherence_dict_no_above'];
    $params['reduce_outliers'] = $defaults['reduce_outliers'];
    $params['reduce_outliers_threshold_ctfidf'] = $defaults['reduce_outliers_threshold_ctfidf'];
    $params['reduce_outliers_use_distributions'] = $defaults['reduce_outliers_use_distributions'];
    $params['reduce_outliers_threshold_distributions'] = $defaults['reduce_outliers_threshold_distributions'];
    $params['use_mmr_representation'] = $defaults['use_mmr_representation'];
    $params['mmr_diversity'] = $defaults['mmr_diversity'];
    $params['embedding_batch_size'] = $defaults['embedding_batch_size'];
    $params['seed'] = $defaults['seed'];

    $params['min_topic_size'] = $this->toInt($params['min_topic_size'] ?? null, 2, null, $defaults['min_topic_size']);
    $params['nr_topics'] = $this->normalizeNrTopics($params['nr_topics'] ?? null, $defaults['nr_topics']);
    $params['top_n_words'] = $this->toInt($params['top_n_words'] ?? null, 1, null, $defaults['top_n_words']);

    $nGramRange = is_array($params['n_gram_range'] ?? null) ? $params['n_gram_range'] : $defaults['n_gram_range'];
    $nGramMin = $this->toInt($nGramRange[0] ?? null, 1, null, $defaults['n_gram_range'][0]);
    $nGramMax = $this->toInt($nGramRange[1] ?? null, $nGramMin, null, max($nGramMin, (int) $defaults['n_gram_range'][1]));
    $params['n_gram_range'] = [$nGramMin, $nGramMax];

    $umapDefaults = $defaults['umap_params'];
    $umap = is_array($params['umap_params'] ?? null) ? $params['umap_params'] : [];
    $umapMetric = trim((string) ($umap['metric'] ?? $umapDefaults['metric']));
    if ($umapMetric === '') {
      $umapMetric = (string) $umapDefaults['metric'];
    }
    $params['umap_params'] = [
      'n_neighbors' => $this->toInt($umap['n_neighbors'] ?? null, 2, null, $umapDefaults['n_neighbors']),
      'n_components' => $this->toInt($umap['n_components'] ?? null, 2, null, $umapDefaults['n_components']),
      'min_dist' => (float) $umapDefaults['min_dist'],
      'metric' => $umapMetric,
      'random_state' => (int) $umapDefaults['random_state'],
    ];

    $hdbscanDefaults = $defaults['hdbscan_params'];
    $hdbscan = is_array($params['hdbscan_params'] ?? null) ? $params['hdbscan_params'] : [];
    $hdbscanMetric = trim((string) ($hdbscan['metric'] ?? $hdbscanDefaults['metric']));
    if ($hdbscanMetric === '') {
      $hdbscanMetric = (string) $hdbscanDefaults['metric'];
    }
    $params['hdbscan_params'] = [
      'min_cluster_size' => $this->toInt(
        $hdbscan['min_cluster_size'] ?? null,
        2,
        null,
        $hdbscanDefaults['min_cluster_size']
      ),
      'min_samples' => $this->toOptionalInt($hdbscan['min_samples'] ?? null, 1, (int) $hdbscanDefaults['min_samples']),
      'metric' => $hdbscanMetric,
      'cluster_selection_method' => (string) $hdbscanDefaults['cluster_selection_method'],
    ];

    return $params;
  }

  protected function normalizeLdaParams(array $params): array
  {
    $defaults = $this->getDefaultLdaParams();
    $params = array_replace($defaults, $params);

    $params['num_topics'] = $this->toInt($params['num_topics'] ?? null, 2, null, $defaults['num_topics']);
    $params['passes'] = $this->toInt($params['passes'] ?? null, 1, null, $defaults['passes']);
    $params['iterations'] = $this->toInt($params['iterations'] ?? null, 1, null, $defaults['iterations']);
    $params['chunksize'] = $this->toInt($params['chunksize'] ?? null, 1, null, $defaults['chunksize']);
    $params['random_state'] = $this->toInt($params['random_state'] ?? null, 0, null, $defaults['random_state']);
    $params['alpha'] = $this->normalizeAlphaEtaValue($params['alpha'] ?? null, false, $defaults['alpha']);
    $params['eta'] = $this->normalizeAlphaEtaValue($params['eta'] ?? null, true, $defaults['eta']);
    $params['no_below'] = $this->toInt($params['no_below'] ?? null, 1, null, $defaults['no_below']);
    $params['no_above'] = $this->toFloat($params['no_above'] ?? null, 0.0001, 1.0, (float) $defaults['no_above']);

    return $params;
  }

  protected function decodeJsonObject(mixed $value): ?array
  {
    if ($value === null) {
      return null;
    }

    if (is_array($value)) {
      return $value;
    }

    if (is_string($value)) {
      $decoded = json_decode($value, true);
      return is_array($decoded) ? $decoded : null;
    }

    return null;
  }

  protected function encodeJson(array $payload): string
  {
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  protected function normalizeNrTopics(mixed $value, string|int|null $fallback = null): string|int|null
  {
    if ($value === null) {
      return $fallback;
    }

    if (is_int($value)) {
      return max(2, $value);
    }

    $text = trim((string) $value);
    $lower = strtolower($text);

    if ($text === '' || in_array($lower, ['null', 'none'], true)) {
      return null;
    }

    if ($lower === 'auto') {
      return 'auto';
    }

    if (is_numeric($text)) {
      return max(2, (int) round((float) $text));
    }

    return $fallback;
  }

  protected function normalizeAlphaEtaValue(mixed $value, bool $allowNull, string|float|null $fallback): string|float|null
  {
    if ($value === null) {
      return $allowNull ? null : $fallback;
    }

    if (is_int($value) || is_float($value)) {
      return (float) $value;
    }

    $text = trim((string) $value);
    $lower = strtolower($text);

    if ($text === '' || in_array($lower, ['null', 'none'], true)) {
      return $allowNull ? null : $fallback;
    }

    if (in_array($lower, ['auto', 'symmetric', 'asymmetric'], true)) {
      return $lower;
    }

    if (is_numeric($text)) {
      return (float) $text;
    }

    return $fallback;
  }

  protected function toInt(mixed $value, int $min, ?int $max = null, ?int $fallback = null): int
  {
    $default = $fallback ?? $min;
    $num = is_numeric((string) $value) ? (int) round((float) $value) : $default;
    $num = max($min, $num);

    if ($max !== null) {
      $num = min($max, $num);
    }

    return $num;
  }

  protected function toOptionalInt(mixed $value, int $min, ?int $fallback = null): ?int
  {
    if ($value === null) {
      return $fallback;
    }

    $text = trim((string) $value);
    if ($text === '') {
      return $fallback;
    }

    if (!is_numeric($text)) {
      return $fallback;
    }

    return max($min, (int) round((float) $text));
  }

  protected function toFloat(mixed $value, float $min, ?float $max = null, ?float $fallback = null): float
  {
    $default = $fallback ?? $min;
    $num = is_numeric((string) $value) ? (float) $value : $default;
    $num = max($min, $num);

    if ($max !== null) {
      $num = min($max, $num);
    }

    return $num;
  }
};