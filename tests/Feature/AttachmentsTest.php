<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Attachments\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function store(bool $madeToOrder = true, string $slug = 'bordados'): Store
    {
        return Store::create([
            'user_id' => User::factory()->create()->id, 'name' => 'Bordados ' . $slug, 'slug' => $slug,
            'status' => 'active', 'template_name' => 'soft-market', 'made_to_order_enabled' => $madeToOrder,
        ]);
    }

    private function upload(Store $store, UploadedFile $file)
    {
        return $this->postJson(route('store.attachments.upload', $store->slug), ['file' => $file]);
    }

    public function test_stores_that_do_not_sell_made_to_order_refuse_uploads(): void
    {
        $this->upload($this->store(false), UploadedFile::fake()->image('logo.png'))->assertNotFound();
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_an_image_is_stored_privately_and_only_served_through_a_signed_url(): void
    {
        $store = $this->store();

        $response = $this->upload($store, UploadedFile::fake()->image('Logo Empresa.png', 300, 200))->assertCreated()
            ->assertJsonStructure(['token', 'name', 'mime', 'size', 'preview_url']);
        $attachment = Attachment::sole();

        $this->assertSame(['image/png', 'Logo Empresa.png'], [$attachment->mime, $attachment->original_name]);
        $this->assertNull($attachment->attachable_type, 'Unlinked until an order claims it');
        $this->assertStringStartsWith("attachments/{$store->id}/", $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringNotContainsString($attachment->path, $response->getContent(), 'The storage path is never exposed');

        $this->get(route('attachments.show', $attachment->token))->assertForbidden();
        $this->get($response->json('preview_url'))->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_svg_and_disguised_files_are_rejected(): void
    {
        $store = $this->store();

        $this->upload($store, UploadedFile::fake()->createWithContent('logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))->assertStatus(422);
        $this->upload($store, UploadedFile::fake()->createWithContent('logo.png', '<?php echo "no soy una imagen";'))->assertStatus(422);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_pdfs_are_accepted_and_always_downloaded_rather_than_rendered(): void
    {
        $store = $this->store();
        $pdf = UploadedFile::fake()->createWithContent('diseño.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        $url = $this->upload($store, $pdf)->assertCreated()->json('preview_url');

        $this->assertSame('application/pdf', Attachment::sole()->mime);
        $this->assertStringContainsString('attachment;', $this->get($url)->assertOk()->headers->get('Content-Disposition'));
    }

    public function test_an_upload_can_only_be_linked_to_its_own_store_and_unlinked_ones_are_pruned(): void
    {
        $store = $this->store();
        $other = $this->store(true, 'otra');
        $service = app(AttachmentService::class);

        $mine = $service->store(UploadedFile::fake()->image('a.png'), $store);
        $foreign = $service->store(UploadedFile::fake()->image('b.png'), $other);
        $abandoned = $service->store(UploadedFile::fake()->image('c.png'), $store);
        $order = Order::create(['store_id' => $store->id, 'order_number' => 'TRB-T-1', 'customer_name' => 'Ana', 'total' => 10]);

        $service->attach($mine, $order, $store);
        $this->assertTrue($mine->fresh()->attachable->is($order));
        try {
            $service->attach($foreign, $order, $store);
            $this->fail('A store must not claim another store\'s upload');
        } catch (\DomainException) {
            $this->assertNull($foreign->fresh()->attachable_type);
        }

        Attachment::whereKey([$mine->id, $abandoned->id])->update(['created_at' => now()->subDays(3)]);
        $this->artisan('attachments:prune')->assertSuccessful();

        $this->assertModelExists($mine);
        $this->assertModelExists($foreign, 'Recent uploads survive even when unlinked');
        $this->assertModelMissing($abandoned);
        Storage::disk('local')->assertMissing($abandoned->path);
        Storage::disk('local')->assertExists($mine->path);
    }
}
