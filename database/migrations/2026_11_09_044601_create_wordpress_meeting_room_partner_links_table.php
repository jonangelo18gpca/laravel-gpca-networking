<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(
            'wordpress_meeting_room_partner_links',
            function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('event_id');

                $table->unsignedBigInteger(
                    'meeting_room_partner_id'
                );

                $table->string('source_url', 2048);
                $table->string('post_type', 100);
                $table->unsignedBigInteger('wordpress_post_id');

                $table->char('identity_hash', 64);

                $table->timestamps();

                $table->unique(
                    'identity_hash',
                    'wp_mrp_links_identity_unique'
                );

                $table->unique(
                    'meeting_room_partner_id',
                    'wp_mrp_links_partner_unique'
                );

                $table->index(
                    ['event_id', 'wordpress_post_id'],
                    'wp_mrp_links_event_post_index'
                );

                $table->foreign(
                    'event_id',
                    'wp_mrp_links_event_fk'
                )
                    ->references('id')
                    ->on('events')
                    ->onDelete('cascade');

                $table->foreign(
                    'meeting_room_partner_id',
                    'wp_mrp_links_partner_fk'
                )
                    ->references('id')
                    ->on('meeting_room_partners')
                    ->onDelete('cascade');
            }
        );
    }

    public function down()
    {
        Schema::dropIfExists(
            'wordpress_meeting_room_partner_links'
        );
    }
};