<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One application may front several ChaosDesk sites.
     *
     * A ticket ulid is only unique within one site, so the unique index moves
     * from the ulid alone to (site, ulid). Existing rows belong to "default".
     * The numeric ticket id is remembered for display next to the ulid.
     */
    public function up(): void
    {
        Schema::table('chaosdesk_tickets', function (Blueprint $table): void {
            $table->string('site')->default('default')->after('external_id');
            $table->unsignedBigInteger('ticket_id')->nullable()->after('ticket_ulid');
        });

        Schema::table('chaosdesk_tickets', function (Blueprint $table): void {
            $table->dropUnique('chaosdesk_tickets_ticket_ulid_unique');
            $table->unique(['site', 'ticket_ulid']);
        });
    }

    public function down(): void
    {
        Schema::table('chaosdesk_tickets', function (Blueprint $table): void {
            $table->dropUnique(['site', 'ticket_ulid']);
            $table->unique('ticket_ulid');
        });

        Schema::table('chaosdesk_tickets', function (Blueprint $table): void {
            $table->dropColumn(['site', 'ticket_id']);
        });
    }
};
