<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Register;
use App\Models\RegisterMovement;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\RegisterService;
use App\Services\Purchasing\PurchaseOrderService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * DEMOFERRETERIA — a hardware-store demo account with a full week of operation.
 *
 * Everything goes through the same services the app uses (purchase orders → stock lots with
 * their landed cost, POS sales, register shifts opened/closed with a count), so the reports
 * the dashboard shows (Caja → Turnos, Inventario → Rentabilidad) match what is reported here.
 *
 *   php artisan db:seed --class=DemoFerreteriaSeeder
 *
 * Not part of DatabaseSeeder on purpose: it ships a known password, so it is only ever run
 * by hand. Set DEMO_FERRETERIA_PASSWORD to use another one, and DEMO_FERRETERIA_RESET=1 to
 * wipe the demo store and load it again (the week is always the last 7 days up to today).
 *
 * Money vocabulary used by the report this seeder prints:
 *   ingresos       = sales collected
 *   costo de ventas= landed cost of the lots the sold units came from
 *   ganancia bruta = ingresos − costo de ventas
 *   gastos         = cash taken out of the drawers (lunch, freight, utilities, day labour…)
 *   ganancia neta  = ganancia bruta − gastos
 *   egresos        = stock purchases received + gastos
 */
class DemoFerreteriaSeeder extends Seeder
{
    public const EMAIL = 'ferreteria@tribio.pe';
    public const SLUG = 'demoferreteria';
    public const DEFAULT_PASSWORD = 'Ferreteria2026!';

    private const CATEGORIES = [
        'hm' => ['Herramientas manuales', '🔨', '#DC2626'],
        'he' => ['Herramientas eléctricas', '⚡', '#F59E0B'],
        'fi' => ['Fijación y sujeción', '🔩', '#6B7280'],
        'pi' => ['Pinturas y acabados', '🎨', '#2563EB'],
        'ga' => ['Gasfitería', '🚰', '#0891B2'],
        'el' => ['Electricidad', '💡', '#EAB308'],
        'co' => ['Construcción', '🧱', '#B45309'],
        'se' => ['Seguridad industrial', '🦺', '#16A34A'],
        'ce' => ['Cerrajería', '🔑', '#7C3AED'],
    ];

    private const SUPPLIERS = [
        'ferro' => ['Ferrocentro Mayorista SAC', '20000000011', 'Raúl Medina', '900000301'],
        'acero' => ['Aceros y Cementos del Norte SAC', '20000000029', 'Karla Pinedo', '900000302'],
        'pacif' => ['Distribuidora Pacífico EIRL', '20000000037', 'Jorge Lozada', '900000303'],
    ];

    private const SUPPLIER_OF = ['hm' => 'ferro', 'he' => 'ferro', 'fi' => 'ferro', 'se' => 'ferro', 'ce' => 'ferro',
        'co' => 'acero', 'pi' => 'pacif', 'ga' => 'pacif', 'el' => 'pacif'];

