<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SiteBenefit;
use App\Models\SiteFaq;
use App\Models\SiteGalleryItem;
use App\Models\SiteProfessional;
use App\Models\SiteService;
use App\Models\SiteSetting;
use App\Models\SiteTestimonial;
use App\Support\Site\LandingSections;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Modelo fictício de landing page ("Clínica Essenza") para demonstrar a
 * estrutura da página pública em ambiente local. Bloqueado em produção.
 * Idempotente por coleção: nunca duplica itens já existentes, e só toca no
 * conteúdo do SiteSetting se ele ainda estiver com os valores padrão do
 * SiteSettingSeeder (nunca sobrescreve edições reais de um administrador).
 */
class LandingContentDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('LandingContentDemoSeeder bloqueado em produção.');

            return;
        }

        $this->seedSiteSetting();
        $this->seedBenefits();
        $this->seedServices();
        $this->seedProfessionals();
        $this->seedGallery();
        $this->seedTestimonials();
        $this->seedFaqs();
    }

    private function seedSiteSetting(): void
    {
        $siteSetting = SiteSetting::query()->first();

        // Só substitui o conteúdo se ainda for exatamente o placeholder
        // genérico criado pelo SiteSettingSeeder — nunca sobrescreve uma
        // edição real feita por um administrador.
        if ($siteSetting && $siteSetting->title !== 'Gestão de Clínicas') {
            return;
        }

        $record = $siteSetting ?? new SiteSetting;
        $record->fill([
            'title' => 'Clínica Essenza',
            'description' => 'Cuidado, saúde e autoestima em cada detalhe.',
            'hero_image_path' => $this->placeholderImage('Clínica Essenza'),
            'primary_color' => '#0F766E',
            'secondary_color' => '#F59E0B',
            'cta_text' => 'Agendar uma avaliação',
            'cta_url' => 'https://wa.me/554732221122',
            'about_text' => 'A Clínica Essenza nasceu para oferecer uma experiência completa de cuidado, unindo saúde, estética, tecnologia e atendimento humanizado em um ambiente acolhedor.',
            'footer_text' => null,
            'is_published' => true,
            'sections_config' => LandingSections::normalize(null),
        ])->save();
    }

    private function seedBenefits(): void
    {
        if (SiteBenefit::query()->count() > 0) {
            return;
        }

        $items = [
            ['icon' => 'heart-handshake', 'title' => 'Atendimento humanizado', 'description' => 'Cada pessoa é acompanhada de forma próxima e individual, do primeiro contato ao pós-tratamento.'],
            ['icon' => 'graduation-cap', 'title' => 'Equipe especializada', 'description' => 'Profissionais qualificados e em constante atualização nas áreas de saúde e estética.'],
            ['icon' => 'shield-check', 'title' => 'Tecnologia e segurança', 'description' => 'Equipamentos modernos e protocolos seguros em todos os procedimentos.'],
            ['icon' => 'sparkles', 'title' => 'Planos personalizados', 'description' => 'Tratamentos pensados para os objetivos e o momento de vida de cada paciente.'],
        ];

        foreach ($items as $order => $item) {
            SiteBenefit::query()->create([...$item, 'order' => $order, 'is_active' => true]);
        }
    }

    private function seedServices(): void
    {
        if (SiteService::query()->count() > 0) {
            return;
        }

        $items = [
            ['name' => 'Avaliação estética', 'short_description' => 'Diagnóstico completo para indicar o melhor tratamento.', 'category' => 'Estética', 'duration_minutes' => 40, 'starting_price_cents' => null],
            ['name' => 'Limpeza de pele', 'short_description' => 'Limpeza profunda com extração e hidratação.', 'category' => 'Estética', 'duration_minutes' => 60, 'starting_price_cents' => 12000],
            ['name' => 'Harmonização facial', 'short_description' => 'Procedimentos para equilíbrio e naturalidade dos traços.', 'category' => 'Estética', 'duration_minutes' => 50, 'starting_price_cents' => 45000],
            ['name' => 'Tratamentos corporais', 'short_description' => 'Protocolos para firmeza, contorno e bem-estar.', 'category' => 'Estética', 'duration_minutes' => 60, 'starting_price_cents' => 18000],
            ['name' => 'Fisioterapia', 'short_description' => 'Reabilitação e prevenção de lesões com acompanhamento individual.', 'category' => 'Saúde', 'duration_minutes' => 50, 'starting_price_cents' => 15000],
            ['name' => 'Nutrição', 'short_description' => 'Acompanhamento nutricional personalizado.', 'category' => 'Saúde', 'duration_minutes' => 45, 'starting_price_cents' => 20000],
            ['name' => 'Dermatologia', 'short_description' => 'Consultas e tratamentos dermatológicos clínicos e estéticos.', 'category' => 'Saúde', 'duration_minutes' => 30, 'starting_price_cents' => 25000],
            ['name' => 'Terapias integradas', 'short_description' => 'Abordagens complementares para saúde e bem-estar.', 'category' => 'Bem-estar', 'duration_minutes' => 60, 'starting_price_cents' => 16000],
        ];

        foreach ($items as $order => $item) {
            SiteService::query()->create([
                ...$item,
                'description' => $item['short_description'],
                'image_path' => $this->placeholderImage($item['name']),
                'cta_text' => 'Agendar',
                'is_featured' => $order < 2,
                'order' => $order,
                'is_active' => true,
            ]);
        }
    }

    private function seedProfessionals(): void
    {
        if (SiteProfessional::query()->count() > 0) {
            return;
        }

        $items = [
            ['name' => 'Dra. Camila Andrade', 'role_title' => 'Dermatologista', 'specialty' => 'Dermatologia clínica e estética', 'professional_register' => 'CRM/SC 12345', 'bio' => 'Mais de 10 anos de experiência em saúde e estética da pele.'],
            ['name' => 'Dr. Rafael Souza', 'role_title' => 'Fisioterapeuta', 'specialty' => 'Reabilitação e performance', 'professional_register' => 'CREFITO 6789', 'bio' => 'Atendimento personalizado para recuperação e prevenção de lesões.'],
            ['name' => 'Marina Torres', 'role_title' => 'Esteticista', 'specialty' => 'Tratamentos faciais e corporais', 'professional_register' => null, 'bio' => 'Especialista em protocolos de harmonização e cuidados com a pele.'],
        ];

        foreach ($items as $order => $item) {
            SiteProfessional::query()->create([
                ...$item,
                'photo_path' => $this->placeholderImage($item['name']),
                'order' => $order,
                'is_active' => true,
            ]);
        }
    }

    private function seedGallery(): void
    {
        if (SiteGalleryItem::query()->count() > 0) {
            return;
        }

        $items = [
            ['caption' => 'Recepção', 'category' => 'Estrutura', 'is_cover' => true],
            ['caption' => 'Sala de atendimento', 'category' => 'Estrutura', 'is_cover' => false],
            ['caption' => 'Equipe Essenza', 'category' => 'Equipe', 'is_cover' => false],
            ['caption' => 'Equipamentos', 'category' => 'Equipamentos', 'is_cover' => false],
        ];

        foreach ($items as $order => $item) {
            SiteGalleryItem::query()->create([
                ...$item,
                'image_path' => $this->placeholderImage($item['caption']),
                'alt_text' => $item['caption'],
                'order' => $order,
                'is_active' => true,
            ]);
        }
    }

    private function seedTestimonials(): void
    {
        if (SiteTestimonial::query()->count() > 0) {
            return;
        }

        // Depoimentos ilustrativos para demonstração — substitua por
        // avaliações reais de pacientes antes de publicar em produção.
        $items = [
            ['author_name' => 'Juliana R. (depoimento ilustrativo)', 'rating' => 5, 'content' => 'Atendimento excelente, me senti muito bem cuidada do início ao fim.'],
            ['author_name' => 'Marcos T. (depoimento ilustrativo)', 'rating' => 5, 'content' => 'Profissionais atenciosos e ambiente muito agradável. Recomendo!'],
            ['author_name' => 'Beatriz L. (depoimento ilustrativo)', 'rating' => 4, 'content' => 'Resultado ótimo no tratamento e equipe muito qualificada.'],
        ];

        foreach ($items as $order => $item) {
            SiteTestimonial::query()->create([
                ...$item,
                'is_featured' => $order === 0,
                'order' => $order,
                'is_active' => true,
            ]);
        }
    }

    private function seedFaqs(): void
    {
        if (SiteFaq::query()->count() > 0) {
            return;
        }

        $items = [
            ['question' => 'Como faço para agendar uma consulta?', 'answer' => 'Você pode agendar diretamente pelo formulário desta página, ou entrar em contato pelo telefone/WhatsApp informado na seção de contato.'],
            ['question' => 'Quais formas de pagamento são aceitas?', 'answer' => 'Aceitamos cartão de crédito, débito e Pix. Consulte condições especiais com a recepção.'],
            ['question' => 'A clínica atende convênios?', 'answer' => 'Consulte a recepção para verificar a disponibilidade de convênios para o seu tratamento.'],
            ['question' => 'Preciso de encaminhamento médico?', 'answer' => 'Para a maioria dos tratamentos estéticos não é necessário. Para procedimentos de saúde específicos, nossa equipe orienta durante a avaliação inicial.'],
        ];

        foreach ($items as $order => $item) {
            SiteFaq::query()->create([...$item, 'order' => $order, 'is_active' => true]);
        }
    }

    private function placeholderImage(string $label): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600">'
            .'<rect width="100%" height="100%" fill="#0F766E"/>'
            .'<text x="50%" y="50%" font-family="sans-serif" font-size="32" fill="#ffffff" text-anchor="middle" dominant-baseline="middle">'
            .e($label)
            .'</text></svg>';

        $path = 'demo/'.Str::slug($label).'-'.Str::random(6).'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
