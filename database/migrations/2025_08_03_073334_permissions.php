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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name_en')->comment("Name of the permission");
            $table->string('name_ar')->comment("Localized name of the permission");
            $table->string('description_en')->nullable()->comment("Description of the permission EN");
            $table->string('description_ar')->nullable()->comment("Description of the permission AR");
            $table->boolean('active')->default(true)->comment("0 => InActive 1 => Active");
            $table->comment("Table to store permissions for users and roles");
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
