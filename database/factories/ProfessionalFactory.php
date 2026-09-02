<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Models\ProfessionalService;
use App\Models\ProfessionalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Professional>
 */
class ProfessionalFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'organization_id' => Organization::factory(),
            'user_id' => null,
            'name' => $name,
            'social_name' => null,
            'display_name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('###########'),
            'document' => LegalEntityFactory::validCpf(),
            'birth_date' => fake()->date(),
            'bio' => null,
            'photo_path' => null,
            'status' => RecordStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RecordStatus::Inactive]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => ['deleted_at' => now()]);
    }

    /** Vincula o profissional a um usuário existente da mesma organização — nunca concede acesso por si só. */
    public function linkedToUser(): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => User::factory()]);
    }

    /** Cria o profissional já com um registro profissional (conselho de classe) principal. */
    public function withRegistration(): static
    {
        return $this->afterCreating(function (Professional $professional) {
            ProfessionalRegistration::factory()
                ->primary()
                ->for($professional)
                ->create(['organization_id' => $professional->organization_id]);
        });
    }

    /** Cria o profissional já vinculado a uma unidade (App\Models\ProfessionalUnit) da mesma organização. */
    public function assignedToUnit(): static
    {
        return $this->afterCreating(function (Professional $professional) {
            ProfessionalUnit::factory()
                ->for($professional)
                ->create(['organization_id' => $professional->organization_id]);
        });
    }

    /** Cria o profissional já vinculado a um serviço (App\Models\ProfessionalService) da mesma organização. */
    public function assignedToService(): static
    {
        return $this->afterCreating(function (Professional $professional) {
            ProfessionalService::factory()
                ->for($professional)
                ->create(['organization_id' => $professional->organization_id]);
        });
    }
}
