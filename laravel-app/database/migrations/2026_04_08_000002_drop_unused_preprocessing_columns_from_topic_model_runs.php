<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    $columnsToDrop = [];

    if (Schema::hasColumn('topic_model_runs', 'remove_stopwords')) {
      $columnsToDrop[] = 'remove_stopwords';
    }

    if (Schema::hasColumn('topic_model_runs', 'min_word_length')) {
      $columnsToDrop[] = 'min_word_length';
    }

    if (Schema::hasColumn('topic_model_runs', 'language')) {
      $columnsToDrop[] = 'language';
    }

    if (!empty($columnsToDrop)) {
      Schema::table('topic_model_runs', function (Blueprint $table) use ($columnsToDrop) {
        $table->dropColumn($columnsToDrop);
      });
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('topic_model_runs', function (Blueprint $table) {
      if (!Schema::hasColumn('topic_model_runs', 'remove_stopwords')) {
        $table->boolean('remove_stopwords')->default(true);
      }

      if (!Schema::hasColumn('topic_model_runs', 'min_word_length')) {
        $table->integer('min_word_length')->default(3);
      }

      if (!Schema::hasColumn('topic_model_runs', 'language')) {
        $table->string('language')->default('indonesian');
      }
    });
  }
};
