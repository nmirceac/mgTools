<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMgMessageEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mg_message_events', function (Blueprint $table) {
            $table->increments('id');
            $table->tinyInteger('event')->index();
            $table->string('recipient', 96)->index();
            $table->integer('mg_message_id', false, true)->nullable()->index();
            $table->text('details');
            $table->dateTime('timestamp');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mg_message_events');
    }
}
