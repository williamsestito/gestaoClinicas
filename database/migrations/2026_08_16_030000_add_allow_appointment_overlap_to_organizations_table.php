<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Encaixe" configurável (Etapa 3.3 do roadmap) — toggle tudo-ou-nada
     * por organização. Quando true, App\Support\Availability\AppointmentOverlapGuard::assertNoConflict()
     * deixa de bloquear sobreposição de agenda do profissional (mas nunca
     * de recurso — ver App\Support\Availability\ResourceOverlapGuard, que
     * não lê este campo).
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('allow_appointment_overlap')->default(false)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('allow_appointment_overlap');
        });
    }
};