    /**
     * [category, sku, name, unit, price, cost, opening stock, sales weight, [min, max] units per line, ?[min, max] on a works ticket]
     */
    private const PRODUCTS = [
        ['hm', 'HM-001', 'Martillo carpintero 16 oz', 'unidad', 28.00, 17.50, 24, 3, [1, 2]],
        ['hm', 'HM-002', 'Alicate universal 8"', 'unidad', 24.90, 14.80, 30, 3, [1, 2]],
        ['hm', 'HM-003', 'Destornillador plano 6x100', 'unidad', 9.50, 5.20, 60, 4, [1, 3]],
        ['hm', 'HM-004', 'Destornillador estrella PH2', 'unidad', 9.50, 5.20, 60, 4, [1, 3]],
        ['hm', 'HM-005', 'Juego de llaves Allen 9 piezas', 'juego', 18.00, 10.40, 25, 2, [1, 2]],
        ['hm', 'HM-006', 'Llave francesa 10"', 'unidad', 36.00, 22.00, 15, 2, [1, 1]],
        ['hm', 'HM-007', 'Flexómetro 5 m', 'unidad', 14.90, 8.30, 50, 5, [1, 3]],
        ['hm', 'HM-008', 'Nivel de burbuja 24"', 'unidad', 22.00, 13.00, 18, 2, [1, 1]],
        ['hm', 'HM-009', 'Serrucho 20"', 'unidad', 32.00, 19.50, 14, 2, [1, 1]],
        ['he', 'HE-001', 'Taladro percutor 13 mm 650 W', 'unidad', 189.00, 128.00, 8, 1, [1, 1]],
        ['he', 'HE-002', 'Amoladora angular 4 1/2" 850 W', 'unidad', 169.00, 114.00, 8, 1, [1, 1]],
        ['he', 'HE-003', 'Disco de corte para metal 4 1/2"', 'unidad', 4.50, 2.30, 150, 6, [1, 6]],
        ['he', 'HE-004', 'Broca para concreto 3/8"', 'unidad', 7.50, 3.90, 80, 5, [1, 3]],
        ['he', 'HE-005', 'Extensión eléctrica 10 m', 'unidad', 34.90, 21.00, 20, 2, [1, 2]],
        ['he', 'HE-006', 'Sierra circular 7 1/4" 1400 W', 'unidad', 259.00, 178.00, 5, 0.6, [1, 1]],
        ['fi', 'FI-001', 'Clavos de 2"', 'kg', 6.80, 4.60, 120, 8, [1, 5], [5, 20]],
        ['fi', 'FI-002', 'Clavos de 3"', 'kg', 6.80, 4.60, 120, 8, [1, 5], [5, 20]],
        ['fi', 'FI-003', 'Tornillo drywall 6x1" (ciento)', 'ciento', 7.50, 4.10, 90, 7, [1, 3], [3, 8]],
        ['fi', 'FI-004', 'Perno hexagonal 3/8" x 2"', 'unidad', 1.20, 0.62, 400, 6, [4, 20], [20, 60]],
        ['fi', 'FI-005', 'Taco fisher S8 (bolsa x50)', 'bolsa', 9.00, 5.10, 60, 4, [1, 2]],
        ['fi', 'FI-006', 'Alambre N°16', 'kg', 7.20, 4.90, 100, 4, [1, 4], [5, 15]],
        ['fi', 'FI-007', 'Cinta teflón', 'unidad', 1.50, 0.60, 150, 8, [2, 6]],
        ['pi', 'PI-001', 'Pintura látex blanco 1 gl', 'galón', 62.00, 44.00, 30, 4, [1, 2], [2, 8]],
        ['pi', 'PI-002', 'Esmalte sintético 1/4 gl', 'cuarto', 38.00, 26.00, 24, 3, [1, 2], [2, 5]],
        ['pi', 'PI-003', 'Thinner acrílico 1 L', 'litro', 12.00, 7.40, 40, 3, [1, 3]],
        ['pi', 'PI-004', 'Brocha 3"', 'unidad', 8.50, 4.60, 50, 4, [1, 2]],
        ['pi', 'PI-005', 'Rodillo 9" con mango', 'unidad', 14.00, 8.00, 36, 3, [1, 2]],
        ['pi', 'PI-006', 'Lija de agua N°220', 'unidad', 1.80, 0.85, 200, 6, [2, 8], [10, 30]],
        ['pi', 'PI-007', 'Masilla lista 1 kg', 'unidad', 16.00, 10.20, 28, 3, [1, 2], [2, 6]],
        ['ga', 'GA-001', 'Tubo PVC agua 1/2" x 5 m', 'unidad', 14.50, 9.20, 40, 4, [1, 3], [4, 12]],
        ['ga', 'GA-002', 'Codo PVC 1/2"', 'unidad', 0.90, 0.40, 300, 6, [2, 10], [10, 30]],
        ['ga', 'GA-003', 'Pegamento PVC 1/16 gl', 'unidad', 11.00, 6.60, 36, 4, [1, 2]],
        ['ga', 'GA-004', 'Llave de paso 1/2"', 'unidad', 19.00, 11.50, 30, 3, [1, 2]],
        ['ga', 'GA-005', 'Grifo de lavatorio cromado', 'unidad', 45.00, 28.00, 14, 2, [1, 1]],
        ['ga', 'GA-006', 'Tubo desagüe PVC 2" x 3 m', 'unidad', 21.00, 13.80, 30, 3, [1, 3], [3, 10]],
        ['el', 'EL-001', 'Cable THW 14 AWG', 'metro', 2.20, 1.35, 600, 6, [5, 30], [30, 100]],
        ['el', 'EL-002', 'Interruptor simple', 'unidad', 4.50, 2.30, 80, 4, [1, 4], [4, 12]],
        ['el', 'EL-003', 'Tomacorriente doble', 'unidad', 6.50, 3.40, 80, 4, [1, 4], [4, 12]],
        ['el', 'EL-004', 'Foco LED 9 W', 'unidad', 5.50, 2.70, 120, 7, [1, 6], [6, 20]],
        ['el', 'EL-005', 'Cinta aislante', 'unidad', 3.50, 1.70, 100, 6, [1, 3]],
        ['el', 'EL-006', 'Interruptor termomagnético 20 A', 'unidad', 22.00, 13.50, 24, 2, [1, 2], [2, 4]],
        ['co', 'CO-001', 'Cemento Sol 42.5 kg', 'bolsa', 29.50, 25.20, 120, 6, [1, 4], [10, 40]],
        ['co', 'CO-002', 'Fierro corrugado 1/2" x 9 m', 'varilla', 46.00, 38.50, 80, 4, [1, 3], [8, 30]],
        ['co', 'CO-003', 'Ladrillo King Kong', 'unidad', 1.30, 0.95, 800, 3, [20, 60], [150, 400]],
        ['co', 'CO-004', 'Arena fina (saco)', 'saco', 6.00, 3.80, 150, 4, [2, 6], [10, 30]],
        ['co', 'CO-005', 'Yeso bolsa 20 kg', 'bolsa', 15.00, 10.50, 40, 2, [1, 3], [3, 10]],
        ['se', 'SE-001', 'Casco de seguridad', 'unidad', 16.00, 8.90, 30, 3, [1, 3], [3, 10]],
        ['se', 'SE-002', 'Guantes de cuero', 'par', 11.50, 6.30, 50, 4, [1, 3], [3, 10]],
        ['se', 'SE-003', 'Lentes de seguridad', 'unidad', 6.50, 3.00, 60, 3, [1, 3], [3, 10]],
        ['se', 'SE-004', 'Botas con punta de acero', 'par', 78.00, 52.00, 12, 1, [1, 1], [1, 4]],
        ['ce', 'CE-001', 'Candado 40 mm', 'unidad', 18.00, 10.20, 40, 3, [1, 2]],
        ['ce', 'CE-002', 'Cerradura de pomo', 'unidad', 49.00, 31.00, 18, 2, [1, 2], [2, 6]],
        ['ce', 'CE-003', 'Bisagra 3" (par)', 'par', 5.50, 2.90, 100, 4, [2, 6], [6, 20]],
    ];

