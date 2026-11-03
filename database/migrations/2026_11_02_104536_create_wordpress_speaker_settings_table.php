<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('wordpress_speaker_settings', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('event_id')->index();
            $table->string('source_url', 2048);
            $table->string('post_type', 100);
            $table->string('source_year', 4);

            $table->unsignedBigInteger('feature_id');
            $table->text('role_mappings');

            $table->char('identity_hash', 64)->unique();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wordpress_speaker_settings');
    }
};