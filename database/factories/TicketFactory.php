<?php

namespace Database\Factories;

use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'TKT-'.fake()->unique()->numerify('9#####'),
            'subject' => rtrim(fake()->sentence(6), '.'),
            'description' => fake()->paragraphs(2, true),
            'requester_id' => User::factory(),
            'priority_id' => fn () => Priority::where('is_default', true)->value('id') ?? Priority::factory(),
            'status_id' => fn () => Status::where('is_default', true)->value('id') ?? Status::factory()->state(['is_default' => true]),
            'source' => 'web',
            'due_response_at' => now()->addHours(4),
            'due_resolution_at' => now()->addDay(),
        ];
    }
}