    /** Customers who buy with a name (works/contractors) — fictional contacts for the demo. */
    private const WORKS_CUSTOMERS = [
        ['Constructora Andina SAC', '900000101', '20000000045'],
        ['Inversiones Pacífico EIRL', '900000102', '20000000053'],
        ['Edificaciones Norte SAC', '900000103', '20000000061'],
        ['Maestro Julio Vásquez', '900000104', '40000001'],
        ['Contratista Rosa Chávez', '900000105', '40000002'],
        ['Maestro Carlos Díaz', '900000106', '40000003'],
    ];

    private const COUNTER_CUSTOMERS = [
        ['Lucía Torres', '900000201', '41000001'],
        ['Pedro Salazar', '900000202', '41000002'],
        ['Marcos Huamán', '900000203', null],
        ['Elena Rojas', '900000204', null],
    ];

    private PurchaseOrderService $purchases;
    private PosSaleService $sales;
    private RegisterService $registers;

    public function run(): void
    {
        $existing = Store::withTrashed()->where('slug', self::SLUG)->first();
        if ($existing && !env('DEMO_FERRETERIA_RESET')) {
            $this->say("La tienda «" . self::SLUG . "» ya existe: no se tocó nada. Usa DEMO_FERRETERIA_RESET=1 para borrarla y cargarla de nuevo.");

            return;
        }

        $this->purchases = app(PurchaseOrderService::class);
        $this->sales = app(PosSaleService::class);
        $this->registers = app(RegisterService::class);

        $realNow = Carbon::now();
        mt_srand(20261103); // same data every run

        try {
            $existing?->forceDelete();
            $password = (string) (env('DEMO_FERRETERIA_PASSWORD') ?: self::DEFAULT_PASSWORD);
            [$owner, $store] = $this->account($password, $realNow);
            $categories = $this->categories($store);
            $suppliers = $this->suppliers($store);
            $products = $this->products($store, $categories);

            $this->openingStock($store, $owner, $suppliers, $products, $realNow);
            $this->tradeWeek($store, $owner, $suppliers, $products, $realNow);
        } finally {
            $this->travel(null);
        }

        $from = $realNow->copy()->subDays(6)->startOfDay();
        $report = $this->weeklyReport($store, $from, $realNow->copy()->endOfDay());
        $this->printReport($report);
        $this->say('');
        $this->say('Tienda:      DEMOFERRETERIA (/' . self::SLUG . ')');
        $this->say('Usuario:     ' . self::EMAIL);
        $this->say('Contraseña:  ' . $password);
    }

