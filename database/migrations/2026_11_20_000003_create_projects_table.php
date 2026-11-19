<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_id')->unique()->constrained('audits')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type', 32);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('fund_source');
            $table->integer('year');
            $table->integer('progress_pct')->default(0);
            $table->unsignedBigInteger('current_cycle_id')->nullable();
            $table->string('public_status', 32)->default('none')->index();
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::create('project_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('value', 14, 3);
            $table->string('unit', 32);
            $table->string('how_to');
            $table->string('origin', 16)->default('template');
            $table->timestampsTz();

            $table->index(['project_id', 'origin']);
        });

        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->integer('progress_pct');
            $table->timestampTz('taken_at');
            $table->timestampsTz();

            $table->index(['project_id', 'taken_at']);
        });

        Schema::create('project_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('field');
            $table->string('item_label');
            $table->string('old_value');
            $table->string('new_value');
            $table->timestampTz('changed_at');
            $table->timestampsTz();

            $table->index(['project_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_changes');
        Schema::dropIfExists('progress_photos');
        Schema::dropIfExists('project_measurements');
        Schema::dropIfExists('projects');
    }
};
