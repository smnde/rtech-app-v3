<?php

namespace Database\Factories;

use App\Models\Promo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promo>
 */
class PromoFactory extends Factory
{
    protected $model = Promo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Promo '.fake()->words(2, true),
            'type' => Promo::TYPE_NOMINAL,
            'value' => fake()->randomElement([50000, 100000, 300000]),
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDays(7)->toDateString(),
            'is_active' => true,
            'item_types' => ['laptop', 'gadget'],
        ];
    }

    /**
     * Promo persen dari harga per unit.
     */
    public function percent(float $value = 10): static
    {
        return $this->state(fn () => [
            'type' => Promo::TYPE_PERCENT,
            'value' => $value,
        ]);
    }
}
