<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('agenda_title')->nullable()->after('slido_link');
            $table->text('agenda_url')->nullable()->after('agenda_title');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'agenda_title',
                'agenda_url',
            ]);
        });
    }
};