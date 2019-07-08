<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMgMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mg_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('mg_campaign_id', false, true)->nullable()->index();
            $table->foreign('mg_campaign_id')->references('id')->on('mg_campaigns')->onDelete('cascade');
            $table->string('recipient', 96)->index();
            $table->string('mailgun_id', 96)->index();
            $table->boolean('delivered')->default(false)->index();
            $table->boolean('dropped')->default(false)->index();
            $table->boolean('bounced')->default(false)->index();
            $table->boolean('spam')->default(false)->index();
            $table->boolean('unsubscribed')->default(false)->index();
            $table->boolean('clicked')->default(false)->index();
            $table->boolean('opened')->default(false)->index();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mg_messages');
    }
}
