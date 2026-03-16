<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIctPmsHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Table: ict_pms_history
     * Columns:
     *  - id
     *  - ict_pms_id (parent PMS schedule id)
     *  - equipment_id
     *  - pms_date (date performed)
     *  - description (list of checked items, text)
     *  - type (PMS|Repair)
     *  - cost_of_repair (decimal)
     *  - remarks (text)
     *  - created_by (nullable user id)
     *  - timestamps, softDeletes
     *
     * Adjust foreign key names to match your existing tables if needed.
     */
    public function up()
    {
        Schema::create('ict_pms_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ict_pms_id')->index();
            $table->unsignedBigInteger('equipment_id')->nullable()->index();
            $table->date('pms_date')->nullable();
            $table->text('description')->nullable();
            $table->string('type', 50)->default('PMS');
            $table->decimal('cost_of_repair', 12, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            // Optional foreign keys — enable if those tables exist and your naming matches:
            $table->foreign('ict_pms_id')->references('id')->on('ict_pms')->onDelete('cascade');
            $table->foreign('equipment_id')->references('id')->on('ict_equipments')->onDelete('set null'); 
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ict_pms_history');
    }
}
