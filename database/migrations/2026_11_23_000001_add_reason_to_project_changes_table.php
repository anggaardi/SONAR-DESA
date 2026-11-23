<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_changes', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('new_value');
        });
    }

    public function down(): void
    {
        Schema::table('project_changes', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
