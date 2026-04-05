<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('topic_model_datasets', function (Blueprint $table) {
      $table->id();
      $table->foreignId('skripsi_id')->constrained('skripsi')->onDelete('cascade');
      $table->string('title')->nullable();
      $table->longText('abstract')->nullable();
      $table->longText('conclusion')->nullable();
      $table->integer('year')->nullable()->index();
      $table->longText('cleaned_text');
      $table->longText('processed_text');
      $table->timestamps();

      $table->unique('skripsi_id');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('topic_model_datasets');
  }
};
