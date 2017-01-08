<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class VkUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vk_users', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('vk_user_id')->nullable();
            $table->string('email')->nullable();
            $table->integer('linkedin_id')->nullable();
            $table->integer('confirmed_linkedin')->nullable();
            $table->longText('details')->nullable();

            $table->index('vk_user_id');
            $table->index('linkedin_id');
            $table->index('confirmed_linkedin');
            $table->index('email');

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
