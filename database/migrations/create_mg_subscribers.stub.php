<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMgSubscribers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mg_subscribers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 96)->unique();
            $table->string('firstname', 64)->index()->default('');
            $table->string('lastname', 64)->index()->default('');
            $table->tinyInteger('state')->index()->default(0);
            $table->text('details');
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
        Schema::dropIfExists('mg_subscribers');
    }
}
