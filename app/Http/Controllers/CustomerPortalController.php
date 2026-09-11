<?php

namespace App\Http\Controllers;

use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerPortalController extends Controller
{
    /**
     * Check if email is already registered in Tribio
     */
    public function checkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = trim(strtolower($request->email));
        $user = User::where('email', $email)->first();

        return response()->json([
            'exists'      => (bool) $user,
            'name'        => $user ? $user->name : null,
            'is_customer' => $user ? $user->isCliente() : false,
            'is_cliente'  => $user ? $user->isCliente() : false,
            'role'        => $user ? $user->role : null,
        ]);
    }

    /**
     * Get currently logged-in customer info and saved addresses
     */
    public function current()
    {
        if (!Auth::check()) {
            return response()->json(['authenticated' => false, 'user' => null, 'addresses' => []]);
        }

        $user = Auth::user();

        // Validar estrictamente rol cliente: dueños de tienda o super admin no son compradores de tienda
        if (!$user->isCliente()) {
            return response()->json([
                'authenticated' => false,
                'is_staff'      => true,
                'role'          => $user->role,
                'user'          => null,
                'addresses'     => [],
            ]);
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role'  => $user->role,
            ],
            'addresses' => $user->customerAddresses()->get(),
        ]);
    }

    /**
     * Authenticate customer
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = [
            'email'    => trim(strtolower($request->email)),
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials, true)) {
            $user = Auth::user();

            // Asegurar que sea rol cliente para el portal de compras
            if (!$user->isCliente()) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuenta pertenece a la administración de la tienda. Para realizar compras y usar Tribio Pass ingresa con tu cuenta de cliente.',
                ], 403);
            }

            return response()->json([
                'success'   => true,
                'user'      => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role'  => $user->role,
                ],
                'addresses' => $user->customerAddresses()->get(),
                'message'   => '¡Bienvenido de vuelta, ' . $user->name . '!',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'El correo o la contraseña ingresados son incorrectos.',
        ], 422);
    }

    /**
     * Register a new customer
     */
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:25',
            'password' => 'required|string|min:6',
            'address'  => 'nullable|string|max:255',
            'city'     => 'nullable|string|max:100',
            'state'    => 'nullable|string|max:100',
            'country'  => 'nullable|string|max:2',
            'zipcode'  => 'nullable|string|max:20',
            'type'     => 'nullable|string|in:casa,trabajo,otro',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => trim(strtolower($request->email)),
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
            'role'     => User::ROLE_CLIENTE,
        ]);

        // Save initial address if provided
        if (!empty($request->address)) {
            $user->customerAddresses()->create([
                'type'       => $request->type ?? 'casa',
                'title'      => ucfirst($request->type ?? 'casa'),
                'address'    => $request->address,
                'city'       => $request->city,
                'state'      => $request->state,
                'country'    => $request->country ?? 'PE',
                'zipcode'    => $request->zipcode,
                'is_default' => true,
            ]);
        }

        Auth::login($user, true);

        // Associate previous guest orders made with this email
        Order::whereNull('user_id')
            ->where('customer_email', $user->email)
            ->update(['user_id' => $user->id]);

        return response()->json([
            'success'   => true,
            'user'      => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'addresses' => $user->customerAddresses()->get(),
            'message'   => '¡Tu cuenta en Tribio ha sido creada exitosamente!',
        ]);
    }

    /**
     * Customer logout
     */
    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente.',
        ]);
    }

    /**
     * List customer orders with tracking information
     */
    public function orders()
    {
        if (!Auth::check() || !Auth::user()->isCliente()) {
            return response()->json(['success' => false, 'message' => 'No autenticado como cliente.', 'orders' => []], 401);
        }

        $user = Auth::user();

        $orders = Order::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('customer_email', $user->email);
        })
        ->with(['items', 'store:id,name,slug,logo_path'])
        ->latest()
        ->get()
        ->map(function ($order) {
            return [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'store_name'     => $order->store?->name ?? 'Tienda Tribio',
                'store_slug'     => $order->store?->slug,
                'total'          => (float) $order->total,
                'currency'       => $order->currency ?? 'PEN',
                'currency_symbol'=> $order->currency === 'USD' ? '$' : 'S/',
                'status'         => $order->status,
                'status_label'   => $order->status_label,
                'status_color'   => $order->status_color,
                'status_step'    => $this->resolveStatusStep($order->status),
                'created_at'     => $order->created_at ? $order->created_at->format('d/m/Y H:i') : '',
                'created_diff'   => $order->created_at ? $order->created_at->diffForHumans() : '',
                'items_count'    => $order->items->sum('quantity'),
                'items'          => $order->items->map(function ($i) {
                    return [
                        'name'     => $i->product_name,
                        'price'    => (float) $i->price,
                        'quantity' => $i->quantity,
                        'subtotal' => (float) $i->subtotal,
                    ];
                }),
                'shipping_address' => $order->customer_address ? "{$order->customer_address}, {$order->customer_city} ({$order->customer_state})" : '',
            ];
        });

        return response()->json([
            'success' => true,
            'orders'  => $orders,
        ]);
    }

    /**
     * Public track order without login
     */
    public function trackOrder(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'email'        => 'nullable|email',
        ]);

        $orderNumber = trim($request->order_number);
        $email = trim(strtolower($request->email ?? ''));

        $query = Order::where('order_number', $orderNumber)->with(['items', 'store:id,name,slug']);
        if (!empty($email)) {
            $query->where('customer_email', $email);
        }

        $order = $query->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'No encontramos ningún pedido con el número ' . $orderNumber . ($email ? ' y correo ' . $email : '') . '.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order'   => [
                'order_number'    => $order->order_number,
                'store_name'      => $order->store?->name ?? 'Tienda Tribio',
                'customer_name'   => $order->customer_name,
                'total'           => (float) $order->total,
                'currency_symbol' => $order->currency === 'USD' ? '$' : 'S/',
                'status'          => $order->status,
                'status_label'    => $order->status_label,
                'status_color'    => $order->status_color,
                'status_step'     => $this->resolveStatusStep($order->status),
                'created_at'      => $order->created_at ? $order->created_at->format('d/m/Y H:i') : '',
                'items_count'     => $order->items->sum('quantity'),
                'items'           => $order->items->map(fn($i) => ['name' => $i->product_name, 'quantity' => $i->quantity, 'price' => (float)$i->price]),
            ]
        ]);
    }

    /**
     * Get addresses list
     */
    public function addresses()
    {
        if (!Auth::check() || !Auth::user()->isCliente()) {
            return response()->json(['success' => false, 'message' => 'No autenticado como cliente.', 'addresses' => []], 401);
        }

        return response()->json([
            'success'   => true,
            'addresses' => Auth::user()->customerAddresses()->get(),
        ]);
    }

    /**
     * Save/update customer address
     */
    public function saveAddress(Request $request)
    {
        if (!Auth::check() || !Auth::user()->isCliente()) {
            return response()->json(['success' => false, 'message' => 'No autenticado como cliente.'], 401);
        }

        $user = Auth::user();

        $data = $request->validate([
            'id'         => 'nullable|integer',
            'type'       => 'required|string|in:casa,trabajo,otro',
            'title'      => 'nullable|string|max:100',
            'address'    => 'required|string|max:255',
            'city'       => 'nullable|string|max:100',
            'state'      => 'nullable|string|max:100',
            'country'    => 'nullable|string|max:2',
            'zipcode'    => 'nullable|string|max:20',
            'reference'  => 'nullable|string|max:255',
            'phone'      => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        $data['country'] = $data['country'] ?? 'PE';
        $data['title'] = $data['title'] ?? ucfirst($data['type']);
        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            $user->customerAddresses()->update(['is_default' => false]);
        }

        if (!empty($data['id'])) {
            $address = $user->customerAddresses()->findOrFail($data['id']);
            $address->update($data);
        } else {
            // If it's the first address, make it default automatically
            if ($user->customerAddresses()->count() === 0) {
                $data['is_default'] = true;
            }
            $address = $user->customerAddresses()->create($data);
        }

        return response()->json([
            'success'   => true,
            'address'   => $address,
            'addresses' => $user->customerAddresses()->get(),
            'message'   => 'Dirección guardada exitosamente.',
        ]);
    }

    /**
     * Delete customer address
     */
    public function deleteAddress($id)
    {
        if (!Auth::check() || !Auth::user()->isCliente()) {
            return response()->json(['success' => false, 'message' => 'No autenticado como cliente.'], 401);
        }

        $user = Auth::user();
        $user->customerAddresses()->where('id', $id)->delete();

        return response()->json([
            'success'   => true,
            'addresses' => $user->customerAddresses()->get(),
            'message'   => 'Dirección eliminada.',
        ]);
    }

    /**
     * Map order status to progress step index (1-5)
     */
    private function resolveStatusStep(?string $status): int
    {
        return match ($status) {
            'pending'    => 1, // 1. Pedido Recibido
            'confirmed'  => 2, // 2. Confirmado
            'processing' => 3, // 3. En Preparación
            'shipped'    => 4, // 4. En Camino / Enviado
            'delivered'  => 5, // 5. Entregado
            default      => 1,
        };
    }
}
