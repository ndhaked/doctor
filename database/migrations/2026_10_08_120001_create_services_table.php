<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            // Card photo, relative to /public. When empty the card shows the icon below instead.
            $table->string('image')->nullable();
            // Icon name understood by <x-icon>; used on cards without a photo and in the header menu.
            $table->string('icon', 40)->default('droplet');
            // Where the "View Service" link goes (path like /faq or a full URL)
            $table->string('link_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
