<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HomepageCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Frizerski saloni',
                'slug' => 'frizeri',
                'title' => 'Frizerski saloni',
                'description' => 'Pronađite najbolje frizere u vašem gradu',
                'link_type' => 'search',
                'link_value' => '/saloni/frizeri',
                'is_enabled' => true,
                'display_order' => 1,
            ],
            [
                'name' => 'Kozmetičari',
                'slug' => 'kozmeticari',
                'title' => 'Kozmetički saloni',
                'description' => 'Profesionalni tretmani lica i tijela',
                'link_type' => 'search',
                'link_value' => '/saloni/kozmeticari',
                'is_enabled' => true,
                'display_order' => 2,
            ],
            [
                'name' => 'Manikir',
                'slug' => 'manikir',
                'title' => 'Manikir saloni',
                'description' => 'Gel lak i nadogradnja noktiju',
                'link_type' => 'search',
                'link_value' => '/saloni/manikir',
                'is_enabled' => true,
                'display_order' => 3,
            ],
            [
                'name' => 'Pedikir',
                'slug' => 'pedikir',
                'title' => 'Pedikir saloni',
                'description' => 'Profesionalna njega stopala',
                'link_type' => 'search',
                'link_value' => '/saloni/pedikir',
                'is_enabled' => true,
                'display_order' => 4,
            ],
            [
                'name' => 'Masaža',
                'slug' => 'masaza',
                'title' => 'Masaža i relaksacija',
                'description' => 'Opustite se uz profesionalnu masažu',
                'link_type' => 'search',
                'link_value' => '/saloni/masaza',
                'is_enabled' => true,
                'display_order' => 5,
            ],
            [
                'name' => 'Berber',
                'slug' => 'berber',
                'title' => 'Berber saloni',
                'description' => 'Muško šišanje i uređivanje brade',
                'link_type' => 'search',
                'link_value' => '/saloni/berber',
                'is_enabled' => true,
                'display_order' => 6,
            ],
        ];

        foreach ($categories as $category) {
            // Check if category exists
            $exists = DB::table('homepage_categories')
                ->where('slug', $category['slug'])
                ->exists();

            if ($exists) {
                // Update using raw SQL with explicit boolean
                DB::statement(
                    "UPDATE homepage_categories
                     SET name = ?, title = ?, description = ?, link_type = ?, link_value = ?,
                         is_enabled = ?, display_order = ?, updated_at = NOW()
                     WHERE slug = ?",
                    [
                        $category['name'],
                        $category['title'],
                        $category['description'],
                        $category['link_type'],
                        $category['link_value'],
                        $category['is_enabled'] ? 'true' : 'false',
                        $category['display_order'],
                        $category['slug'],
                    ]
                );
            } else {
                // Insert using raw SQL with explicit boolean
                DB::statement(
                    "INSERT INTO homepage_categories
                     (slug, name, title, description, link_type, link_value, is_enabled, display_order, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [
                        $category['slug'],
                        $category['name'],
                        $category['title'],
                        $category['description'],
                        $category['link_type'],
                        $category['link_value'],
                        $category['is_enabled'] ? 'true' : 'false',
                        $category['display_order'],
                    ]
                );
            }
        }

        $this->command->info('Homepage categories seeded successfully!');
    }
}
