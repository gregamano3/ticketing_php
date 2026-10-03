<?php

namespace Database\Factories;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KbArticle>
 */
class KbArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kb_category_id' => KbCategory::factory(),
            'author_id' => User::factory(),
            'title' => rtrim(fake()->unique()->sentence(5), '.'),
            'excerpt' => fake()->sentence(),
            'body' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'is_published' => true,
        ];
    }
}
