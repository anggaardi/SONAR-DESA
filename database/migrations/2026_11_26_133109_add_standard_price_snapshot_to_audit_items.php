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
        Schema::table('audit_items', function (Blueprint $table) {
            $table->bigInteger('standard_price_rp')->nullable()->after('standard_id');
        });

        // Backfill: salin price_rp dari price_standards untuk baris yang sudah punya standard_id.
        DB::statement(
            'UPDATE audit_items ai
             SET standard_price_rp = ps.price_rp
             FROM price_standards ps
             WHERE ai.standard_id = ps.id'
        );
    }

    public function down(): void
    {
        Schema::table('audit_items', function (Blueprint $table) {
            $table->dropColumn('standard_price_rp');
        });
    }
};
