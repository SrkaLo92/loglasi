<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => fn (array $attributes) => Conversation::query()->find($attributes['conversation_id'])->buyer_id,
            'body' => fake()->sentence(),
        ];
    }
}
