<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->restrictOnDelete();
            $table->string('scope', 32);
            $table->string('destination_type', 40);
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('province_code', 2);
            $table->string('district_code', 4)->nullable();
            $table->string('commune_code', 6)->nullable();
            $table->string('village_code', 8)->nullable();
            $table->string('venue_name', 255);
            $table->text('address')->nullable();
            $table->json('location_snapshot');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['mission_id', 'sort_order'], 'mission_destinations_order_idx');
            $table->index(['province_code', 'scope'], 'mission_destinations_province_idx');
        });
        Schema::create('mission_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->restrictOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('action', 50);
            $table->foreignId('acted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('workflow_action_id')->nullable()->constrained('workflow_instance_actions')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['mission_id', 'occurred_at', 'id'], 'mission_history_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_status_histories');
        Schema::dropIfExists('mission_destinations');
    }
};
