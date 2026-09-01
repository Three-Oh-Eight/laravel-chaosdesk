<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local references to tickets raised through this application.
     *
     * ChaosDesk owns the ticket; this table only remembers which of your users
     * raised it and the access token needed to read the thread back.
     */
    public function up(): void
    {
        Schema::create('chaosdesk_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id')->index();
            $table->string('ticket_ulid', 26)->unique();
            $table->string('access_token', 64);
            $table->string('subject');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chaosdesk_tickets');
    }
};
