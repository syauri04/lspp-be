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
        Schema::table('category_skema_sertifikasi', function (Blueprint $table) {
            $table->json('kategori')->change();
        });

        Schema::table('skema_sertifikasi', function (Blueprint $table) {
            $table->json('title')->change();
            $table->json('summary')->nullable()->change();
            $table->json('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('category_skema_sertifikasi', function (Blueprint $table) {
            $table->string('kategori')->change();
        });

        Schema::table('skema_sertifikasi', function (Blueprint $table) {
            $table->string('title')->change();
            $table->text('summary')->nullable()->change();
            $table->longText('description')->nullable()->change();
        });
    }
};
