<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: table was added directly to production before this migration existed.
        // On production the table already exists — skip creation, just let the migration
        // record land in the migrations table so artisan doesn't error on re-run.
        if (Schema::hasTable('students')) {
            return;
        }

        DB::statement("CREATE TABLE `students` (
            `id`                   int           NOT NULL AUTO_INCREMENT,
            `lastname`             varchar(200)  DEFAULT NULL,
            `firstname`            varchar(200)  DEFAULT NULL,
            `middlename`           varchar(200)  DEFAULT NULL,
            `birthday`             varchar(20)   DEFAULT NULL,
            `sex`                  varchar(10)   DEFAULT NULL,
            `studentcontact`       varchar(50)   DEFAULT NULL,
            `contactperson`        varchar(200)  DEFAULT NULL,
            `contactno1`           varchar(20)   DEFAULT NULL,
            `img`                  varchar(200)  DEFAULT NULL,
            `signature`            varchar(200)  DEFAULT NULL,
            `remarks`              varchar(500)  DEFAULT NULL,
            `entrytype`            varchar(50)   DEFAULT NULL,
            `pisaysystemID2`       varchar(255)  DEFAULT NULL,
            `mother_name`          varchar(255)  DEFAULT NULL,
            `father_name`          varchar(255)  DEFAULT NULL,
            `province`             varchar(255)  DEFAULT NULL,
            `district`             varchar(255)  DEFAULT NULL,
            `municipal`            varchar(255)  DEFAULT NULL,
            `houseno`              varchar(255)  DEFAULT NULL,
            `status`               varchar(50)   DEFAULT NULL,
            `encodedby`            varchar(200)  DEFAULT NULL,
            `date_encoded`         varchar(20)   DEFAULT NULL,
            `bloodtype`            varchar(255)  DEFAULT NULL,
            `batch`                varchar(255)  NOT NULL DEFAULT '',
            `nickname`             varchar(255)  DEFAULT NULL,
            `birthplace`           varchar(255)  DEFAULT NULL,
            `religion`             varchar(255)  DEFAULT NULL,
            `ethnic`               varchar(255)  NOT NULL DEFAULT '',
            `nationality`          varchar(255)  DEFAULT NULL,
            `region`               varchar(255)  DEFAULT NULL,
            `dateofgraduation`     varchar(20)   DEFAULT NULL,
            `noofgraduates`        int           DEFAULT NULL,
            `average`              float(4,2)    DEFAULT NULL,
            `honors`               varchar(255)  DEFAULT NULL,
            `fathersign`           varchar(255)  DEFAULT NULL,
            `mothersign`           varchar(255)  DEFAULT NULL,
            `motheraddress`        varchar(255)  DEFAULT NULL,
            `fatheraddress`        varchar(255)  DEFAULT NULL,
            `mcitizenship`         varchar(50)   DEFAULT NULL,
            `fcitizenship`         varchar(50)   DEFAULT NULL,
            `mcpno`                varchar(50)   DEFAULT NULL,
            `fcpno`                varchar(50)   DEFAULT NULL,
            `memailaddress`        varchar(255)  DEFAULT NULL,
            `femailaddress`        varchar(255)  DEFAULT NULL,
            `moccupation`          varchar(255)  DEFAULT '',
            `foccupation`          varchar(255)  DEFAULT NULL,
            `mcompany`             varchar(255)  DEFAULT NULL,
            `fcompany`             varchar(255)  DEFAULT '',
            `mofcaddress`          varchar(255)  DEFAULT NULL,
            `fofcaddress`          varchar(255)  DEFAULT NULL,
            `mofctelno`            varchar(50)   DEFAULT NULL,
            `fofctelno`            varchar(50)   DEFAULT NULL,
            `dormer`               varchar(50)   DEFAULT NULL,
            `studentcitizenship`   varchar(50)   DEFAULT NULL,
            `homeaddresstype`      varchar(50)   DEFAULT NULL,
            `date_updated`         varchar(20)   DEFAULT NULL,
            `schooltype`           varchar(50)   DEFAULT NULL,
            `elemschool`           varchar(500)  DEFAULT NULL,
            `lrn`                  varchar(50)   DEFAULT NULL,
            `student_email`        varchar(255)  DEFAULT NULL,
            `math`                 int           DEFAULT NULL,
            `verbal`               int           DEFAULT NULL,
            `science`              int           DEFAULT NULL,
            `abtract`              int           DEFAULT NULL,
            `regiongraduated`      varchar(100)  NOT NULL DEFAULT '',
            `malumnus`             varchar(3)    NOT NULL DEFAULT '',
            `falumnus`             varchar(3)    NOT NULL DEFAULT '',
            `schocat`              varchar(15)   NOT NULL DEFAULT '',
            `dormer1`              varchar(15)   NOT NULL DEFAULT '',
            `pkey`                 varchar(20)   NOT NULL DEFAULT '',
            `pisaysystemID`        varchar(11)   NOT NULL DEFAULT '',
            `mbirthday`            varchar(20)   NOT NULL DEFAULT '',
            `fbirthday`            varchar(20)   NOT NULL DEFAULT '',
            `meduc`                varchar(100)  NOT NULL DEFAULT '',
            `feduc`                varchar(100)  NOT NULL DEFAULT '',
            `mschool`              varchar(150)  NOT NULL DEFAULT '',
            `fschool`              varchar(150)  NOT NULL DEFAULT '',
            `parentsstatus`        varchar(50)   NOT NULL DEFAULT '',
            `zipcode`              varchar(20)   NOT NULL DEFAULT '',
            `schooladdress`        varchar(500)  NOT NULL DEFAULT '',
            `relation1`            varchar(50)   NOT NULL DEFAULT '',
            `contact_address1`     varchar(200)  NOT NULL DEFAULT '',
            `contact_ofc_address1` varchar(200)  NOT NULL DEFAULT '',
            `contact_ofc_telno1`   varchar(15)   NOT NULL DEFAULT '',
            `contactperson2`       varchar(50)   NOT NULL DEFAULT '',
            `relation2`            varchar(20)   NOT NULL DEFAULT '',
            `contact_address2`     varchar(200)  NOT NULL DEFAULT '',
            `contactno2`           varchar(15)   NOT NULL DEFAULT '',
            `contact_ofc_address2` varchar(200)  NOT NULL DEFAULT '',
            `contact_ofc_telno2`   varchar(15)   NOT NULL DEFAULT '',
            `date_updated1`        varchar(50)   DEFAULT NULL,
            `date_updated2`        varchar(50)   DEFAULT NULL,
            `date_updated3`        varchar(50)   DEFAULT NULL,
            `date_updated4`        varchar(50)   DEFAULT NULL,
            `socioeconomic`        varchar(50)   NOT NULL DEFAULT '',
            `schoolType1`          varchar(50)   NOT NULL DEFAULT '',
            `schoolType2`          varchar(50)   NOT NULL DEFAULT '',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=2707 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
