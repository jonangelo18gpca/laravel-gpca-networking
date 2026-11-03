<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('wordpress_speaker_links', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('speaker_id')->unique();

            $table->string('source_url', 2048);
            $table->string('post_type', 100);
            $table->unsignedBigInteger('wordpress_post_id');

            $table->char('identity_hash', 64)->unique();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wordpress_speaker_links');
    }
};