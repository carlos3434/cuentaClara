<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            // AI extraction (filled by ValidateReceiptJob).
            $table->unsignedBigInteger('extracted_amount_cents')->nullable()->after('status');
            $table->char('extracted_currency', 3)->nullable()->after('extracted_amount_cents');
            $table->date('extracted_date')->nullable()->after('extracted_currency');
            $table->string('extracted_method', 32)->nullable()->after('extracted_date');
            $table->string('extracted_recipient')->nullable()->after('extracted_method');
            $table->decimal('confidence', 4, 3)->nullable()->after('extracted_recipient');
            $table->text('ai_explanation')->nullable()->after('confidence');
            $table->json('ai_raw')->nullable()->after('ai_explanation');

            // Verdict trail.
            $table->string('reason_code', 32)->nullable()->after('ai_raw');
            $table->string('decided_by', 16)->nullable()->after('reason_code'); // ai|organizer
            $table->timestamp('decided_at')->nullable()->after('decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn([
                'extracted_amount_cents',
                'extracted_currency',
                'extracted_date',
                'extracted_method',
                'extracted_recipient',
                'confidence',
                'ai_explanation',
                'ai_raw',
                'reason_code',
                'decided_by',
                'decided_at',
            ]);
        });
    }
};
