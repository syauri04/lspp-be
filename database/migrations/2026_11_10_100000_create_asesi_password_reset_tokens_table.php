<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token reset password KHUSUS asesi (website lspp-fe), terpisah dari password_reset_tokens milik admin CMS.
 * Sebelumnya broker 'asesis' memakai tabel yang sama dan token hanya dikunci per email, sehingga token reset
 * asesi bisa dipakai di form reset CMS untuk email admin yang sama. Hanya menambah tabel baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asesi_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token'); // hash bcrypt, bukan token asli
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesi_password_reset_tokens');
    }
};
