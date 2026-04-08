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
    $this->compactRunParams();
    $this->compactSettingParams();
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    // Data compaction migration; intentionally no-op.
  }

  protected function compactRunParams(): void
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
            $compactedBertopic = $this->compactBertopicParams($bertopic);
            if ($this->encodeJson($bertopic) !== $this->encodeJson($compactedBertopic)) {
              $updates['bertopic_params'] = $this->encodeJson($compactedBertopic);
            }
          }

          $lda = $this->decodeJsonObject($row->lda_params);
          if ($lda !== null) {
            $compactedLda = $this->compactLdaParams($lda);
            if ($this->encodeJson($lda) !== $this->encodeJson($compactedLda)) {
              $updates['lda_params'] = $this->encodeJson($compactedLda);
            }
          }

          if (!empty($updates)) {
            DB::table('topic_model_runs')->where('id', $row->id)->update($updates);
          }
        }
      }, 'id');
  }

  protected function compactSettingParams(): void
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
            $compactedBertopic = $this->compactBertopicParams($bertopic);
            if ($this->encodeJson($bertopic) !== $this->encodeJson($compactedBertopic)) {
              $updates['bertopic_params'] = $this->encodeJson($compactedBertopic);
            }
          }

          $lda = $this->decodeJsonObject($row->lda_params);
          if ($lda !== null) {
            $compactedLda = $this->compactLdaParams($lda);
            if ($this->encodeJson($lda) !== $this->encodeJson($compactedLda)) {
              $updates['lda_params'] = $this->encodeJson($compactedLda);
            }
          }

          if (!empty($updates)) {
            DB::table('topic_model_settings')->where('id', $row->id)->update($updates);
          }
        }
      }, 'id');
  }

  protected function getCompactBertopicDefaults(): array
  {
    return [
      'min_topic_size' => 12,
      'nr_topics' => 'auto',
      'top_n_words' => 10,
      'n_gram_range' => [1, 2],
      'umap_params' => [
        'n_neighbors' => 40,
        'n_components' => 5,
        'metric' => 'cosine',
      ],
      'hdbscan_params' => [
        'min_cluster_size' => 16,
        'min_samples' => 1,
        'metric' => 'euclidean',
      ],
    ];
  }

  protected function getCompactLdaDefaults(): array
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

  protected function compactBertopicParams(array $params): array
  {
    $defaults = $this->getCompactBertopicDefaults();
    $umap = is_array($params['umap_params'] ?? null) ? $params['umap_params'] : [];
    $hdbscan = is_array($params['hdbscan_params'] ?? null) ? $params['hdbscan_params'] : [];
    $nGramRange = is_array($params['n_gram_range'] ?? null) ? $params['n_gram_range'] : $defaults['n_gram_range'];

    $nGramMin = $this->toInt($nGramRange[0] ?? null, 1, null, (int) $defaults['n_gram_range'][0]);
    $nGramMax = $this->toInt($nGramRange[1] ?? null, $nGramMin, null, max($nGramMin, (int) $defaults['n_gram_range'][1]));

    return [
      'min_topic_size' => $this->toInt($params['min_topic_size'] ?? null, 2, null, $defaults['min_topic_size']),
      'nr_topics' => $this->normalizeNrTopics($params['nr_topics'] ?? null, $defaults['nr_topics']),
      'top_n_words' => $this->toInt($params['top_n_words'] ?? null, 1, null, $defaults['top_n_words']),
      'n_gram_range' => [$nGramMin, $nGramMax],
      'umap_params' => [
        'n_neighbors' => $this->toInt(
          $umap['n_neighbors'] ?? null,
          2,
          null,
          (int) $defaults['umap_params']['n_neighbors']
        ),
        'n_components' => $this->toInt(
          $umap['n_components'] ?? null,
          2,
          null,
          (int) $defaults['umap_params']['n_components']
        ),
        'metric' => $this->toNonEmptyString(
          $umap['metric'] ?? null,
          (string) $defaults['umap_params']['metric']
        ),
      ],
      'hdbscan_params' => [
        'min_cluster_size' => $this->toInt(
          $hdbscan['min_cluster_size'] ?? null,
          2,
          null,
          (int) $defaults['hdbscan_params']['min_cluster_size']
        ),
        'min_samples' => $this->toOptionalInt(
          $hdbscan['min_samples'] ?? null,
          1,
          (int) $defaults['hdbscan_params']['min_samples']
        ),
        'metric' => $this->toNonEmptyString(
          $hdbscan['metric'] ?? null,
          (string) $defaults['hdbscan_params']['metric']
        ),
      ],
    ];
  }

  protected function compactLdaParams(array $params): array
  {
    $defaults = $this->getCompactLdaDefaults();

    return [
      'num_topics' => $this->toInt($params['num_topics'] ?? null, 2, null, $defaults['num_topics']),
      'passes' => $this->toInt($params['passes'] ?? null, 1, null, $defaults['passes']),
      'iterations' => $this->toInt($params['iterations'] ?? null, 1, null, $defaults['iterations']),
      'chunksize' => $this->toInt($params['chunksize'] ?? null, 1, null, $defaults['chunksize']),
      'random_state' => $this->toInt($params['random_state'] ?? null, 0, null, $defaults['random_state']),
      'alpha' => $this->normalizeAlphaEtaValue($params['alpha'] ?? null, false, $defaults['alpha']),
      'eta' => $this->normalizeAlphaEtaValue($params['eta'] ?? null, true, $defaults['eta']),
      'no_below' => $this->toInt($params['no_below'] ?? null, 1, null, $defaults['no_below']),
      'no_above' => $this->toFloat($params['no_above'] ?? null, 0.0001, 1.0, (float) $defaults['no_above']),
    ];
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

  protected function toNonEmptyString(mixed $value, string $fallback): string
  {
    if ($value === null) {
      return $fallback;
    }

    $text = trim((string) $value);
    if ($text === '') {
      return $fallback;
    }

    return $text;
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