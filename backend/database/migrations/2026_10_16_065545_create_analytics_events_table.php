<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('event_id', 64);
            $table->string('visitor_id', 64)->nullable();
            $table->string('session_id', 64)->nullable();
            $table->string('user_id', 64)->nullable();

            $table->string('event_name', 100);
            $table->string('event_source', 20);
            $table->string('page_url', 2048)->nullable();

            $table->json('properties')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamp('received_at')->useCurrent();

            $table->unique(['project_id', 'event_id']);

            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'event_name', 'occurred_at']);
            $table->index(['project_id', 'visitor_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};


