<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_answers', function (Blueprint $table) {
            $table->dropForeign(['measurement_id']);
            $table->foreign('measurement_id')
                ->references('id')
                ->on('project_measurements')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('report_answers', function (Blueprint $table) {
            $table->dropForeign(['measurement_id']);
            $table->foreign('measurement_id')
                ->references('id')
                ->on('project_measurements');
        });
    }
};
