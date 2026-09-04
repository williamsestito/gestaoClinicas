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
 * Modelo fictício de landing page ("Espaço Duda Almeida — Saúde e Beleza",
 * uma clínica de podologia) para demonstrar a estrutura da página pública
 * em ambiente local. Bloqueado em produção. Idempotente por coleção: nunca
 * duplica itens já existentes, e só toca no conteúdo do SiteSetting se ele
 * ainda estiver com um dos títulos padrão gerados por seeder (nunca
 * sobrescreve edições reais de um administrador).
 */
class LandingContentDemoSeeder extends Seeder
{
    /** Títulos considerados "ainda no padrão de demonstração" — nunca um título editado por um administrador real. */
    private const DEFAULT_TITLES = ['Gestão de Clínicas', 'Clínica Essenza'];

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
        $siteSettings = SiteSetting::query()->orderBy('id')->get();
        $siteSetting = $siteSettings->first();

        // Só substitui o conteúdo se ainda for um dos placeholders gerados
        // por seeder — nunca sobrescreve uma edição real feita por um
        // administrador.
        if ($siteSetting && ! in_array($siteSetting->title, self::DEFAULT_TITLES, true)) {
            return;
        }

        // A tabela deveria ter no máximo um registro (é um singleton de
        // configuração); remove eventuais duplicatas de teste antes de
        // gravar o conteúdo de demonstração.
        $siteSettings->skip(1)->each(fn (SiteSetting $extra) => $extra->delete());

        $record = $siteSetting ?? new SiteSetting;
        $record->fill([
            'title' => 'Espaço Duda Almeida',
            'description' => 'Cuidar dos seus pés é cuidar da sua qualidade de vida.',
            'hero_image_path' => $this->placeholderImage('Espaço Duda Almeida'),
            'primary_color' => '#B08D3E',
            'secondary_color' => '#3E2C1C',
            'cta_text' => 'Agende sua avaliação',
            'cta_url' => 'https://wa.me/554799999999',
            'about_text' => 'Mais do que um cuidado estético, é sobre bem-estar e autoestima em cada detalhe. Tratamentos especializados, tecnologia e um atendimento humanizado para você se sentir bem em cada passo.',
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
            ['icon' => 'heart-handshake', 'title' => 'Atendimento humanizado', 'description' => 'Cada paciente é acompanhado de forma próxima e individual, do diagnóstico ao pós-tratamento.'],
            ['icon' => 'graduation-cap', 'title' => 'Equipe especializada', 'description' => 'Podólogas, ortopedista e dermatologista dedicados à saúde e à estética dos seus pés.'],
            ['icon' => 'sparkles', 'title' => 'Tecnologia de ponta', 'description' => 'Equipamentos modernos para tratamentos a laser, unhas encravadas e micoses.'],
            ['icon' => 'shield-check', 'title' => 'Ambiente acolhedor', 'description' => 'Um espaço pensado para o seu bem-estar em cada etapa do atendimento.'],
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
            ['name' => 'Podologia clínica', 'short_description' => 'Avaliação e cuidados completos para a saúde dos seus pés.', 'category' => 'Podologia', 'duration_minutes' => 50, 'starting_price_cents' => 12000],
            ['name' => 'Tratamento a laser', 'short_description' => 'Tecnologia a laser para tratamento de micoses e unhas.', 'category' => 'Podologia', 'duration_minutes' => 40, 'starting_price_cents' => 18000],
            ['name' => 'Unha encravada', 'short_description' => 'Tratamento especializado para unhas encravadas, sem dor.', 'category' => 'Podologia', 'duration_minutes' => 45, 'starting_price_cents' => 15000],
            ['name' => 'Tratamento de micoses', 'short_description' => 'Diagnóstico e tratamento de micoses de pele e unha.', 'category' => 'Podologia', 'duration_minutes' => 40, 'starting_price_cents' => 16000],
            ['name' => 'Prevenção e bem-estar', 'short_description' => 'Cuidados preventivos para manter seus pés sempre saudáveis.', 'category' => 'Bem-estar', 'duration_minutes' => 50, 'starting_price_cents' => 11000],
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
            ['name' => 'Duda Almeida', 'role_title' => 'Podóloga especialista', 'specialty' => 'Podologia clínica e tratamentos a laser', 'professional_register' => null, 'bio' => 'Especialista em podologia, dedicada a unir cuidado técnico e bem-estar em cada atendimento.'],
            ['name' => 'Fernanda', 'role_title' => 'Podóloga', 'specialty' => 'Podologia clínica', 'professional_register' => null, 'bio' => 'Cuidado próximo e humanizado para a saúde dos seus pés.'],
            ['name' => 'Dr. Rafael Martins', 'role_title' => 'Ortopedista', 'specialty' => 'Especialista em pés', 'professional_register' => 'CRM/SC 45678', 'bio' => 'Acompanhamento ortopédico especializado para a saúde e o movimento dos pés.'],
            ['name' => 'Dra. Juliana Costa', 'role_title' => 'Dermatologista', 'specialty' => 'Dermatologia e estética', 'professional_register' => 'CRM/SC 78912', 'bio' => 'Tratamentos dermatológicos e estéticos com foco na saúde da pele dos pés.'],
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
            ['caption' => 'Equipe Espaço Duda Almeida', 'category' => 'Equipe', 'is_cover' => false],
            ['caption' => 'Equipamentos de podologia', 'category' => 'Equipamentos', 'is_cover' => false],
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
            ['author_name' => 'Marcos T. (depoimento ilustrativo)', 'rating' => 5, 'content' => 'Tratei uma unha encravada que me incomodava há meses. Equipe cuidadosa e resultado excelente!'],
            ['author_name' => 'Renata S. (depoimento ilustrativo)', 'rating' => 5, 'content' => 'Atendimento humanizado, ambiente agradável e muito profissionalismo da Duda e da equipe.'],
            ['author_name' => 'Carlos A. (depoimento ilustrativo)', 'rating' => 4, 'content' => 'Fiz o tratamento a laser para micose e já vejo resultado. Recomendo o Espaço Duda Almeida.'],
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
            ['question' => 'Preciso de indicação médica para fazer podologia?', 'answer' => 'Não, você pode agendar uma avaliação diretamente. Para casos que exigem acompanhamento ortopédico ou dermatológico, nossa equipe orienta durante a consulta.'],
            ['question' => 'Quais formas de pagamento são aceitas?', 'answer' => 'Aceitamos cartão de crédito, débito e Pix.'],
            ['question' => 'O tratamento a laser dói?', 'answer' => 'É um procedimento pouco invasivo e bem tolerado pela maioria dos pacientes. Nossa equipe explica todo o processo antes de iniciar.'],
            ['question' => 'Com que frequência devo fazer podologia preventiva?', 'answer' => 'Recomendamos avaliações periódicas, geralmente a cada 4 a 6 semanas, ajustadas conforme a necessidade de cada paciente.'],
        ];

        foreach ($items as $order => $item) {
            SiteFaq::query()->create([...$item, 'order' => $order, 'is_active' => true]);
        }
    }

    private function placeholderImage(string $label): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600">'
            .'<rect width="100%" height="100%" fill="#B08D3E"/>'
            .'<text x="50%" y="50%" font-family="sans-serif" font-size="32" fill="#ffffff" text-anchor="middle" dominant-baseline="middle">'
            .e($label)
            .'</text></svg>';

        $path = 'demo/'.Str::slug($label).'-'.Str::random(6).'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
