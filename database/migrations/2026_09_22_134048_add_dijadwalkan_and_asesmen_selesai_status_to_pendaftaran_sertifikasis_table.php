<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE pendaftaran_sertifikasis MODIFY COLUMN status ENUM(
            'submitted', 'approved', 'rejected', 'awaiting_payment', 'paid',
            'dijadwalkan', 'asesmen_selesai',
            'completed', 'expired', 'cancelled'
        ) NOT NULL DEFAULT 'submitted'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE pendaftaran_sertifikasis MODIFY COLUMN status ENUM(
            'submitted', 'approved', 'rejected', 'awaiting_payment', 'paid',
            'completed', 'expired', 'cancelled'
        ) NOT NULL DEFAULT 'submitted'");
    }
};
