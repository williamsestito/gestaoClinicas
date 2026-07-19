<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\IndexingPolicy;
use App\Enums\SchemaBusinessType;
use App\Models\SiteSetting;
use App\Rules\OfficialDomainRule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Página singular (não é um Resource, pois só existe um registro) para
 * administrar o conteúdo e o SEO/marketing da página pública inicial
 * ("/"): título, descrição, imagem, cores, domínio oficial, metadados,
 * verificações de busca e presença no Google Business.
 *
 * @property-read Schema $form
 */
class ManageSiteContent extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Site e SEO';

    protected static ?string $title = 'Site e SEO';

    protected string $view = 'filament.pages.manage-site-content';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getRecord()?->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Conteúdo da página inicial')
                        ->description('Exibido no hero público em "/".')
                        ->schema([
                            TextInput::make('title')
                                ->label('Título')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description')
                                ->label('Descrição')
                                ->rows(3)
                                ->maxLength(500),
                            FileUpload::make('hero_image_path')
                                ->label('Imagem de destaque (opcional)')
                                ->image()
                                ->disk('public')
                                ->directory('site-content')
                                ->visibility('public'),
                            ColorPicker::make('primary_color')
                                ->label('Cor primária'),
                            ColorPicker::make('secondary_color')
                                ->label('Cor secundária'),
                        ]),

                    Section::make('Domínio e identificação')
                        ->schema([
                            TextInput::make('official_domain')
                                ->label('Domínio oficial (opcional)')
                                ->placeholder('clinicaexemplo.com.br')
                                ->helperText('Apenas o domínio, sem "https://" — usado para a URL canônica, sitemap e compartilhamento. Em ambiente local, a URL de desenvolvimento é usada no lugar deste campo.')
                                ->nullable()
                                ->rule(new OfficialDomainRule)
                                ->maxLength(253),
                            Select::make('schema_type')
                                ->label('Tipo de negócio (dados estruturados)')
                                ->options($this->enumOptions(SchemaBusinessType::cases()))
                                ->default(SchemaBusinessType::LocalBusiness->value)
                                ->required()
                                ->helperText('Escolha apenas o tipo que corresponde de fato à clínica.'),
                        ])
                        ->columns(2),

                    Section::make('Metadados de SEO')
                        ->schema([
                            TextInput::make('meta_title')
                                ->label('Título para buscadores (opcional)')
                                ->helperText('Se vazio, usa o título da página inicial.')
                                ->maxLength(255),
                            Textarea::make('meta_description')
                                ->label('Descrição para buscadores (opcional)')
                                ->helperText('Se vazio, usa a descrição da página inicial.')
                                ->rows(2)
                                ->maxLength(320),
                            TextInput::make('og_image_alt')
                                ->label('Texto alternativo da imagem de compartilhamento')
                                ->maxLength(255),
                            Textarea::make('focus_keywords')
                                ->label('Palavras e temas estratégicos (apoio editorial)')
                                ->helperText('Não é a antiga meta tag "keywords" — serve apenas para orientar títulos e conteúdo.')
                                ->rows(2),
                            TextInput::make('author_name')
                                ->label('Autor ou organização responsável')
                                ->maxLength(255),
                            Select::make('indexing_policy')
                                ->label('Política de indexação')
                                ->options($this->enumOptions(IndexingPolicy::cases()))
                                ->default(IndexingPolicy::NoIndex->value)
                                ->required()
                                ->helperText('Em ambiente local, a indexação é sempre bloqueada, independentemente desta configuração.'),
                        ])
                        ->columns(2),

                    Section::make('Verificações de ferramentas de busca')
                        ->schema([
                            TextInput::make('google_search_console_verification')
                                ->label('Código de verificação do Google Search Console')
                                ->maxLength(255),
                            TextInput::make('bing_webmaster_verification')
                                ->label('Código de verificação do Bing Webmaster Tools')
                                ->maxLength(255),
                        ])
                        ->columns(2),

                    Section::make('Google Business e localização')
                        ->schema([
                            TextInput::make('google_business_profile_url')
                                ->label('URL do perfil no Google Business')
                                ->url()
                                ->maxLength(255),
                            TextInput::make('google_reviews_url')
                                ->label('URL de avaliações')
                                ->url()
                                ->maxLength(255),
                            TextInput::make('google_maps_url')
                                ->label('URL de "como chegar" (Google Maps)')
                                ->url()
                                ->maxLength(255),
                            TextInput::make('latitude')
                                ->label('Latitude (opcional)')
                                ->numeric()
                                ->minValue(-90)
                                ->maxValue(90),
                            TextInput::make('longitude')
                                ->label('Longitude (opcional)')
                                ->numeric()
                                ->minValue(-180)
                                ->maxValue(180),
                        ])
                        ->columns(2),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Salvar')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->record($this->getRecord())
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $record = $this->getRecord() ?? new SiteSetting;
        $record->fill($data);
        $record->save();

        Notification::make()
            ->success()
            ->title('Página inicial atualizada com sucesso.')
            ->send();
    }

    public function getRecord(): ?SiteSetting
    {
        return SiteSetting::query()->first();
    }

    /**
     * @param  array<int, IndexingPolicy|SchemaBusinessType>  $cases
     * @return array<string, string>
     */
    private function enumOptions(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
