<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMgListSubscriber extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mg_list_subscriber', function (Blueprint $table) {
            $table->integer('mg_list_id')->unsigned()->index();
            $table->foreign('mg_list_id')->references('id')->on('mg_lists')->onDelete('cascade');
            $table->integer('mg_subscriber_id')->unsigned()->index();
            $table->foreign('mg_subscriber_id')->references('id')->on('mg_subscribers')->onDelete('cascade');
            $table->primary(['mg_list_id', 'mg_subscriber_id']);
            $table->dateTime('added');
            $table->tinyInteger('status')->index();
            $table->integer('counter')->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mg_list_subscriber');
    }
}
