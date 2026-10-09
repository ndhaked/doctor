<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pictures that rotate in the middle column of the "Book your appointment" section
        Schema::create('booking_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image'); // relative to /public
            $table->string('alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_slides');
    }
};
