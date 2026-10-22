<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'slug'         => Str::slug($title),
            'title'        => Str::title($title),
            'date'         => fake()->date('Y-m-d'),
            'status'       => fake()->randomElement(['completed', 'in_progress']),
            'desc'         => fake()->sentence(),
            'gallery'      => [],
            'technologies' => fake()->randomElements(['Laravel', 'Nuxt', 'Vue', 'PostgreSQL'], 3),
        ];
    }
}
