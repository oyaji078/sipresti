<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toddlers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // BLT-000001
            $table->string('nik', 32)->nullable()->index();
            $table->string('name', 150)->index();
            $table->date('birth_date');
            $table->enum('gender', ['L', 'P'])->index();
            $table->string('mother_name', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('village', 100)->nullable()->index();
            $table->string('posyandu', 100)->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['village', 'posyandu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toddlers');
    }
};