    // ─── Account, catalog ───────────────────────────────────────

    /** @return array{0: User, 1: Store} */
    private function account(string $password, Carbon $now): array
    {
        $owner = User::updateOrCreate(['email' => self::EMAIL], [
            'name'     => 'FERRETERIA',
            'password' => $password,
            'role'     => 'store_owner',
            'phone'    => '+51900000001',
        ]);

        $store = Store::create([
            'user_id'         => $owner->id,
            'name'            => 'DEMOFERRETERIA',
            'slug'            => self::SLUG,
            'description'     => 'Ferretería demo: herramientas, fijación, pinturas, gasfitería, electricidad y materiales de construcción.',
            'tagline'         => 'Todo para tu obra, en un solo lugar.',
            'category'        => 'otros',
            'template_name'   => 'industrial-light',
            'accent_color'    => '#F59E0B',
            'secondary_color' => '#1F2937',
            'whatsapp_phone'  => '51900000001',
            'phone'           => '900000001',
            'email'           => self::EMAIL,
            'status'          => 'active',
            'plan'            => 'professional',
            'plan_expires_at' => $now->copy()->addYear(),
            'city'            => 'Chiclayo',
            'country'         => 'PE',
            'address'         => 'Av. Los Constructores 123, Chiclayo',
        ]);
        $owner->switchToStore($store);

        return [$owner, $store];
    }

    /** @return array<string, Category> keyed by the short category code */
    private function categories(Store $store): array
    {
        $categories = [];
        $order = 0;
        foreach (self::CATEGORIES as $code => [$name, $icon, $color]) {
            $categories[$code] = Category::create([
                'store_id' => $store->id, 'name' => $name, 'slug' => Str::slug($name), 'icon' => $icon,
                'color' => $color, 'sort_order' => $order++, 'is_active' => true, 'show_in_header' => true,
            ]);
        }

        return $categories;
    }

    /** @return array<string, Supplier> */
    private function suppliers(Store $store): array
    {
        $suppliers = [];
        foreach (self::SUPPLIERS as $key => [$name, $ruc, $contact, $phone]) {
            $suppliers[$key] = Supplier::create(['store_id' => $store->id, 'name' => $name, 'document_number' => $ruc,
                'contact_name' => $contact, 'phone' => $phone, 'notes' => 'Proveedor ficticio de la cuenta demo.']);
        }

        return $suppliers;
    }

    /** @return array<string, array{model: Product, meta: array}> keyed by SKU */
    private function products(Store $store, array $categories): array
    {
        $products = [];
        foreach (self::PRODUCTS as $i => $row) {
            [$cat, $sku, $name, $unit, $price, $cost, $stock, $weight] = $row;
            $product = Product::create([
                'store_id'          => $store->id,
                'category_id'       => $categories[$cat]->id,
                'name'              => $name,
                'slug'              => Str::slug($name . ' ' . $sku),
                'sku'               => $sku,
                'short_description' => $name . ' · ' . self::CATEGORIES[$cat][0],
                'price'             => $price,
                'cost_price'        => $cost,
                'stock'             => 0, // enters through the opening purchase, with its lot cost
                'track_stock'       => true,
                'low_stock_alert'   => max(5, (int) round($stock * 0.2)),
                'unit'              => $unit,
                'is_active'         => true,
                'is_featured'       => $i % 9 === 0,
                'sort_order'        => $i,
            ]);
            $products[$sku] = ['model' => $product, 'meta' => [
                'cat' => $cat, 'opening' => $stock, 'weight' => $weight, 'range' => $row[8], 'works' => $row[9] ?? null,
            ]];
        }

        return $products;
    }

    // ─── Purchases ──────────────────────────────────────────────

