<?php

namespace Tests\Feature;

use App\Services\CulqiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CulqiPlanPriceTest extends TestCase
{
    public function test_price_change_creates_a_new_culqi_plan_for_new_subscriptions(): void
    {
        config(['services.culqi.secret_key' => 'test-secret']);
        Cache::put('culqi_plan_id_basic', 'old-plan');
        Http::preventStrayRequests();
        Http::fake([
            'api.culqi.com/v2/plans' => Http::response(['id' => 'new-plan', 'amount' => 1990]),
        ]);

        $plan = app(CulqiService::class)->getOrCreatePlan('basic');

        $this->assertSame('new-plan', $plan['id']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://api.culqi.com/v2/plans'
            && $request['amount'] === 1990);
    }
}
