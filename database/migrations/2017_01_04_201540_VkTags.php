<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class VkTags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vk_tags', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('vk_user_id');
            $table->string('tag');

            $table->index('vk_user_id');
            $table->index('tag');

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
