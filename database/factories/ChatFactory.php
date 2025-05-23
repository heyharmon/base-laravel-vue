<?php

namespace Database\Factories;

use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chat>
 */
class ChatFactory extends Factory
{
    protected $model = Chat::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => $this->faker->randomElement(['user', 'assistant', 'system']),
            'content' => $this->faker->paragraph(),
            'metadata' => null,
        ];
    }
}
