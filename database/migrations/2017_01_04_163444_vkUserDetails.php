<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class VkUserDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vk_users_details', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('vk_user_id')->unique();

            $table->longText('details')->nullable();
            $table->longText('friends_data')->nullable();

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
