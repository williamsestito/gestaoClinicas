<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `document` (CPF, dígitos apenas) é opcional no formulário público e
     * usado só para localizar um paciente já cadastrado com mais precisão
     * do que telefone/e-mail (ver App\Actions\Public\CreateAppointmentRequestAction).
     * `patient_id` guarda o resultado dessa correspondência (por CPF,
     * telefone ou e-mail) ou o vínculo direto quando quem envia já está
     * logado no portal — `nullOnDelete`: perder o paciente nunca deveria
     * apagar o histórico da solicitação.
     */
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->string('document', 14)->nullable()->after('email');
            $table->foreignUlid('patient_id')->nullable()->after('professional_id')
                ->constrained('patients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropColumn(['patient_id', 'document']);
        });
    }
};
