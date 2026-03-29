<?php

namespace App\Services;

class LocalTextPreprocessor
{
  /** @var array<string, bool> */
  protected array $stopwords;

  public function __construct(
    protected bool $removeStopwords = true,
    protected int $minWordLength = 3,
    protected string $language = 'indonesian',
  ) {
    $this->stopwords = $this->loadStopwords($this->language);
  }

  public function cleanText(string $text): string
  {
    $text = mb_strtolower($text);

    // Normalize whitespace
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    // Keep letters/numbers/spaces, strip punctuation/symbols
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;

    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

    return $text;
  }

  /** @return list<string> */
  public function tokenize(string $cleanText): array
  {
    if ($cleanText === '') {
      return [];
    }

    $parts = preg_split('/\s+/u', trim($cleanText)) ?: [];
    $parts = array_values(array_filter($parts, fn ($t) => $t !== ''));

    return $parts;
  }

  /** @param list<string> $tokens @return list<string> */
  public function filterByLength(array $tokens): array
  {
    $min = max(1, (int) $this->minWordLength);

    $filtered = array_values(array_filter($tokens, fn ($t) => mb_strlen($t) >= $min));

    return $filtered;
  }

  /** @param list<string> $tokens @return list<string> */
  public function removeStopwordsFromTokens(array $tokens): array
  {
    if (!$this->removeStopwords) {
      return $tokens;
    }

    $filtered = [];
    foreach ($tokens as $t) {
      if (!isset($this->stopwords[$t])) {
        $filtered[] = $t;
      }
    }

    return $filtered;
  }

  public function preprocessCleaned(string $rawText): string
  {
    $clean = $this->cleanText($rawText);
    $tokens = $this->tokenize($clean);
    $tokens = $this->filterByLength($tokens);
    $tokens = $this->removeStopwordsFromTokens($tokens);

    return implode(' ', $tokens);
  }

  /** @return array<string, bool> */
  protected function loadStopwords(string $language): array
  {
    // Lightweight embedded stopwords list (can be replaced later with a file or package).
    if ($language !== 'indonesian') {
      return [];
    }

    $words = [
      'yang', 'dan', 'di', 'ke', 'dari', 'pada', 'untuk', 'dengan', 'atau', 'sebagai',
      'dalam', 'ini', 'itu', 'oleh', 'karena', 'agar', 'juga', 'tidak', 'bukan', 'adalah',
      'akan', 'dapat', 'bisa', 'lebih', 'kurang', 'sudah', 'belum', 'saat', 'ketika',
      'sehingga', 'maka', 'serta', 'antara', 'tersebut', 'hingga', 'terhadap', 'paling',
      'para', 'oleh', 'pada', 'dengan', 'dalam', 'dari', 'sebuah', 'suatu', 'setiap',
      'dengan', 'tanpa', 'dengan', 'bahwa', 'yaitu', 'yakni', 'selain', 'termasuk',
    ];

    $map = [];
    foreach ($words as $w) {
      $map[$w] = true;
    }

    return $map;
  }
}
