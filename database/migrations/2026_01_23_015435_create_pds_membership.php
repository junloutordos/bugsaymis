<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pds_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_id')->constrained('pds')->onDelete('cascade');

            /*
            |-------------------------------------------------
            | 34. Consanguinity / Affinity
            |-------------------------------------------------
            */
            $table->boolean('q34a_third_degree')->nullable();
            $table->text('q34a_details')->nullable();

            $table->boolean('q34b_fourth_degree')->nullable();
            $table->text('q34b_details')->nullable();

            /*
            |-------------------------------------------------
            | 35. Administrative / Criminal Cases
            |-------------------------------------------------
            */
            $table->boolean('q35a_admin_offense')->nullable();
            $table->text('q35a_details')->nullable();

            $table->boolean('q35b_criminal_charge')->nullable();
            $table->date('q35b_date_filed')->nullable();
            $table->string('q35b_status')->nullable();
            $table->text('q35b_details')->nullable();

            /*
            |-------------------------------------------------
            | 36. Convicted of crime or violation
            |-------------------------------------------------
            */
            $table->boolean('q36_convicted')->nullable();
            $table->text('q36_details')->nullable();

            /*
            |-------------------------------------------------
            | 37. Separated from service
            |-------------------------------------------------
            */
            $table->boolean('q37_separated_service')->nullable();
            $table->text('q37_details')->nullable();

            /*
            |-------------------------------------------------
            | 38. Election-related
            |-------------------------------------------------
            */
            $table->boolean('q38a_candidate')->nullable();
            $table->text('q38a_details')->nullable();

            $table->boolean('q38b_resigned_for_campaign')->nullable();
            $table->text('q38b_details')->nullable();

            /*
            |-------------------------------------------------
            | 39. Immigrant / Permanent Resident
            |-------------------------------------------------
            */
            $table->boolean('q39_immigrant')->nullable();
            $table->string('q39_country')->nullable();

            /*
            |-------------------------------------------------
            | 40. Special Status
            |-------------------------------------------------
            */
            $table->boolean('q40a_indigenous')->nullable();
            $table->string('q40a_group')->nullable();

            $table->boolean('q40b_pwd')->nullable();
            $table->string('q40b_pwd_id')->nullable();

            $table->boolean('q40c_solo_parent')->nullable();
            $table->string('q40c_solo_parent_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_questions');
    }
};