    /** Opening inventory: one purchase per supplier, ordered 9 days ago and received 8 days ago (before the week). */
    private function openingStock(Store $store, User $owner, array $suppliers, array $products, Carbon $now): void
    {
        $ordered = $now->copy()->subDays(9)->setTime(10, 0);
        $received = $now->copy()->subDays(8)->setTime(9, 30);
        foreach ($suppliers as $key => $supplier) {
            $items = [];
            foreach ($products as $entry) {
                if (self::SUPPLIER_OF[$entry['meta']['cat']] === $key) {
                    $items[] = ['product_id' => $entry['model']->id, 'quantity' => $entry['meta']['opening'], 'unit_cost' => (float) $entry['model']->cost_price];
                }
            }
            $freight = $key === 'acero' ? 180.00 : 60.00;
            $this->purchase($store, $owner, $supplier, $items, $freight, 'Inventario inicial de la demo', $ordered, $received);
        }
    }

    /**
     * Restock: what ran under 40% of its opening stock is ordered back up to ~60% of it,
     * from each supplier that has something to bring.
     */
    private function restock(Store $store, User $owner, array $suppliers, array $products, Carbon $at): void
    {
        foreach ($suppliers as $key => $supplier) {
            $items = [];
            foreach ($products as $entry) {
                $meta = $entry['meta'];
                $stock = (int) Product::whereKey($entry['model']->id)->value('stock');
                if (self::SUPPLIER_OF[$meta['cat']] === $key && $stock < $meta['opening'] * 0.4) {
                    $items[] = ['product_id' => $entry['model']->id, 'quantity' => (int) round($meta['opening'] * 0.6), 'unit_cost' => (float) $entry['model']->cost_price];
                }
            }
            if ($items !== []) {
                $this->purchase($store, $owner, $supplier, $items, $key === 'acero' ? 90.00 : 35.00, 'Reposición de stock', $at->copy()->subDay()->setTime(16, 0), $at);
            }
        }
    }

    private function purchase(Store $store, User $owner, Supplier $supplier, array $items, float $freight, string $note, Carbon $orderedAt, Carbon $receivedAt): PurchaseOrder
    {
        $this->travel($orderedAt);
        $order = $this->purchases->save($store, [
            'supplier_id' => $supplier->id, 'expected_at' => $receivedAt->toDateString(), 'extra_costs' => $freight,
            'notes' => $note, 'mark_ordered' => true, 'items' => $items,
        ], null, $owner->id);

        $this->travel($receivedAt);

        return $this->purchases->receive($order, $order->items->mapWithKeys(fn ($item) => [$item->id => ['quantity' => $item->quantity_ordered]])->all(), $owner->id);
    }

    // ─── The week ───────────────────────────────────────────────

    private function tradeWeek(Store $store, User $owner, array $suppliers, array $products, Carbon $realNow): void
    {
        $today = $realNow->copy()->startOfDay();
        $cajas = [
            Register::create(['store_id' => $store->id, 'name' => 'Caja 1 · Mostrador']),
            Register::create(['store_id' => $store->id, 'name' => 'Caja 2 · Materiales']),
        ];

        for ($ago = 6; $ago >= 0; $ago--) {
            $day = $today->copy()->subDays($ago);
            $open = $day->copy()->setTime(8, 0);
            $end = $day->copy()->setTime(19, 0);
            if ($ago === 0) {
                $end = $end->min($realNow->copy()->subMinutes(15)); // never in the future
            }
            if ($end->lt($open->copy()->addMinutes(45))) {
                continue; // seeded too early in the day for there to be a shift
            }

            if (in_array($ago, [4, 1], true)) {
                $this->restock($store, $owner, $suppliers, $products, $day->copy()->setTime(7, 30));
            }

            $this->travel($open);
            $dow = $day->dayOfWeek; // 0 Sunday … 6 Saturday
            $active = $dow === 0 ? [0] : [0, 1];
            $sessions = [];
            foreach ($active as $idx) {
                $sessions[$idx] = $this->registers->open($cajas[$idx], $idx === 0 ? 200.00 : 150.00, $owner->id);
            }

            $events = $this->timeline($ago, $dow, $open, $end, $active);
            foreach ($events as $event) {
                $this->travel($event['at']);
                if ($event['type'] === 'sale') {
                    $this->ringUp($store, $owner, $products, $sessions, $event);
                } else {
                    $this->payOut($owner, $sessions[$event['register']], $event);
                }
            }

            $closeAt = $end->copy()->addMinutes(10);
            if ($ago === 0) {
                $closeAt = $closeAt->min($realNow->copy()->subMinutes(2));
            }
            $this->travel($closeAt);
            foreach ($sessions as $idx => $session) {
                $expected = $this->registers->summary($session)['expected_cash'];
                $gap = [0, 0, 0, -2.50, 0, 1.00, 0, -5.00, 0, 0.50][mt_rand(0, 9)];
                $this->registers->close($session, $expected + $gap, $gap === 0 ? null : 'Diferencia por vuelto / sencillo.', $owner->id);
            }
        }
    }

