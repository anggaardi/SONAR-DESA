<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->string('file_path')->nullable();
            $table->string('source', 32)->default('fixture');
            $table->integer('read_ms')->nullable();
            $table->bigInteger('written_total_rp')->nullable();
            $table->boolean('total_ack')->default(false);
            $table->string('error_code', 64)->nullable();
            $table->timestampsTz();
        });

        Schema::create('audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->integer('position');
            $table->string('raw_name');
            $table->decimal('volume', 14, 3)->nullable();
            $table->string('unit', 32)->nullable();
            $table->bigInteger('unit_price_rp')->nullable();
            $table->bigInteger('amount_rp')->nullable();
            $table->foreignId('standard_id')->nullable()->constrained('price_standards')->nullOnDelete();
            $table->jsonb('doubt')->nullable();
            $table->string('label', 32)->default('no_reference')->index();
            $table->integer('over_pct')->nullable();
            $table->bigInteger('diff_rp')->nullable();
            $table->boolean('corrected')->default(false);
            $table->timestampsTz();

            $table->index(['audit_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_items');
        Schema::dropIfExists('audits');
    }
};
