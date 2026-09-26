<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toddler_id')->constrained('toddlers')->restrictOnDelete();
            $table->date('measurement_date');
            $table->unsignedSmallInteger('age_months');
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('height_cm', 5, 1);
            $table->enum('source', ['MANUAL', 'IMPORT_EPPBGM'])->default('MANUAL')->index();
            $table->foreignId('import_history_id')->nullable()->constrained('import_histories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['toddler_id', 'measurement_date']);
            $table->unique(['toddler_id', 'measurement_date'], 'measurements_toddler_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
