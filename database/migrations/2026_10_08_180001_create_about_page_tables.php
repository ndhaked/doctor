<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Small icon cards in the "Expertise Services" slider on the About Doctor page
        Schema::create('about_expertise', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image')->nullable(); // relative to /public
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The round photos in "Our Main Treatments" on the About Doctor page
        Schema::create('about_treatments', function (Blueprint $table) {
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
        Schema::dropIfExists('about_treatments');
        Schema::dropIfExists('about_expertise');
    }
};
