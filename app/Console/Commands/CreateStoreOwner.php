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
    /** @var array<string, mixed> validated --set values */
    private array $extraSettings = [];

    protected $signature = 'tribio:create-owner
        {email : Login email for the owner}
        {store_name : Store/business name, e.g. "NINTAI FASHION"}
        {--name= : Owner display name (defaults to the store name)}
        {--slug= : Store slug (defaults to the slugified store name)}
        {--category=moda : Rubro key from config/tribio.php business_categories}
        {--plan=basic : Plan key from config/tribio.php plans}
        {--days=30 : Days until the plan expires}
        {--password= : Password to set (generated and printed when omitted)}
        {--template= : Storefront template key from config/storefront.php (defaults to the platform default)}
        {--primary= : Brand primary color as #RRGGBB (template setting colors.primary)}
        {--secondary= : Brand secondary color as #RRGGBB (template setting colors.secondary)}
        {--font= : Heading font key of the template (typography.heading), e.g. playfair}
        {--set=* : Any other template setting as path=value, e.g. --set="hero.items.0.title=Jeans" (toggles take 0/1; an empty value clears an optional text)}
        {--tagline= : Short slogan shown by the storefront}
        {--description= : Store description (About / SEO)}
        {--whatsapp= : WhatsApp number with country code, e.g. 51999888777}';

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

        $template = $this->option('template') ?: config('storefront.default_template', 'soft-market');
        if (!config("storefront.templates.{$template}")) {
            $this->error("Unknown template '{$template}'. Valid: " . implode(', ', array_keys(config('storefront.templates'))));
            return self::FAILURE;
        }
        $extra = [];
        foreach ((array) $this->option('set') as $pair) {
            [$path, $value] = array_pad(explode('=', (string) $pair, 2), 2, null);
            $field = app(\App\Services\Storefront\TemplateRegistry::class)->fields($template)[trim((string) $path)] ?? null;
            if ($value === null || !$field) {
                $this->error("--set '{$pair}': unknown setting for {$template} (use path=value).");
                return self::FAILURE;
            }
            $extra[trim($path)] = $field['type'] === 'toggle' ? in_array($value, ['1', 'true', 'si', 'sí'], true) : $value;
        }
        $this->extraSettings = $extra;
        foreach (['primary', 'secondary'] as $colorOption) {
            if ($this->option($colorOption) && !preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->option($colorOption))) {
                $this->error("--{$colorOption} must look like #1F4E8C.");
                return self::FAILURE;
            }
        }

        $password = $this->option('password') ?: Str::password(14, symbols: false);
        if (strlen($password) < 8) {
            $this->error('The password needs at least 8 characters.');
            return self::FAILURE;
        }

        $store = DB::transaction(function () use ($email, $storeName, $slug, $plan, $category, $password, $template) {
            $user = User::create([
                'name'     => $this->option('name') ?: $storeName,
                'email'    => $email,
                'password' => Hash::make($password),
                'role'     => User::ROLE_STORE_OWNER,
            ]);

            // Optional brand fields are left out when not given so the column defaults apply.
            $brand = array_filter([
                'tagline'         => $this->option('tagline'),
                'description'     => $this->option('description'),
                'whatsapp_phone'  => $this->option('whatsapp') ? preg_replace('/\D/', '', (string) $this->option('whatsapp')) : null,
                'accent_color'    => $this->option('primary'),
                'secondary_color' => $this->option('secondary'),
                'template_settings' => $this->templateSettings($template),
            ], fn ($value) => $value !== null && $value !== '' && $value !== []);

            return Store::create(BusinessProfile::for($category)->creationDefaults() + $brand + [
                'user_id'         => $user->id,
                'name'            => $storeName,
                'slug'            => $slug,
                'category'        => $category,
                'template_name'   => $template,
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

    /** Brand colors and heading font saved the same way the Plantillas customizer saves them. */
    private function templateSettings(string $template): array
    {
        $settings = [];
        foreach (['colors.primary' => 'primary', 'colors.secondary' => 'secondary', 'typography.heading' => 'font'] as $path => $option) {
            if ($this->option($option)) {
                data_set($settings, $path, $this->option($option));
            }
        }

        foreach ($this->extraSettings as $path => $value) {
            data_set($settings, $path, $value);
        }

        return $settings ? [$template => $settings] : [];
    }
}
