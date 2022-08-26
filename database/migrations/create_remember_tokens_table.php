<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRememberTokensTable extends Migration
{
    /**
     * 
     */
    public function up()
    {
        Schema::create('remember_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token');
            $table->string('session_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['token', 'user_id']);
        });
    }

    /**
     * 
     */
    public function down()
    {
        Schema::dropIfExists('remember_tokens');
    }
}
