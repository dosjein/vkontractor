<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class VkMatrix extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vk_matrix', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('vk_user_id');
            $table->integer('vk_related_id');
            $table->string('type');

            $table->index('vk_user_id');
            $table->index('vk_related_id');
            $table->index('type');

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
