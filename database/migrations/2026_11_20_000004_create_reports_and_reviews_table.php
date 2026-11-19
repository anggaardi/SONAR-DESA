<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('opened_at');
            $table->timestampTz('in_review_since')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->string('result', 32)->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index(['project_id', 'opened_at']);
        });

        Schema::create('review_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('review_cycles')->cascadeOnDelete();
            $table->text('note');
            $table->timestampsTz();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('review_cycles')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 32)->index();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->decimal('distance_m', 8, 2)->nullable();
            $table->boolean('in_radius')->default(true)->index();
            $table->string('note', 280)->nullable();
            $table->unsignedBigInteger('superseded_by')->nullable();
            $table->string('invalid_reason')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampsTz();

            $table->index(['project_id', 'in_radius', 'invalid_reason']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('current_cycle_id')->references('id')->on('review_cycles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['current_cycle_id']);
        });

        Schema::dropIfExists('reports');
        Schema::dropIfExists('review_notes');
        Schema::dropIfExists('review_cycles');
    }
};
