<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrasi awal (0001_01_01_000000) seharusnya sudah membuat tabel `sessions`,
 * tapi pada deploy produksi tabel ini hilang (kemungkinan proses migrate
 * sebelumnya terhenti di tengah jalan) sehingga SESSION_DRIVER=database gagal
 * total dan pengguna terus diminta login ulang. Migrasi ini dibuat idempoten
 * agar aman dijalankan berapa kali pun, di lingkungan mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
