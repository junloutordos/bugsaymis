<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('procurements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('pr_no');
            $table->date('pr_date')->nullable();
            $table->text('purpose')->nullable();
            $table->string('requested_by')->nullable();
            $table->date('date_approved_budget_officer')->nullable();
            $table->date('date_decline_budget_officer')->nullable();
            $table->date('date_approved_procurement')->nullable();
            $table->date('date_decline_procurement')->nullable();
            $table->date('ocd_approve_date')->nullable();
            $table->date('ocd_decline_date')->nullable();
            $table->date('date_approved_division_chief')->nullable();
            $table->date('date_decline_division_chief')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('procurements');
    }
};
