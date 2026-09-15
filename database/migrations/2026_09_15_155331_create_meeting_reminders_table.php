<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meeting_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('meeting_id')
                ->constrained('meetings')
                ->cascadeOnDelete();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('window', 32);
            $table->timestamp('database_sent_at')->nullable();
            $table->timestamp('mail_sent_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['meeting_id', 'user_id', 'window'],
                'meeting_reminder_recipient_window_uq'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_reminders');
    }
};
