<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('bpd_verdict', 16)->nullable()->after('invalid_reason');
            $table->timestampTz('bpd_verified_at')->nullable()->after('bpd_verdict');
        });

        // Reports already marked invalid before this migration count as verified.
        DB::table('reports')
            ->whereNotNull('invalid_reason')
            ->update(['bpd_verdict' => 'invalid', 'bpd_verified_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn(['bpd_verdict', 'bpd_verified_at']);
        });
    }
};
