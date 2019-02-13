<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMgCampaignList extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mg_campaign_list', function (Blueprint $table) {
            $table->integer('mg_list_id')->unsigned()->index();
            $table->foreign('mg_list_id')->references('id')->on('mg_lists')->onDelete('cascade');
            $table->integer('mg_campaign_id')->unsigned()->index();
            $table->foreign('mg_campaign_id')->references('id')->on('mg_campaigns')->onDelete('cascade');
            $table->primary(['mg_list_id', 'mg_campaign_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mg_campaign_list');
    }
}
