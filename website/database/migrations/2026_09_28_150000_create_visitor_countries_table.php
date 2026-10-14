<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Country of each anonymous visitor (first resolved country wins). Only the
     * two-letter country code is kept: the IP address is read in memory for the
     * lookup and is never stored.
     */
    public function up(): void
    {
        Schema::create('visitor_countries', function (Blueprint $table) {
            $table->char('token_hash', 64)->primary();
            $table->char('country_code', 2)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_countries');
    }
};
