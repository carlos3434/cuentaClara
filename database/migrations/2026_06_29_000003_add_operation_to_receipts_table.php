<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            // Operation / constancia number read off the voucher — used as an
            // assist for the organizer and to spot duplicate uploads.
            $table->string('extracted_operation')->nullable()->after('extracted_recipient');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('extracted_operation');
        });
    }
};
