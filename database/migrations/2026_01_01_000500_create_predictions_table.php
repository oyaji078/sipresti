<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toddler_id')->constrained('toddlers')->restrictOnDelete();
            $table->foreignId('measurement_id')->constrained('measurements')->restrictOnDelete();
            $table->foreignId('model_version_id')->nullable()->constrained('model_versions')->nullOnDelete();
            $table->string('prediction_class', 50);
            $table->string('prediction_label', 100)->index();
            $table->decimal('probability', 8, 6);
            $table->json('input_snapshot_json');
            $table->char('request_id', 36)->unique();
            $table->foreignId('predicted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('predicted_at')->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['toddler_id', 'predicted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
