<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class IstackSeeder extends Seeder
{
    public function run(): void
    {
        // ── Usuario iStack ────────────────────────────────────────
        $istackUser = User::firstOrCreate(
            ['email' => 'admin@istack.com'],
            [
                'name'     => 'iStack Admin',
                'password' => Hash::make('istack123'),
                'role'     => 'store_owner',
                'phone'    => '+51999888777',
            ]
        );

        if (!$istackUser->store) {
            $store = Store::create([
                'user_id'       => $istackUser->id,
                'name'          => 'iStack',
                'slug'          => 'istack',
                'description'   => 'Especialistas en equipos Apple y tecnología de alta gama de segunda mano. Reacondicionados premium con garantía.',
                'tagline'       => 'Tecnología Premium, Precio Inteligente',
                'category'      => 'tecnologia',
                'template_name' => 'elegant-refurbished',
                'accent_color'  => '#00F0FF', // Neon Cyan
                'secondary_color' => '#1C1C1E', // Dark Grey
                'whatsapp_phone'=> '51999888777',
                'status'        => 'active',
                'plan'          => 'enterprise',
                'plan_expires_at' => now()->addYears(5),
                'is_featured'   => true,
                'city'          => 'Lima',
                'country'       => 'PE',
            ]);

            // Categorías
            $cats = [
                ['name' => 'iPhone', 'slug' => 'iphone', 'icon' => '📱', 'color' => '#00F0FF'],
                ['name' => 'MacBook', 'slug' => 'macbook', 'icon' => '💻', 'color' => '#00F0FF'],
                ['name' => 'iPad', 'slug' => 'ipad', 'icon' => '📝', 'color' => '#00F0FF'],
                ['name' => 'Audio JBL', 'slug' => 'audio-jbl', 'icon' => '🎧', 'color' => '#00F0FF'],
            ];

            foreach ($cats as $i => $cat) {
                $store->categories()->create(array_merge($cat, ['sort_order' => $i, 'store_id' => $store->id]));
            }

            // Productos
            $products = [
                ['name' => 'iPhone 15 Pro Max 256GB - Titanio Natural (Como Nuevo)', 'price' => 4500.00, 'stock' => 3, 'category_slug' => 'iphone'],
                ['name' => 'iPhone 14 Pro 128GB - Morado Oscuro', 'price' => 3200.00, 'stock' => 5, 'category_slug' => 'iphone'],
                ['name' => 'iPhone 13 128GB - Medianoche', 'price' => 2100.00, 'stock' => 8, 'category_slug' => 'iphone'],
                
                ['name' => 'MacBook Pro M2 14" 512GB - Gris Espacial', 'price' => 6800.00, 'stock' => 2, 'category_slug' => 'macbook'],
                ['name' => 'MacBook Air M1 256GB - Plata', 'price' => 3100.00, 'stock' => 4, 'category_slug' => 'macbook'],
                
                ['name' => 'iPad Air (5ta Gen) Wi-Fi 64GB - Azul', 'price' => 2200.00, 'stock' => 6, 'category_slug' => 'ipad'],
                ['name' => 'iPad Pro 11" M2 128GB - Gris Espacial', 'price' => 3500.00, 'stock' => 3, 'category_slug' => 'ipad'],
                
                ['name' => 'JBL Flip 6 - Altavoz Portátil Bluetooth', 'price' => 450.00, 'stock' => 10, 'category_slug' => 'audio-jbl'],
                ['name' => 'JBL Charge 5 - Altavoz Impermeable', 'price' => 650.00, 'stock' => 8, 'category_slug' => 'audio-jbl'],
            ];

            foreach ($products as $i => $product) {
                // Find category id
                $catId = $store->categories()->where('slug', $product['category_slug'])->first()->id ?? null;
                
                $store->products()->create([
                    'name'         => $product['name'],
                    'slug'         => Str::slug($product['name']),
                    'price'        => $product['price'],
                    'stock'        => $product['stock'],
                    'track_stock'  => true,
                    'is_active'    => true,
                    'is_featured'  => $i < 4, // First 4 are featured
                    'sort_order'   => $i,
                    'store_id'     => $store->id,
                    'category_id'  => $catId
                ]);
            }
        }
        
        $this->command->info('✅ iStack store and admin user created successfully.');
    }
}
