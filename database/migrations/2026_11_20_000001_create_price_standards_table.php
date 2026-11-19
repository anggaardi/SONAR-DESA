<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_standards', function (Blueprint $table) {
            $table->id();
            $table->string('regency');
            $table->string('code');
            $table->string('name');
            $table->string('unit');
            $table->bigInteger('price_rp');
            $table->integer('year');
            $table->string('source_ref');
            $table->timestampsTz();

            $table->unique(['regency', 'code', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_standards');
    }
};
