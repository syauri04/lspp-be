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
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('news_category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->json('title');

            $table->string('slug')
                ->unique();

            $table->json('summary')
                ->nullable();

            $table->json('content');

            $table->string('image')
                ->nullable();

            $table->string('source')
                ->nullable();

            $table->unsignedBigInteger('is_view')
                ->default(0);

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
        Schema::dropIfExists('news_articles');
    }
};
