<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMgCampaigns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mg_campaigns', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 96)->index();
            $table->tinyInteger('status')->index();
            $table->integer('sent')->unsigned();
            $table->mediumText('settings');
            $table->text('details');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mg_campaigns');
    }
}
