<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->boolean('is_manager')->default(false);
            $table->timestamps();

            $table->unique(['organization_membership_id', 'unit_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_memberships');
    }
};
