<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item de uma venda. `unit_price_cents`/`final_price_cents` são
     * sempre um retrato do momento da venda — nunca relidos do
     * produto/serviço depois de criados, para o histórico não mudar se o
     * preço de catálogo mudar depois. Sem soft delete: um item de venda
     * confirmada nunca é apagado (só a venda inteira muda de status).
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('item_type');
            $table->foreignUlid('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->unsignedSmallInteger('session_count')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('discount_percentage')->default(0);
            $table->unsignedInteger('final_price_cents');
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_justification')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'sale_id']);
            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'service_id'], 'sale_items_org_service_fk')
                ->references(['organization_id', 'id'])->on('services')->restrictOnDelete();
            $table->foreign(['organization_id', 'product_id'], 'sale_items_org_product_fk')
                ->references(['organization_id', 'id'])->on('products')->restrictOnDelete();
        });

        DB::statement(
            "ALTER TABLE sale_items ADD CONSTRAINT sale_items_item_type_valid
             CHECK (item_type IN ('service', 'product', 'service_package'))",
        );
        DB::statement(
            'ALTER TABLE sale_items ADD CONSTRAINT sale_items_discount_percentage_range
             CHECK (discount_percentage BETWEEN 0 AND 100)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
