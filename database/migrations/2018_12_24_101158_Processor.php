<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Processor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('processor')){
            return;
        }

        Schema::create('processor', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->integer("status");
            $table->longText('message')->nullable();
            $table->longText('reponse')->nullable();
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
        //
    }
}
