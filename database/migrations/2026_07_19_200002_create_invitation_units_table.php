<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_units', function (Blueprint $table) {
            $table->foreignUlid('invitation_id')->constrained('invitations')->cascadeOnDelete();
            $table->foreignUlid('unit_id')->constrained('units')->cascadeOnDelete();

            $table->primary(['invitation_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_units');
    }
};