    /** The day's sales and drawer payments in time order. */
    private function timeline(int $ago, int $dow, Carbon $open, Carbon $end, array $active): array
    {
        $factor = match ($dow) {
            6 => 1.6,
            0 => 0.55,
            5 => 1.15,
            default => 1.0,
        };
        $window = max(1, $open->diffInMinutes($end));
        $count = max(4, (int) round((16 * $factor + mt_rand(-2, 2)) * min(1, $window / 660)));

        $events = [];
        for ($i = 0; $i < $count; $i++) {
            $kind = mt_rand(1, 100) <= 28 ? 'works' : 'counter';
            $register = $kind === 'works' && count($active) > 1 ? ($this->chance(80) ? 1 : 0) : (count($active) > 1 && $this->chance(30) ? 1 : 0);
            $events[] = ['type' => 'sale', 'kind' => $kind, 'register' => $register, 'at' => $open->copy()->addMinutes(mt_rand(10, max(11, $window - 5)))];
        }

        // Drawer payments (gastos): lunch every day, utilities once, day labour on Saturday, small freight.
        $outs = [['Almuerzo del personal', $dow === 0 ? 18.00 : 36.00, 0]];
        if ($ago === 5) {
            $outs[] = ['Pago de recibo de agua', 74.20, 0];
        }
        if ($ago === 2) {
            $outs[] = ['Pago de recibo de luz', 212.60, 0];
        }
        if ($dow === 6) {
            $outs[] = ['Jornal de ayudantes de despacho', 150.00, 1];
            $outs[] = ['Combustible de reparto', 45.00, 0];
        } elseif ($dow !== 0) {
            if ($this->chance(60)) {
                $outs[] = ['Flete de reparto en mototaxi', (float) mt_rand(20, 45), 1];
            }
            if ($this->chance(30)) {
                $outs[] = ['Útiles de limpieza', (float) mt_rand(12, 24), 0];
            }
        }
        foreach ($outs as [$reason, $amount, $register]) {
            $at = $open->copy()->setTime(mt_rand(11, 16), mt_rand(0, 59));
            if ($at->lt($end->copy()->subMinutes(5)) && isset(array_flip($active)[$register])) {
                $events[] = ['type' => 'out', 'register' => $register, 'at' => $at, 'reason' => $reason, 'amount' => $amount];
            }
        }

        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $events;
    }

    private function payOut(User $owner, RegisterSession $session, array $event): void
    {
        // The drawer must hold it (the app refuses otherwise): skip rather than invent cash.
        if ($event['amount'] + 20 <= $this->registers->summary($session)['expected_cash']) {
            $this->registers->movement($session, 'out', $event['amount'], $event['reason'], $owner->id);
        }
    }

    private function ringUp(Store $store, User $owner, array $products, array $sessions, array $event): void
    {
        $works = $event['kind'] === 'works';
        $items = $this->basket($products, $works);
        if ($items === []) {
            return;
        }

        $lines = $this->sales->lines($store->id, $items);
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $discount = $subtotal >= 80 && $this->chance(12) ? (float) max(1, floor($subtotal * 0.05)) : 0.0;
        $total = round($subtotal - $discount, 2);

        $customer = null;
        if ($this->chance($works ? 65 : 12)) {
            $pool = $works ? self::WORKS_CUSTOMERS : self::COUNTER_CUSTOMERS;
            $customer = $pool[mt_rand(0, count($pool) - 1)];
        }

        $data = [
            'uuid'     => (string) Str::uuid(),
            'items'    => $items,
            'discount' => $discount,
            'payments' => $this->payment($total, $works),
        ];
        if ($customer) {
            $data += ['customer_name' => $customer[0], 'customer_phone' => $customer[1], 'customer_document' => $customer[2]];
        }

        $this->sales->sell($sessions[$event['register']], $data, $owner->id);
    }

