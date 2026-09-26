<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 50)->unique();
            $table->string('algorithm', 50)->default('XGBoost');
            $table->string('artifact_name', 150)->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->unsignedInteger('dataset_size')->nullable();
            $table->unsignedInteger('training_size')->nullable();
            $table->unsignedInteger('testing_size')->nullable();

            // Kolom metrik sengaja nullable dan tetap NULL sampai ada hasil
            // training sebenarnya. Dilarang diisi angka contoh.
            $table->decimal('accuracy', 8, 6)->nullable();
            $table->decimal('precision_score', 8, 6)->nullable();
            $table->decimal('recall', 8, 6)->nullable();
            $table->decimal('f1_score', 8, 6)->nullable();

            $table->json('parameters_json')->nullable();
            $table->json('features_json')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_versions');
    }
};
