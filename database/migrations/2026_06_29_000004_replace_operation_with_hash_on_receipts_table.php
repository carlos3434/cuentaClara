<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store a one-way hash of the operation number for duplicate detection
        // instead of the clear value (data minimization — see docs/privacy).
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('operation_hash', 64)->nullable()->index()->after('extracted_recipient');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('extracted_operation');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('extracted_operation')->nullable()->after('extracted_recipient');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('operation_hash');
        });
    }
};
