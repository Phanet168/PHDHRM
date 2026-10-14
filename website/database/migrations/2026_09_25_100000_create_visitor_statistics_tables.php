<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymous_visitors', function (Blueprint $table) {
            $table->char('token_hash', 64)->primary();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->unsignedBigInteger('page_views')->default(0);
        });

        Schema::create('visitor_days', function (Blueprint $table) {
            $table->date('date');
            $table->char('token_hash', 64);
            $table->primary(['date', 'token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_days');
        Schema::dropIfExists('anonymous_visitors');
    }
};
