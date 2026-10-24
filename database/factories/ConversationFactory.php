<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'buyer_id' => User::factory(),
            'seller_id' => fn (array $attributes) => Listing::query()->find($attributes['listing_id'])?->user_id ?? User::factory(),
            'last_message_at' => now(),
        ];
    }
}
