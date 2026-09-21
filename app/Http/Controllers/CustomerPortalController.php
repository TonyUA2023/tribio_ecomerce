<?php

namespace App\Http\Controllers;

use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Services\CustomerIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Tribio Pass: any account can shop, including store owners buying from other
        // stores. Only platform staff (super_admin) is excluded from the buyer portal.
        if (!$user->canUseCustomerPortal()) {
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
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'phone'     => $user->phone,
                'role'      => $user->role,
                'has_store' => $user->hasStore(),
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

            if (!$user->canUseCustomerPortal()) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuenta pertenece a la administración de la plataforma.',
                ], 403);
            }

            return response()->json([
                'success'   => true,
                'user'      => [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'phone'     => $user->phone,
                    'role'      => $user->role,
                    'has_store' => $user->hasStore(),
                ],
                'addresses' => $user->customerAddresses()->get(),
                'message'   => '¡Bienvenido de vuelta, ' . $user->name . '!',
            ]);
        }

        $googleOnly = User::where('email', $credentials['email'])->whereNotNull('google_id')->exists();

        return response()->json([
            'success' => false,
            'message' => $googleOnly
                ? 'Esta cuenta está conectada con Google. Inicia sesión con el botón "Continuar con Google".'
                : 'El correo o la contraseña ingresados son incorrectos.',
        ], 422);
    }

    /**
     * Step 1: Validate data and send OTP via Brevo API
     */
    public function sendOtp(Request $request, CustomerIdentityService $identity)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:25',
            'password' => 'required|string|min:6',
        ]);

        $identity->sendRegistrationOtp($request->name, trim(strtolower($request->email)));

        return response()->json([
            'success' => true,
            'message' => 'Código enviado. Por favor revisa tu correo electrónico.',
        ]);
    }

    /**
     * Step 2: Verify OTP and Register new customer
     */
    public function verifyAndRegister(Request $request, CustomerIdentityService $identity)
    {
        $request->validate([
            'email'    => 'required|email|max:255',
            'token'    => 'required|string|size:6',
            // User details
            'name'     => 'required|string|max:255',
            'phone'    => 'nullable|string|max:25',
            'password' => 'required|string|min:6',
            'address'  => 'nullable|string|max:255',
            'city'     => 'nullable|string|max:100',
            'state'    => 'nullable|string|max:100',
            'country'  => 'nullable|string|max:2',
            'zipcode'  => 'nullable|string|max:20',
            'type'     => 'nullable|string|in:casa,trabajo,otro',
        ]);

        $email = trim(strtolower($request->email));

        if (!$identity->verifyRegistrationOtp($email, $request->token)) {
            return response()->json([
                'success' => false,
                'message' => 'El código ingresado es incorrecto o ha expirado.',
            ], 422);
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico ya está registrado.',
            ], 422);
        }

        $user = $identity->registerCustomer([
            'name'     => $request->name,
            'email'    => $email,
            'phone'    => $request->phone,
            'password' => $request->password,
            'address'  => $request->address,
            'city'     => $request->city,
            'state'    => $request->state,
            'country'  => $request->country,
            'zipcode'  => $request->zipcode,
            'type'     => $request->type,
        ]);

        Auth::login($user, true);

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
    public function orders(CustomerIdentityService $identity)
    {
        if (!Auth::check() || !Auth::user()->canUseCustomerPortal()) {
            return response()->json(['success' => false, 'message' => 'No autenticado como cliente.', 'orders' => []], 401);
        }

        return response()->json([
            'success' => true,
            'orders'  => $identity->ordersFor(Auth::user()),
        ]);
    }

    /**
     * Public track order without login
     */
    public function trackOrder(Request $request, CustomerIdentityService $identity)
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
                'status_step'     => $identity->resolveStatusStep($order->status),
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
        if (!Auth::check() || !Auth::user()->canUseCustomerPortal()) {
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
        if (!Auth::check() || !Auth::user()->canUseCustomerPortal()) {
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
        if (!Auth::check() || !Auth::user()->canUseCustomerPortal()) {
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

}
