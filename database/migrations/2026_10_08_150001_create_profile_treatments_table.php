<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The round-icon grid inside the "doctor card" block on the home page
        Schema::create('profile_treatments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image')->nullable(); // relative to /public
            $table->string('icon', 40)->nullable(); // <x-icon> name, used when there is no image
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_treatments');
    }
};
