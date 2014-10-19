<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFiles extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('files', function(Blueprint $table){

            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('hash', 40);
            $table->string('mime_type', 255); // This really long types exist
            $table->string('name', 255);
            $table->string('title', 255)->nullable();
            $table->string('file_path', 255)->unique();

            $table->integer('parent_id')->unsigned()->nullable();
            $table->tinyInteger('is_empty')->unsigned()->default(0);

            $table->index('name');
            $table->index('parent_id');
            $table->timestamps();
            $table->foreign('parent_id')->references('id')->on('files');
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('files');
	}

}
