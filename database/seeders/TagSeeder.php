<?php

namespace Database\Seeders;

use App\Modules\Core\Entities\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            // Audience
            ['name' => 'For Humans', 'slug' => 'for_humans', 'category' => 'audience'],
            ['name' => 'For Animals', 'slug' => 'for_animals', 'category' => 'audience'],
            ['name' => 'For Both', 'slug' => 'for_both', 'category' => 'audience'],
            // Food state
            ['name' => 'Cooked', 'slug' => 'cooked', 'category' => 'state'],
            ['name' => 'Raw Ingredients', 'slug' => 'raw_ingredients', 'category' => 'state'],
            ['name' => 'Packaged', 'slug' => 'packaged', 'category' => 'state'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['slug' => $tag['slug']], $tag);
        }
    }
}