    /** @return list<array{product_id: int, quantity: int}> */
    private function basket(array $products, bool $works): array
    {
        $heavy = ['co'];
        $pool = [];
        foreach ($products as $sku => $entry) {
            $weight = $entry['meta']['weight'];
            if ($works) {
                $weight *= in_array($entry['meta']['cat'], ['co', 'fi', 'pi', 'el', 'se'], true) ? 2.5 : 0.4;
            } elseif (in_array($entry['meta']['cat'], $heavy, true)) {
                $weight *= 0.5; // a few bags at the counter, not truckloads
            }
            $pool[$sku] = $weight;
        }

        $lineCount = $works ? mt_rand(2, 5) : (int) $this->pickWeighted([1 => 40, 2 => 35, 3 => 18, 4 => 7]);
        $items = [];
        for ($i = 0; $i < $lineCount && $pool !== []; $i++) {
            $sku = $this->pickWeighted($pool);
            unset($pool[$sku]);
            $meta = $products[$sku]['meta'];
            [$min, $max] = $works && $meta['works'] ? $meta['works'] : $meta['range'];
            $quantity = mt_rand($min, $max);
            $stock = (int) Product::whereKey($products[$sku]['model']->id)->value('stock');
            $quantity = min($quantity, $stock);
            if ($quantity > 0) {
                $items[] = ['product_id' => $products[$sku]['model']->id, 'quantity' => $quantity];
            }
        }

        return $items;
    }

    /** @return list<array{method: string, amount: float}> */
    private function payment(float $total, bool $works): array
    {
        $roll = mt_rand(1, 100);
        $method = $works
            ? ($roll <= 40 ? 'cash' : ($roll <= 75 ? 'transfer' : ($roll <= 85 ? 'yape' : 'card')))
            : ($roll <= 52 ? 'cash' : ($roll <= 74 ? 'yape' : ($roll <= 82 ? 'plin' : ($roll <= 94 ? 'card' : 'mixed'))));

        if ($method === 'mixed') {
            $yape = round($total / 2, 2);

            return [['method' => 'yape', 'amount' => $yape], ['method' => 'cash', 'amount' => round($total - $yape, 2)]];
        }
        if ($method === 'cash') {
            $step = $total > 300 ? 50 : ($total > 60 ? 10 : 5);
            $tendered = $this->chance(60) ? (float) (ceil($total / $step) * $step) : $total;

            return [['method' => 'cash', 'amount' => $tendered]];
        }

        return [['method' => $method, 'amount' => $total]];
    }

    // ─── Report ─────────────────────────────────────────────────

    /**
     * The week as the owner reads it: per day and in total, plus the shift of every register.
     *
     * @return array{days: array<string, array>, totals: array, shifts: list<array>}
     */
    public function weeklyReport(Store $store, Carbon $from, Carbon $to): array
    {
        $days = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $days[$d->toDateString()] = ['sales' => 0, 'income' => 0.0, 'cost' => 0.0, 'gross' => 0.0, 'expenses' => 0.0,
                'net' => 0.0, 'purchases' => 0.0, 'outflow' => 0.0, 'methods' => []];
        }

        $orders = Order::where('store_id', $store->id)->where('status', '!=', 'cancelled')->whereBetween('created_at', [$from, $to])->get(['total', 'created_at']);
        foreach ($orders as $order) {
            $days[$order->created_at->toDateString()]['sales']++;
            $days[$order->created_at->toDateString()]['income'] += (float) $order->total;
        }

        $costs = InventoryMovement::where('store_id', $store->id)->where('type', 'sale')->whereBetween('created_at', [$from, $to])->get(['total_cost', 'created_at']);
        foreach ($costs as $movement) {
            $days[$movement->created_at->toDateString()]['cost'] += (float) $movement->total_cost;
        }

        $payments = OrderPayment::whereIn('register_session_id', RegisterSession::where('store_id', $store->id)->select('id'))
            ->where('status', OrderPayment::STATUS_PAID)->whereBetween('paid_at', [$from, $to])->get(['method', 'amount', 'paid_at']);
        foreach ($payments as $payment) {
            $row = &$days[$payment->paid_at->toDateString()]['methods'];
            $row[$payment->method] = ($row[$payment->method] ?? 0.0) + (float) $payment->amount;
            unset($row);
        }

