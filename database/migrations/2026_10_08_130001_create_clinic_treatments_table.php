<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The small icon cards in the "About the clinic" block on the home page
        Schema::create('clinic_treatments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image')->nullable(); // relative to /public
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_treatments');
    }
};
