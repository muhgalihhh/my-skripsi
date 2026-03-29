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
    Schema::create('topic_model_runs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained()->onDelete('cascade');

      $table->enum('status', ['pending', 'preprocessing', 'training', 'completed', 'failed'])->default('pending');

      // FastAPI job ids (optional)
      $table->string('fastapi_training_job_id')->nullable()->index();

      // Preprocessing settings snapshot
      $table->boolean('remove_stopwords')->default(true);
      $table->integer('min_word_length')->default(3);
      $table->string('language')->default('indonesian');

      // Training hyperparameters snapshot (from metadata.json / UI)
      $table->json('bertopic_params')->nullable();

      // Summary stats
      $table->integer('total_documents')->default(0);
      $table->integer('total_tokens_before')->default(0);
      $table->integer('total_tokens_after_cleaned')->default(0);

      // Store step-by-step preview samples
      $table->json('preprocessing_preview')->nullable();

      // Training outputs (subset)
      $table->integer('num_topics')->nullable();
      $table->integer('num_outliers')->nullable();
      $table->decimal('coherence_cv', 8, 4)->nullable();
      $table->decimal('topic_diversity', 8, 4)->nullable();
      $table->string('model_path')->nullable();

      $table->text('error_message')->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('completed_at')->nullable();
      $table->timestamps();
    });

    Schema::create('topic_model_topics', function (Blueprint $table) {
      $table->id();
      $table->foreignId('topic_model_run_id')->constrained('topic_model_runs')->onDelete('cascade');

      $table->integer('topic_id')->index();
      $table->integer('count')->default(0);
      $table->json('top_words');
      $table->json('word_scores')->nullable();

      $table->timestamps();

      $table->unique(['topic_model_run_id', 'topic_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('topic_model_topics');
    Schema::dropIfExists('topic_model_runs');
  }
};
