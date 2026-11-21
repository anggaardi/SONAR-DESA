<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M4-02: answers and photos of a Cek Spek report, plus the 22 Nov decisions on reports (D-009, D-010, D-011).
 * One active report per account and per device in a project cycle is enforced here, not only in code (F4-4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            // No ON DELETE: a measurement with answers is evidence and is removed only together with its project.
            $table->foreignId('measurement_id')->constrained('project_measurements');
            $table->string('answer', 32);
            // C5: only for "Tidak"; same precision as project_measurements.value, unit copied from the RAB.
            $table->decimal('measured_value', 14, 3)->nullable();
            $table->string('measured_unit', 32)->nullable();
            $table->timestampsTz();

            $table->unique(['report_id', 'measurement_id']);
        });

        Schema::create('report_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            // Private disk, re-encoded WebP (EXIF/GPS dropped). Never public (C11).
            $table->string('path');
            $table->unsignedSmallInteger('position');
            $table->timestampsTz();

            $table->unique(['report_id', 'position']);
        });

        Schema::table('reports', function (Blueprint $table) {
            // D-010: HMAC-SHA256 of the random device cookie; the cookie value itself is never stored.
            // Nullable for reports made before this migration.
            $table->char('device_hash', 64)->nullable();
        });

        // D-009: out of radius is refused before saving, so every report is inside the radius.
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'in_radius', 'invalid_reason']);
            $table->dropIndex(['in_radius']);
            $table->dropColumn('in_radius');
            $table->index(['project_id', 'invalid_reason']);
        });

        // F4-4, D-010. Partial indexes need raw SQL. NULLS NOT DISTINCT (Postgres 15+) also covers reports
        // sent before the project has a review cycle (cycle_id null).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX reports_active_per_user_unique
            ON reports (project_id, cycle_id, user_id) NULLS NOT DISTINCT
            WHERE superseded_by IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX reports_active_per_device_unique
            ON reports (project_id, cycle_id, device_hash) NULLS NOT DISTINCT
            WHERE superseded_by IS NULL AND device_hash IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reports_active_per_device_unique');
        DB::statement('DROP INDEX IF EXISTS reports_active_per_user_unique');

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'invalid_reason']);
            $table->boolean('in_radius')->default(true)->index();
            $table->index(['project_id', 'in_radius', 'invalid_reason']);
            $table->dropColumn('device_hash');
        });

        Schema::dropIfExists('report_photos');
        Schema::dropIfExists('report_answers');
    }
};
