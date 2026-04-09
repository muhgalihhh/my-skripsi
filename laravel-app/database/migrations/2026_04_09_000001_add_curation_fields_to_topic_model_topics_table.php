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
    Schema::table('topic_model_topics', function (Blueprint $table) {
      $table->string('custom_name', 150)->nullable()->after('word_scores');
      $table->text('representation_description')->nullable()->after('custom_name');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('topic_model_topics', function (Blueprint $table) {
      $table->dropColumn(['custom_name', 'representation_description']);
    });
  }
};
