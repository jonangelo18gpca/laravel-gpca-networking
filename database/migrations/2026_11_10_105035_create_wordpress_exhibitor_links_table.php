<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('wordpress_exhibitor_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignId('exhibitor_id')
                ->constrained('exhibitors')
                ->cascadeOnDelete();

            $table->text('source_url');
            $table->string('post_type', 100);
            $table->unsignedBigInteger('wordpress_post_id');

            $table->char('identity_hash', 64)->unique();

            $table->unique(
                'exhibitor_id',
                'wp_exhibitor_links_exhibitor_unique'
            );

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wordpress_exhibitor_links');
    }
};