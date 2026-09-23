<?php

namespace Tests\Feature\Concerns;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\User;
use App\Services\Reviews\ProductReviewService;
use Illuminate\Support\Str;

/**
 * Data builders shared by the review test classes. (Each class still declares its own
 * migrateFreshUsing(): a trait can't override RefreshDatabase's without a name clash.)
 */
trait BuildsReviewFixtures
{
    private function makeStore(array $attributes = []): Store
    {
        $owner = User::factory()->create(['role' => 'store_owner']);

        return Store::create($attributes + [
            'user_id' => $owner->id, 'name' => 'Tienda Reseñas', 'slug' => 'tienda-resenas-' . Str::random(5),
            'status' => 'active', 'template_name' => 'soft-market', 'contact_email' => 'duena@example.test',
        ]);
    }

    private function makeProduct(Store $store, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Polo bordado', 'slug' => 'polo-' . Str::random(5),
            'price' => 50, 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function buyer(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => 'cliente', 'name' => 'María Castillo Pérez']);
    }

    /** An order of $buyer's containing $product, in the given status. */
    private function purchase(User $buyer, Product $product, string $status = 'delivered', array $orderAttributes = []): Order
    {
        $order = Order::create($orderAttributes + [
            'store_id' => $product->store_id, 'user_id' => $buyer->id, 'order_number' => 'TRB-T-' . Str::upper(Str::random(8)),
            'customer_name' => $buyer->name, 'customer_email' => $buyer->email,
            'subtotal' => 50, 'total' => 50, 'currency' => 'PEN', 'status' => $status, 'payment_status' => 'paid',
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 50, 'quantity' => 1, 'subtotal' => 50]);

        return $order;
    }

    private function service(): ProductReviewService
    {
        return app(ProductReviewService::class);
    }

    /** A published review by a brand-new verified buyer. */
    private function seedReview(Product $product, int $rating = 5, ?string $comment = 'Muy bueno', array $buyerAttributes = []): ProductReview
    {
        $buyer = $this->buyer($buyerAttributes);
        $this->purchase($buyer, $product);

        return $this->service()->submit($buyer, $product, $rating, $comment)['review'];
    }
}
