<?php

use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = User::updateOrCreate(
    ['email' => 'ventas@llantasracing.com'],
    [
        'name'     => 'Administrador de Llantas',
        'password' => Hash::make('Llantas2026!'),
        'role'     => 'store_owner',
        'phone'    => '+51999999999',
    ]
);

$store = Store::updateOrCreate(
    ['user_id' => $user->id],
    [
        'name'          => 'Llantas Racing & Offroad',
        'slug'          => 'llantas-racing',
        'description'   => 'Llantas de alto rendimiento para autos deportivos, 4x4 y vehículos de lujo. Máximo agarre y un diseño agresivo para conquistar el asfalto.',
        'tagline'       => 'Agarre total, diseño agresivo.',
        'category'      => 'otros',
        'template_name' => 'elegant-dark',
        'accent_color'  => '#E11D48', // Aggressive Red
        'secondary_color' => '#111827', // Very Dark Grey
        'text_color'    => '#F3F4F6', // Light text
        'bg_color'      => '#030712', // Almost black
        'whatsapp_phone'=> '51999999999',
        'status'        => 'active',
        'plan'          => 'enterprise',
        'plan_expires_at' => now()->addYears(2),
        'is_featured'   => true,
        'city'          => 'Lima',
        'country'       => 'PE',
    ]
);

echo "TIENDA CREADA CON EXITO\n";
echo "========================\n";
echo "URL de la tienda: /tienda/" . $store->slug . "\n";
echo "Usuario (Email): " . $user->email . "\n";
echo "Contraseña: Llantas2026!\n";

