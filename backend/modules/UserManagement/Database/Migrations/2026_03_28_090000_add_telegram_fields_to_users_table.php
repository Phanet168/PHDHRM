<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable()->unique()->after('contact_no');
            }

            if (!Schema::hasColumn('users', 'telegram_link_token')) {
                $table->string('telegram_link_token')->nullable()->index()->after('telegram_chat_id');
            }

            if (!Schema::hasColumn('users', 'telegram_linked_at')) {
                $table->timestamp('telegram_linked_at')->nullable()->after('telegram_link_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'telegram_linked_at')) {
                $table->dropColumn('telegram_linked_at');
            }

            if (Schema::hasColumn('users', 'telegram_link_token')) {
                $table->dropIndex('users_telegram_link_token_index');
                $table->dropColumn('telegram_link_token');
            }

            if (Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->dropUnique('users_telegram_chat_id_unique');
                $table->dropColumn('telegram_chat_id');
            }
        });
    }
};

