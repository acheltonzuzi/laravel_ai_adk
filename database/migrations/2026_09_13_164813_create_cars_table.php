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
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('license_plate', 16)->unique();
            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('condition', 20);
            $table->string('location')->nullable();
            $table->string('availability', 20);
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index('brand');
            $table->index('condition');
            $table->index('availability');
            $table->index('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
