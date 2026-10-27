<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installations', function (Blueprint $table) {
            $table->text('id')->primary();
            $table->text('workspace_id');
            $table->text('workspace_name');
            $table->jsonb('granted_permissions')->default('[]');
            $table->timestampTz('installed_at');
            $table->timestampTz('uninstalled_at')->nullable();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->text('delivery_id')->primary();
            $table->text('event');
            $table->text('installation_id');
            $table->timestampTz('received_at')->useCurrent();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('installation_id');
            $table->text('channel_id');
            $table->text('author_id');
            $table->text('author_name');
            $table->text('body');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['installation_id', 'channel_id', 'created_at'], 'posts_installation_channel_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('installations');
    }
};
