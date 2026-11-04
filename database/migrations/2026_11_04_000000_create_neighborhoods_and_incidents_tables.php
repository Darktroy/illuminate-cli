<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighborhoods', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('name')->unique();
            $table->json('boundary'); // [[lat, lng], ...] polygon vertices
            $table->json('properties')->nullable();
        });

        Schema::create('incidents', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->double('lat');
            $table->double('lng');
            $table->string('code')->nullable(); // metadata->incident->code, lifted out for querying
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->json('reports')->nullable();
            $table->timestamp('occurred_at')->nullable();

            $table->index(['lat', 'lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('neighborhoods');
    }
};
