<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('location')->nullable();
            $table->string('service_name')->nullable();
            $table->text('summary');
            $table->date('completed_at')->nullable();
            $table->string('duration_label')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('before_image_path')->nullable();
            $table->string('after_image_path')->nullable();
            $table->text('client_quote')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'is_featured']);
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
