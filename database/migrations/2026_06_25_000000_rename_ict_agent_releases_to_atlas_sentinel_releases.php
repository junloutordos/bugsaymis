<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ict_agent_releases') && ! Schema::hasTable('atlas_sentinel_releases')) {
            Schema::rename('ict_agent_releases', 'atlas_sentinel_releases');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('atlas_sentinel_releases') && ! Schema::hasTable('ict_agent_releases')) {
            Schema::rename('atlas_sentinel_releases', 'ict_agent_releases');
        }
    }
};
