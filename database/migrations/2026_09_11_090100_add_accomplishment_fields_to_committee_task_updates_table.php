<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a task update double as a formal accomplishment submission —
     * is_accomplishment flags the update as "what I actually delivered"
     * (vs. a plain progress note), and mov_link carries the means-of-
     * verification link. The committee chairperson's Rate modal then pulls
     * the latest flagged update per member instead of manually re-typing
     * accomplishment text sourced from Done task titles.
     */
    public function up(): void
    {
        Schema::table('committee_task_updates', function (Blueprint $table) {
            $table->boolean('is_accomplishment')->default(false)->after('body');
            $table->string('mov_link', 500)->nullable()->after('is_accomplishment');
        });
    }

    public function down(): void
    {
        Schema::table('committee_task_updates', function (Blueprint $table) {
            $table->dropColumn(['is_accomplishment', 'mov_link']);
        });
    }
};
