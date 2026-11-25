<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                ActivityLog::ACTION_CREATED,
                ActivityLog::ACTION_UPDATED,
                ActivityLog::ACTION_DELETED,
                ActivityLog::ACTION_LOGIN,
                ActivityLog::ACTION_LOGOUT,
            ]),
            'module' => fake()->randomElement(['sale', 'purchase', 'laptop', 'customer']),
            'description' => fake()->sentence(4),
            'ip' => fake()->ipv4(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
