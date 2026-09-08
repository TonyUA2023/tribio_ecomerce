<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Super Administrador ──────────────────────────────────
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@tribio.pe'],
            [
                'name' => 'Super Admin Tribio',
                'password' => Hash::make('Tribio2026!'),
                'role' => 'super_admin',
                'phone' => '+51902699916',
            ]
        );

        // ── Tienda de demo ────────────────────────────────────────
        $demoUser = User::firstOrCreate(
            ['email' => 'demo@tienda.com'],
            [
                'name' => 'Sandra Gómez',
                'password' => Hash::make('demo1234'),
                'role' => 'store_owner',
                'phone' => '+51987654321',
            ]
        );

        if (!$demoUser->store) {
            $store = Store::create([
                'user_id' => $demoUser->id,
                'name' => "Sandra's Cakes",
                'slug' => 'sandras-cakes',
                'description' => 'Tortas y pasteles personalizados hechos con amor',
                'tagline' => '¡El sabor que te enamora!',
                'category' => 'alimentos',
                'template_name' => 'vibrant-fresh',
                'accent_color' => '#EC4899',
                'secondary_color' => '#F59E0B',
                'whatsapp_phone' => '51987654321',
                'status' => 'active',
                'plan' => 'professional',
                'plan_expires_at' => now()->addYear(),
                'is_featured' => true,
                'city' => 'Lima',
                'country' => 'PE',
            ]);

            // Categorías demo
            $cats = [
                ['name' => 'Tortas', 'slug' => 'tortas', 'icon' => '🎂', 'color' => '#EC4899'],
                ['name' => 'Cupcakes', 'slug' => 'cupcakes', 'icon' => '🧁', 'color' => '#8B5CF6'],
                ['name' => 'Galletas', 'slug' => 'galletas', 'icon' => '🍪', 'color' => '#F59E0B'],
            ];

            foreach ($cats as $i => $cat) {
                $store->categories()->create(array_merge($cat, ['sort_order' => $i, 'store_id' => $store->id]));
            }

            // Productos demo
            $products = [
                ['name' => 'Torta de Chocolate', 'price' => 89.90, 'stock' => 5],
                ['name' => 'Torta de Vainilla', 'price' => 79.90, 'stock' => 3],
                ['name' => 'Cupcakes x12', 'price' => 45.00, 'stock' => 20],
                ['name' => 'Galletas Decoradas x6', 'price' => 25.00, 'stock' => 50],
            ];

            foreach ($products as $i => $product) {
                $store->products()->create([
                    'name' => $product['name'],
                    'slug' => Str::slug($product['name']),
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                    'track_stock' => true,
                    'is_active' => true,
                    'is_featured' => $i < 2,
                    'sort_order' => $i,
                    'store_id' => $store->id,
                ]);
            }
        }

        $this->call(TflPartsSeeder::class);

        $this->command->info('✅ Base de datos inicializada con éxito.');
        $this->command->info('   Super Admin: admin@tribio.pe / Tribio2026!');
        $this->command->info('   Demo Store:  demo@tienda.com / demo1234');
        $this->command->info('   TFL Parts:   tflparts@tribio.com / password');
    }
}
