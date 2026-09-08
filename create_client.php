<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$email = 'maetek@tribioshop.com';

use App\Models\Store;

// Check if user already exists
$existingUser = User::where('email', $email)->first();

if ($existingUser) {
    $existingUser->update(['role' => 'store_owner']);
    $user = $existingUser;
    echo "El usuario con email {$email} ya existe. Su rol ha sido actualizado a 'store_owner'.\n";
} else {
    $user = User::create([
        'name' => 'Maetek',
        'email' => $email,
        'password' => Hash::make('Maetek2026@'),
        'role' => 'store_owner',
    ]);
    echo "Cliente {$email} creado exitosamente! ID del usuario: " . $user->id . "\n";
}

if (!$user->store) {
    Store::create([
        'user_id'        => $user->id,
        'name'           => 'Maetek Store',
        'slug'           => 'maetek-store',
        'category'       => 'tecnologia',
        'template_name'  => 'minimal-light',
        'status'         => 'active',
        'plan'           => 'basic',
        'plan_expires_at'=> now()->addDays(30),
    ]);
    echo "Tienda 'Maetek Store' creada por defecto para este usuario.\n";
}
