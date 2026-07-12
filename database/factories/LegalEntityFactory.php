<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LegalEntityType;
use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalEntity>
 */
class LegalEntityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => LegalEntityType::Individual,
            'document' => self::validCpf(),
            'legal_name' => fake()->name(),
            'trade_name' => null,
            'is_primary' => false,
            'status' => RecordStatus::Active,
        ];
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LegalEntityType::Company,
            'document' => self::validCnpj(),
            'legal_name' => fake()->company(),
            'trade_name' => fake()->company(),
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary' => true]);
    }

    /** Gera um CPF numericamente válido (dígitos verificadores corretos), para uso exclusivo em testes/factories. */
    public static function validCpf(): string
    {
        $digits = [];
        for ($i = 0; $i < 9; $i++) {
            $digits[] = random_int(0, 9);
        }

        for ($round = 0; $round < 2; $round++) {
            $length = 9 + $round;
            $sum = 0;
            foreach ($digits as $i => $digit) {
                if ($i >= $length) {
                    break;
                }
                $sum += $digit * (($length + 1) - $i);
            }
            $remainder = ($sum * 10) % 11;
            $digits[] = $remainder === 10 ? 0 : $remainder;
        }

        return implode('', $digits);
    }

    /** Gera um CNPJ numericamente válido, para uso exclusivo em testes/factories. */
    public static function validCnpj(): string
    {
        $digits = [];
        for ($i = 0; $i < 12; $i++) {
            $digits[] = random_int(0, 9);
        }

        $weightsFirst = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weightsSecond = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([$weightsFirst, $weightsSecond] as $round => $weights) {
            $length = 12 + $round;
            $sum = 0;
            foreach ($weights as $i => $weight) {
                if ($i >= $length) {
                    break;
                }
                $sum += $digits[$i] * $weight;
            }
            $remainder = $sum % 11;
            $digits[] = $remainder < 2 ? 0 : 11 - $remainder;
        }

        return implode('', $digits);
    }
}
