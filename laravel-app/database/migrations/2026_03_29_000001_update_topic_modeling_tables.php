<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Tambah kolom yang diperlukan & hapus kolom tidak perlu dari topic_model_runs.
   *
   * Perubahan:
   *   + model_type       : enum bertopic/lda (wajib untuk mendukung dua model)
   *   + lda_params       : JSON snapshot hyperparameter LDA
   *   + training_duration_seconds : durasi training dari FastAPI
   *   - preprocessing_preview    : tidak diperlukan (terlalu besar, tidak berguna)
   *   - total_tokens_before      : cukup di API response, tidak perlu di DB
   *   - total_tokens_after_cleaned : cukup di API response
   */
  public function up(): void
  {
    Schema::table('topic_model_runs', function (Blueprint $table) {
      // Tambah model_type setelah user_id
      $table->enum('model_type', ['bertopic', 'lda'])
        ->default('bertopic')
        ->after('user_id');

      // Tambah lda_params (JSON snapshot hyperparameter LDA)
      $table->json('lda_params')->nullable()->after('bertopic_params');

      // Tambah durasi training
      $table->decimal('training_duration_seconds', 8, 2)->nullable()->after('model_path');

      // Hapus kolom yang tidak diperlukan di DB
      $table->dropColumn([
        'preprocessing_preview',
        'total_tokens_before',
        'total_tokens_after_cleaned',
      ]);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('topic_model_runs', function (Blueprint $table) {
      $table->dropColumn(['model_type', 'lda_params', 'training_duration_seconds']);

      // Restore dropped columns
      $table->json('preprocessing_preview')->nullable();
      $table->integer('total_tokens_before')->default(0);
      $table->integer('total_tokens_after_cleaned')->default(0);
    });
  }
};
