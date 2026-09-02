<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_emergency_contacts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('name');
            $table->string('relationship');
            $table->string('phone_primary');
            $table->string('phone_secondary')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');

            $table->foreign(['organization_id', 'patient_id'], 'patient_emergency_contacts_org_patient_fk')
                ->references(['organization_id', 'id'])->on('patients')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_emergency_contacts');
    }
};
