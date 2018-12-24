<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Messages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (Schema::hasTable('messages')){
            return;
        }

        Schema::create('messages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->integer("in");
            $table->longText('message')->nullable();
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

        if (!Schema::hasTable('messages')){
            return;
        }

        Schema::drop('messages');
    }
}
