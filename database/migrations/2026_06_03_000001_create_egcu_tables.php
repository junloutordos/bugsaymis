<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Guidance / Cumulative Record tables (egcu_ prefix) ────────────────

        if (! Schema::hasTable('egcu_bestfriends')) {
            DB::statement("CREATE TABLE `egcu_bestfriends` (
                `id`            int(5)       NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20)  DEFAULT NULL,
                `levelid`       varchar(5)   DEFAULT NULL,
                `fr_name`       varchar(500) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_educbackground')) {
            DB::statement("CREATE TABLE `egcu_educbackground` (
                `id`                  int(5)       NOT NULL AUTO_INCREMENT,
                `pisaySystemID`       varchar(20)  DEFAULT NULL,
                `levelid`             varchar(20)  DEFAULT NULL,
                `subject_like`        varchar(100) DEFAULT NULL,
                `subject_least`       varchar(100) DEFAULT NULL,
                `subject_difficult`   varchar(100) DEFAULT NULL,
                `subjectlearnedmost`  varchar(100) DEFAULT NULL,
                `subjectlearnedleast` varchar(100) DEFAULT NULL,
                `subjecttaugtbest`    varchar(100) DEFAULT NULL,
                `subjecttaugtworst`   varchar(100) DEFAULT NULL,
                `syid`                int(5)       DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_educbackground1')) {
            DB::statement("CREATE TABLE `egcu_educbackground1` (
                `id`               int(5)      NOT NULL AUTO_INCREMENT,
                `pisaySystemID`    varchar(20) DEFAULT NULL,
                `gradelevel`       varchar(10) DEFAULT NULL,
                `section`          varchar(50) DEFAULT NULL,
                `scholarshipstatus` varchar(50) DEFAULT NULL,
                `academicstatus`   varchar(50) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_extracurricular1')) {
            DB::statement("CREATE TABLE `egcu_extracurricular1` (
                `id`            int(5)       NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20)  DEFAULT NULL,
                `levelid`       varchar(5)   DEFAULT NULL,
                `position`      varchar(100) DEFAULT NULL,
                `clubname`      varchar(200) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_extracurricular2')) {
            DB::statement("CREATE TABLE `egcu_extracurricular2` (
                `id`            int(5)       NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20)  DEFAULT NULL,
                `levelid`       varchar(5)   DEFAULT NULL,
                `activityname`  varchar(100) DEFAULT NULL,
                `category`      varchar(50)  DEFAULT NULL,
                `involvement`   varchar(500) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_extracurricular3')) {
            DB::statement("CREATE TABLE `egcu_extracurricular3` (
                `id`            int(5)       NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20)  DEFAULT NULL,
                `levelid`       varchar(5)   DEFAULT NULL,
                `award`         varchar(100) DEFAULT NULL,
                `competition`   varchar(200) DEFAULT NULL,
                `category`      varchar(50)  DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_extracurricular4')) {
            DB::statement("CREATE TABLE `egcu_extracurricular4` (
                `id`            int(5)       NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20)  DEFAULT NULL,
                `levelid`       varchar(5)   DEFAULT NULL,
                `activity`      varchar(500) DEFAULT NULL,
                `sponsor`       varchar(100) DEFAULT NULL,
                `category`      varchar(50)  DEFAULT NULL,
                `involvement`   varchar(500) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_general_makeup')) {
            DB::statement("CREATE TABLE `egcu_general_makeup` (
                `id`            int(5)       NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20)  DEFAULT NULL,
                `levelid`       varchar(5)   DEFAULT NULL,
                `traits`        varchar(500) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_health')) {
            DB::statement("CREATE TABLE `egcu_health` (
                `id`               int(10)      NOT NULL AUTO_INCREMENT,
                `pisaySystemID`    varchar(20)  DEFAULT NULL,
                `levelid`          int(2)       DEFAULT NULL,
                `weight`           varchar(20)  DEFAULT NULL,
                `height`           varchar(20)  DEFAULT NULL,
                `sight`            varchar(100) DEFAULT NULL,
                `hearing`          varchar(100) DEFAULT NULL,
                `speech`           varchar(100) DEFAULT NULL,
                `general_health`   varchar(100) DEFAULT NULL,
                `history`          varchar(3)   DEFAULT NULL,
                `history_remarks`  varchar(100) DEFAULT NULL,
                `ailment`          varchar(3)   DEFAULT NULL,
                `ailment_remarks`  varchar(100) DEFAULT NULL,
                `date_filed`       varchar(40)  DEFAULT NULL,
                `syid`             int(2)       DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_residence')) {
            DB::statement("CREATE TABLE `egcu_residence` (
                `id`              int(11)     NOT NULL AUTO_INCREMENT,
                `pisaySystemID`   varchar(20) DEFAULT NULL,
                `levelid`         varchar(5)  DEFAULT NULL,
                `residencetype`   varchar(50) DEFAULT NULL,
                `transportation`  varchar(50) DEFAULT NULL,
                `date_filed`      varchar(25) DEFAULT NULL,
                `bguard`          varchar(5)  DEFAULT NULL,
                `syid`            int(5)      DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_selfassessment')) {
            DB::statement("CREATE TABLE `egcu_selfassessment` (
                `id`            int(5)      NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20) DEFAULT NULL,
                `levelid`       varchar(5)  DEFAULT NULL,
                `physical`      varchar(2)  DEFAULT NULL,
                `mental`        varchar(2)  DEFAULT NULL,
                `speech_en`     varchar(2)  DEFAULT NULL,
                `speech_fil`    varchar(2)  DEFAULT NULL,
                `writing`       varchar(2)  DEFAULT NULL,
                `personality`   varchar(2)  DEFAULT NULL,
                `character1`    varchar(2)  DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_selstatements')) {
            DB::statement("CREATE TABLE `egcu_selstatements` (
                `id`            int(5)       NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20)  DEFAULT NULL,
                `levelid`       varchar(5)   DEFAULT NULL,
                `state1`        varchar(500) DEFAULT NULL,
                `state2`        varchar(500) DEFAULT NULL,
                `state3`        varchar(500) DEFAULT NULL,
                `state4`        varchar(500) DEFAULT NULL,
                `state5`        varchar(500) DEFAULT NULL,
                `state6`        varchar(500) DEFAULT NULL,
                `state7`        varchar(500) DEFAULT NULL,
                `state8`        varchar(500) DEFAULT NULL,
                `state9`        varchar(500) DEFAULT NULL,
                `state10`       varchar(500) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_social')) {
            DB::statement("CREATE TABLE `egcu_social` (
                `id`            int(5)      NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20) DEFAULT NULL,
                `levelid`       varchar(5)  DEFAULT NULL,
                `fr_age`        varchar(5)  DEFAULT NULL,
                `fr_older`      varchar(5)  DEFAULT NULL,
                `fr_younger`    varchar(5)  DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_social1')) {
            DB::statement("CREATE TABLE `egcu_social1` (
                `id`                int(5)      NOT NULL AUTO_INCREMENT,
                `pisaySystemID`     varchar(20) NOT NULL DEFAULT '',
                `levelid`           varchar(5)  NOT NULL DEFAULT '',
                `fr_boys`           varchar(5)  NOT NULL DEFAULT '',
                `fr_girls`          varchar(5)  NOT NULL DEFAULT '',
                `fr_otherschool`    varchar(5)  NOT NULL DEFAULT '',
                `fr_fromthischool`  varchar(5)  NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_talents')) {
            DB::statement("CREATE TABLE `egcu_talents` (
                `id`            int(5)      NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20) DEFAULT NULL,
                `levelid`       varchar(5)  DEFAULT NULL,
                `talent`        varchar(300) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('egcu_vocational')) {
            DB::statement("CREATE TABLE `egcu_vocational` (
                `id`            int(5)       NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20)  DEFAULT NULL,
                `levelid`       varchar(5)   DEFAULT NULL,
                `1stchoice`     varchar(300) DEFAULT NULL,
                `2ndchoice`     varchar(300) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        // ── Medical / Health tables (no prefix — universally understood) ──────

        if (! Schema::hasTable('student_allergies')) {
            DB::statement("CREATE TABLE `student_allergies` (
                `id`            int(11)      NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(50)  DEFAULT NULL,
                `allergy`       varchar(50)  DEFAULT NULL,
                `medication`    varchar(255) DEFAULT NULL,
                `created_at`    timestamp    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('student_immunization_history')) {
            DB::statement("CREATE TABLE `student_immunization_history` (
                `id`                int(11)      NOT NULL AUTO_INCREMENT,
                `pisaySystemID`     varchar(20)  DEFAULT NULL,
                `vaccine`           varchar(255) DEFAULT NULL,
                `date_administered` date         DEFAULT NULL,
                `created_at`        timestamp    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('student_medical_history')) {
            DB::statement("CREATE TABLE `student_medical_history` (
                `id`                   int(11)      NOT NULL AUTO_INCREMENT,
                `pisaySystemID`        varchar(20)  DEFAULT NULL,
                `disease`              varchar(255) DEFAULT NULL,
                `date_sustained`       date         DEFAULT NULL,
                `opd`                  tinyint(1)   DEFAULT NULL,
                `hospital_confinement` tinyint(1)   DEFAULT NULL,
                `created_at`           timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }

        if (! Schema::hasTable('student_vitamins_history')) {
            DB::statement("CREATE TABLE `student_vitamins_history` (
                `id`            int(11)      NOT NULL AUTO_INCREMENT,
                `pisaySystemID` varchar(20)  DEFAULT NULL,
                `vitamin`       varchar(255) DEFAULT NULL,
                `date_taken`    date         DEFAULT NULL,
                `created_at`    timestamp    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pisaySystemID` (`pisaySystemID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
        }
    }

    public function down(): void
    {
        $tables = [
            'egcu_bestfriends',
            'egcu_educbackground',
            'egcu_educbackground1',
            'egcu_extracurricular1',
            'egcu_extracurricular2',
            'egcu_extracurricular3',
            'egcu_extracurricular4',
            'egcu_general_makeup',
            'egcu_health',
            'egcu_residence',
            'egcu_selfassessment',
            'egcu_selstatements',
            'egcu_social',
            'egcu_social1',
            'egcu_talents',
            'egcu_vocational',
            'student_allergies',
            'student_immunization_history',
            'student_medical_history',
            'student_vitamins_history',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