        $outs = RegisterMovement::where('type', 'out')->whereIn('register_session_id', RegisterSession::where('store_id', $store->id)->select('id'))
            ->whereBetween('created_at', [$from, $to])->get(['amount', 'created_at']);
        foreach ($outs as $out) {
            $days[$out->created_at->toDateString()]['expenses'] += (float) $out->amount;
        }

        $received = PurchaseOrder::where('store_id', $store->id)->where('status', 'received')->whereBetween('received_at', [$from, $to])->get(['total', 'received_at']);
        foreach ($received as $purchase) {
            $days[$purchase->received_at->toDateString()]['purchases'] += (float) $purchase->total;
        }

        $totals = ['sales' => 0, 'income' => 0.0, 'cost' => 0.0, 'gross' => 0.0, 'expenses' => 0.0, 'net' => 0.0, 'purchases' => 0.0, 'outflow' => 0.0];
        foreach ($days as $date => &$day) {
            $day['income'] = round($day['income'], 2);
            $day['cost'] = round($day['cost'], 2);
            $day['gross'] = round($day['income'] - $day['cost'], 2);
            $day['expenses'] = round($day['expenses'], 2);
            $day['net'] = round($day['gross'] - $day['expenses'], 2);
            $day['purchases'] = round($day['purchases'], 2);
            $day['outflow'] = round($day['purchases'] + $day['expenses'], 2);
            foreach ($totals as $key => $_) {
                $totals[$key] += $day[$key];
            }
        }
        unset($day);
        $totals = array_map(fn ($value) => is_float($value) ? round($value, 2) : $value, $totals);

        $shifts = RegisterSession::where('store_id', $store->id)->whereBetween('opened_at', [$from, $to])->with('register:id,name')
            ->withCount(['orders' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->withSum(['orders' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->orderBy('opened_at')->orderBy('register_id')->get()
            ->map(fn (RegisterSession $s) => [
                'date' => $s->opened_at->toDateString(), 'register' => $s->register->name, 'opened' => $s->opened_at->format('H:i'),
                'closed' => $s->closed_at?->format('H:i'), 'sales' => $s->orders_count, 'sold' => round((float) $s->orders_sum_total, 2),
                'float' => (float) $s->opening_float, 'expected' => (float) $s->expected_cash, 'counted' => (float) $s->counted_cash, 'difference' => (float) $s->difference,
            ])->all();

        return compact('days', 'totals', 'shifts');
    }

    private function printReport(array $report): void
    {
        if (!$this->command) {
            return;
        }
        $money = fn ($v) => number_format((float) $v, 2);

        $this->say('');
        $this->say('RESUMEN POR DÍA');
        $rows = [];
        foreach ($report['days'] as $date => $d) {
            $rows[] = [$date, $d['sales'], $money($d['income']), $money($d['cost']), $money($d['gross']), $money($d['expenses']), $money($d['net']), $money($d['purchases']), $money($d['outflow'])];
        }
        $t = $report['totals'];
        $rows[] = ['TOTAL', $t['sales'], $money($t['income']), $money($t['cost']), $money($t['gross']), $money($t['expenses']), $money($t['net']), $money($t['purchases']), $money($t['outflow'])];
        $this->command->table(['Día', 'Ventas', 'Ingresos', 'Costo', 'G. bruta', 'Gastos', 'G. neta', 'Compras', 'Egresos'], $rows);

        $this->say('REPORTE DE CAJAS');
        $this->command->table(['Día', 'Caja', 'Abre', 'Cierra', 'Ventas', 'Vendido', 'Fondo', 'Esperado', 'Contado', 'Dif.'],
            array_map(fn ($s) => [$s['date'], $s['register'], $s['opened'], $s['closed'], $s['sales'], $money($s['sold']), $money($s['float']), $money($s['expected']), $money($s['counted']), $money($s['difference'])], $report['shifts']));
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    /** @param array<int|string, float|int> $weights */
    private function pickWeighted(array $weights): int|string
    {
        $roll = mt_rand() / mt_getrandmax() * array_sum($weights);
        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    private function travel(?Carbon $to): void
    {
        Carbon::setTestNow($to);
        CarbonImmutable::setTestNow($to);
    }

    private function say(string $line): void
    {
        $this->command?->getOutput()->writeln($line);
    }
}
