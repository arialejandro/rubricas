<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Centro de trabajo + turno. Una maestra de primaria tiene a lo más dos grupos:
        // uno matutino y uno vespertino, y cada uno puede estar en una escuela distinta.
        Schema::table('groups', function (Blueprint $table) {
            $table->string('shift', 12)->default('matutino')->after('user_id');
            $table->unsignedTinyInteger('grade')->nullable()->after('shift');
            $table->string('school_name')->nullable()->after('school_year');
            $table->string('school_cct', 20)->nullable()->after('school_name');
            $table->string('school_zone', 40)->nullable()->after('school_cct');

            $table->unique(['user_id', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'shift']);
            $table->dropColumn(['shift', 'grade', 'school_name', 'school_cct', 'school_zone']);
        });
    }
};
