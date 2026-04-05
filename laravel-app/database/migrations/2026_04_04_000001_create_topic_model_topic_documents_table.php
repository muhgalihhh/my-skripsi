<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('topic_model_topic_documents', function (Blueprint $table) {
      $table->id();
      $table->foreignId('topic_model_run_id')->constrained('topic_model_runs')->onDelete('cascade');
      $table->foreignId('topic_model_topic_id')->constrained('topic_model_topics')->onDelete('cascade');
      $table->foreignId('skripsi_id')->constrained('skripsi')->onDelete('cascade');
      $table->integer('topic_id')->index();
      $table->timestamps();

      $table->unique(['topic_model_run_id', 'skripsi_id']);
      $table->index(['topic_model_run_id', 'topic_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('topic_model_topic_documents');
  }
};
