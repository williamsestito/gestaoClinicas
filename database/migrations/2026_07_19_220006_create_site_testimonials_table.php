<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->string('author_photo_path')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('content');
            $table->foreignId('related_service_id')->nullable()
                ->constrained('site_services')->nullOnDelete();
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'order']);
        });

        DB::statement(
            'ALTER TABLE site_testimonials ADD CONSTRAINT site_testimonials_rating_range
             CHECK (rating IS NULL OR rating BETWEEN 1 AND 5)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('site_testimonials');
    }
};
