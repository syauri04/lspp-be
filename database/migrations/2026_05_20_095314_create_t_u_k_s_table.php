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
        Schema::create('t_u_k_s', function (Blueprint $table) {
            $table->id();

            $table->string('title');

            $table->string('city');

            $table->text('address');

            $table->string('open_days')
                ->nullable();

            $table->string('open_hours')
                ->nullable();

            $table->text('google_maps_url')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_u_k_s');
    }
};
