<?php

namespace Tests\Feature;

use App\Actions\Links\ExtractProductMetadataAction;
use App\Enums\UserRole;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BulkUrlExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_product_metadata_action_parses_html_successfully(): void
    {
        Http::fake([
            'https://shopee.co.id/serum-niacinamide*' => Http::response('
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Jual [COD] Serum Niacinamide Somethinc 20ml Original Murah | Shopee Indonesia</title>
                    <meta property="og:title" content="Jual [COD] Serum Niacinamide Somethinc 20ml Original Murah | Shopee Indonesia" />
                    <meta property="og:description" content="Serum pencerah kulit wajah dengan kandungan niacinamide 10% dan zinc. Hemat 30% hari ini." />
                    <meta property="og:image" content="https://down-id.img.susercontent.com/file/serum.jpg" />
                </head>
                <body>
                    <span class="price">Rp 115.000</span>
                </body>
                </html>
            ', 200),
        ]);

        $action = new ExtractProductMetadataAction();
        $result = $action->execute('https://shopee.co.id/serum-niacinamide-i.12345.67890');

        $this->assertSame('Serum Niacinamide Somethinc 20ml Original Murah', $result['title']);
        $this->assertSame('https://down-id.img.susercontent.com/file/serum.jpg', $result['og_image_url']);
        $this->assertSame('Skincare & Kecantikan', $result['category']);
        $this->assertSame('Hemat 30%', $result['discount_tag']);
        $this->assertNotEmpty($result['suggested_slug']);
    }

    public function test_overview_single_auto_extract(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Http::fake([
            'https://shopee.co.id/kaos-oversize*' => Http::response('
                <!DOCTYPE html>
                <html>
                <head>
                    <meta property="og:title" content="Kaos Oversize Pria Cotton Combed 24s | Shopee Indonesia" />
                    <meta property="og:description" content="Baju kaos oversize distro kekinian bahan katun adem." />
                    <meta property="og:image" content="https://down-id.img.susercontent.com/file/kaos.jpg" />
                </head>
                </html>
            ', 200),
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\Overview::class)
            ->set('original_url', 'https://shopee.co.id/kaos-oversize-pria')
            ->call('autoExtractSingleUrl')
            ->assertSet('title', 'Kaos Oversize Pria Cotton Combed 24s')
            ->assertSet('og_image_url', 'https://down-id.img.susercontent.com/file/kaos.jpg')
            ->assertSet('category', 'Fashion & Pakaian')
            ->assertNotSet('slug', '');
    }

    public function test_overview_bulk_import_extract_and_save(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => UserRole::AFFILIATE]);

        Http::fake([
            'https://shopee.co.id/item-1*' => Http::response('
                <html><head>
                    <meta property="og:title" content="Earphone TWS Bluetooth 5.3 Bass | Shopee" />
                    <meta property="og:image" content="https://img.com/tws.jpg" />
                </head></html>
            ', 200),
            'https://shopee.co.id/item-2*' => Http::response('
                <html><head>
                    <meta property="og:title" content="Lampu Meja Belajar LED Aesthetic | Shopee" />
                    <meta property="og:image" content="https://img.com/lampu.jpg" />
                </head></html>
            ', 200),
        ]);

        $bulkInput = "https://shopee.co.id/item-1\nhttps://shopee.co.id/item-2";

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\BulkImport::class)
            ->dispatch('open-bulk-import')
            ->assertSet('show', true)
            ->set('urlsInput', $bulkInput)
            ->call('process')
            ->assertCount('items', 2)
            ->call('save')
            ->assertSet('show', false)
            ->assertDispatched('links-imported')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('links', [
            'user_id' => $user->id,
            'title' => 'Earphone TWS Bluetooth 5.3 Bass',
            'category' => 'Elektronik & Gadget',
        ]);

        $this->assertDatabaseHas('links', [
            'user_id' => $user->id,
            'title' => 'Lampu Meja Belajar LED Aesthetic',
            'category' => 'Rumah Tangga & Dekor',
        ]);
    }
}
