<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Enums\SchemaBusinessType;
use App\Models\Address;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Models\UnitOpeningHour;

/**
 * Monta o JSON-LD (schema.org LocalBusiness ou especialização) a partir
 * de dados reais já cadastrados — nunca inventa avaliações, preços,
 * coordenadas ou horários. Campos sem dado real são omitidos.
 */
final class LocalBusinessJsonLd
{
    public function __construct(private readonly CanonicalUrlResolver $canonicalUrlResolver) {}

    /** @return array<string, mixed>|null */
    public function build(Organization $organization, ?SiteSetting $siteSetting): ?array
    {
        $headquarters = $organization->headquarters()->with(['address', 'openingHours'])->first();
        $legalEntity = $organization->primaryLegalEntity()->first();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => $siteSetting?->schema_type->value ?? SchemaBusinessType::LocalBusiness->value,
            'name' => $organization->name,
            'url' => $this->canonicalUrlResolver->resolve($siteSetting, '/'),
        ];

        if (filled($legalEntity?->trade_name)) {
            $data['alternateName'] = $legalEntity->trade_name;
        }

        $description = $siteSetting?->meta_description ?: $siteSetting?->description;
        if (filled($description)) {
            $data['description'] = $description;
        }

        $telephone = $headquarters?->phone ?: ($headquarters?->whatsapp ?: $legalEntity?->phone);
        if (filled($telephone)) {
            $data['telephone'] = $telephone;
        }

        $email = $headquarters?->email ?: $legalEntity?->email;
        if (filled($email)) {
            $data['email'] = $email;
        }

        if ($headquarters?->address) {
            $data['address'] = $this->buildAddress($headquarters->address);
        }

        if ($siteSetting && $siteSetting->latitude !== null && $siteSetting->longitude !== null) {
            $data['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $siteSetting->latitude,
                'longitude' => (float) $siteSetting->longitude,
            ];
        }

        $openingHours = $headquarters
            ? $this->buildOpeningHours($headquarters->openingHours)
            : [];
        if ($openingHours !== []) {
            $data['openingHoursSpecification'] = $openingHours;
        }

        $sameAs = array_values(array_filter([
            $siteSetting?->google_business_profile_url,
            $siteSetting?->google_reviews_url,
            $siteSetting?->facebook_url,
            $siteSetting?->instagram_url,
            $siteSetting?->linkedin_url,
        ], fn (?string $url) => filled($url)));
        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        if (filled($siteSetting?->google_maps_url)) {
            $data['hasMap'] = $siteSetting->google_maps_url;
        }

        // Sem endereço nem telefone real, o JSON-LD não agrega valor —
        // melhor omitir do que publicar um LocalBusiness quase vazio.
        if (! isset($data['address']) && ! isset($data['telephone'])) {
            return null;
        }

        return $data;
    }

    /** @return array<string, string> */
    private function buildAddress(Address $address): array
    {
        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => trim("{$address->street}, {$address->number}", ', '),
            'addressLocality' => $address->city,
            'addressRegion' => $address->state,
            'postalCode' => $address->postal_code,
            'addressCountry' => $address->country ?: 'BR',
        ], fn (?string $value) => filled($value));
    }

    /**
     * @param  iterable<UnitOpeningHour>  $openingHours
     * @return list<array<string, string>>
     */
    private function buildOpeningHours(iterable $openingHours): array
    {
        $specs = [];

        foreach ($openingHours as $hour) {
            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/'.$this->dayOfWeekName($hour->day_of_week),
                'opens' => substr((string) $hour->opens_at, 0, 5),
                'closes' => substr((string) $hour->closes_at, 0, 5),
            ];
        }

        return $specs;
    }

    private function dayOfWeekName(int $day): string
    {
        return match ($day) {
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            default => 'Monday',
        };
    }
}
