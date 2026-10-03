<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\User;
use App\Support\BusinessProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Onboards a business by hand: creates its store_owner login and an active store.
 * The password is passed on the command line (or generated and printed once), never
 * stored in the repo — the owner is expected to change it after the first login.
 */
class CreateStoreOwner extends Command
{
    protected $signature = 'tribio:create-owner
        {email : Login email for the owner}
        {store_name : Store/business name, e.g. "NINTAI FASHION"}
        {--name= : Owner display name (defaults to the store name)}
        {--slug= : Store slug (defaults to the slugified store name)}
        {--category=moda : Rubro key from config/tribio.php business_categories}
        {--plan=basic : Plan key from config/tribio.php plans}
        {--days=30 : Days until the plan expires}
        {--password= : Password to set (generated and printed when omitted)}';

    protected $description = 'Create a store_owner account plus an active store for a new business';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $storeName = trim($this->argument('store_name'));
        $slug = $this->option('slug') ?: Str::slug($storeName);
        $plan = $this->option('plan');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email: {$email}");
            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("A user with {$email} already exists; nothing was changed.");
            return self::FAILURE;
        }
        if (Store::where('slug', $slug)->exists()) {
            $this->error("The slug '{$slug}' is already taken; pass --slug=.");
            return self::FAILURE;
        }
        if (!config("tribio.plans.{$plan}")) {
            $this->error("Unknown plan '{$plan}'.");
            return self::FAILURE;
        }
        $category = $this->option('category');
        if (!in_array($category, BusinessProfile::keys(), true)) {
            $this->error("Unknown category '{$category}'. Valid: " . implode(', ', BusinessProfile::keys()));
            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(14, symbols: false);
        if (strlen($password) < 8) {
            $this->error('The password needs at least 8 characters.');
            return self::FAILURE;
        }

        $store = DB::transaction(function () use ($email, $storeName, $slug, $plan, $category, $password) {
            $user = User::create([
                'name'     => $this->option('name') ?: $storeName,
                'email'    => $email,
                'password' => Hash::make($password),
                'role'     => User::ROLE_STORE_OWNER,
            ]);

            return Store::create(BusinessProfile::for($category)->creationDefaults() + [
                'user_id'         => $user->id,
                'name'            => $storeName,
                'slug'            => $slug,
                'category'        => $category,
                'template_name'   => config('storefront.default_template', 'soft-market'),
                'status'          => 'active',
                'plan'            => $plan,
                'plan_expires_at' => now()->addDays((int) $this->option('days')),
            ]);
        });

        $this->info("Created {$storeName} (store #{$store->id}, slug '{$slug}').");
        $this->line("Usuario:    {$email}");
        $this->line("Contraseña: {$password}");
        $this->warn('Share these once and ask the owner to change the password after logging in.');

        return self::SUCCESS;
    }
}
