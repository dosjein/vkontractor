<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class VkBirthData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vk_birthday', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('vk_user_id')->unique();
            $table->timestamp('birthday');

            $table->index('vk_user_id');

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
