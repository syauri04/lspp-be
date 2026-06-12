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
        Schema::create('skema_sertifikasi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_skema_id')
                ->constrained('category_skema_sertifikasi')
                ->cascadeOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();

            $table->decimal('amount', 15, 2)->default(0);

            $table->unsignedBigInteger('is_view')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skema_sertifikasi');
    }
};
