<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('measurements', 'muac_cm')) {
            return;
        }

        Schema::table('measurements', function (Blueprint $table) {
            // LiLA (lingkar lengan atas). Opsional: tidak semua pemeriksaan mengukurnya.
            $table->decimal('muac_cm', 4, 1)->nullable()->after('height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->dropColumn('muac_cm');
        });
    }
};
