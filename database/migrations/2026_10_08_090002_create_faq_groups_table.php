<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_groups', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // Where the group is shown: the FAQs page (tabs) or the FAQ block on the home page
            $table->string('placement', 20)->default('faq_page'); // faq_page | home
            $table->string('icon')->nullable(); // relative to /public
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('faq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_group_id')->constrained('faq_groups')->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
        Schema::dropIfExists('faq_groups');
    }
};
