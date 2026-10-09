<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();

            // Image paths are relative to /public (e.g. "images/slider-1.webp" or "uploads/slides/xyz.jpg")
            $table->string('image');
            $table->string('doctor_image')->nullable();
            $table->string('doctor_name')->nullable();

            $table->string('badge')->nullable();
            $table->string('title');
            $table->string('credentials')->nullable();
            $table->string('specialty')->nullable();
            $table->text('description')->nullable();

            // Quick cards under the text: [{"icon": "droplet", "title": "Diabetes"}, ...]
            $table->json('cards')->nullable();

            $table->string('cta_text')->nullable();
            $table->string('cta_action', 20)->default('appointment'); // appointment | contact | custom
            $table->string('cta_url')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
