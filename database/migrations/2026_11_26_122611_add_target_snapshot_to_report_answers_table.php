<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('report_answers', function (Blueprint $table): void {
            $table->decimal('target_value', 14, 3)->nullable()->after('measured_unit');
            $table->string('target_unit', 32)->nullable()->after('target_value');
        });

        DB::statement('
            UPDATE report_answers
            SET target_value = pm.value,
                target_unit = pm.unit
            FROM project_measurements pm
            WHERE pm.id = report_answers.measurement_id
              AND report_answers.target_value IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_answers', function (Blueprint $table): void {
            $table->dropColumn(['target_value', 'target_unit']);
        });
    }
};
