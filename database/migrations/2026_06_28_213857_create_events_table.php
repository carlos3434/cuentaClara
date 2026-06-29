<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            // Nullable for now: organizer auth is the next vertical slice.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 16)->unique();
            $table->string('name', 120);
            $table->date('event_date');
            // Money stored as integer cents (PEN). See docs/13.
            $table->unsignedBigInteger('total_cents');
            $table->unsignedInteger('headcount');
            $table->unsignedBigInteger('share_cents');
            $table->string('recipient_name', 120);
            $table->string('recipient_handle', 60)->nullable();
            $table->json('accepted_methods');
            $table->date('pay_deadline');
            $table->string('status', 16)->default('active'); // draft|active|closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
