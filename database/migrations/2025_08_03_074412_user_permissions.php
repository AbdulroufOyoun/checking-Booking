<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment("Foreign key to users table");
            $table->unsignedBigInteger('permission_id')->comment("Foreign key to permissions table");
            $table->timestamps();
            $table->unique(['user_id', 'permission_id'], 'unique_user_permission');
            $table->comment("Table to store user permissions for various actions");

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
