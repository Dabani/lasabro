<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One row per plugin that has been installed on this system.
        Schema::create('lasabro_plugins', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('code', 100)->unique();            // Lasabro.Hr
            $t->string('version', 40);
            $t->boolean('is_active')->default(false);
            $t->timestamp('installed_at')->nullable();
            $t->timestamp('activated_at')->nullable();
            $t->timestamps();
        });

        // Which permission / menu / widget ... codes each plugin created (so only those are touched when it is switched off or removed).
        Schema::create('lasabro_plugin_resources', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('plugin_code', 100);
            $t->string('type', 40);
            $t->string('code', 190);
            $t->timestamps();
            $t->unique(['plugin_code', 'type', 'code']);
        });

        // Append-only history: installed, activated, deactivated, uninstalled.
        Schema::create('lasabro_plugin_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('plugin_code', 100)->index();
            $t->string('action', 40);
            $t->string('detail')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lasabro_plugin_events');
        Schema::dropIfExists('lasabro_plugin_resources');
        Schema::dropIfExists('lasabro_plugins');
    }
};
