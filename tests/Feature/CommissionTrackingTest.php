<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Dashboard\Commissions;
use App\Models\Conversion;
use App\Models\Link;
use App\Models\LinkClickSummary;
use App\Models\User;
use App\Services\CommissionService;
use App\Support\CommissionReportReader;
use App\Support\ProductRef;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CommissionTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Link $serum;
    protected Link $tws;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->user = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $this->serum = Link::create([
            'user_id' => $this->user->id,
            'title' => 'Serum Niacinamide',
            'original_url' => 'https://s.shopee.co.id/AbCd',
            'slug' => 'serum-glowing',
            'sub_id' => 'SerumGlowing',
        ]);
        $this->tws = Link::create([
            'user_id' => $this->user->id,
            'title' => 'TWS Bluetooth',
            'original_url' => 'https://shopee.co.id/TWS-Bluetooth-i.555.98765',
            'slug' => 'tws-murah',
        ]);
    }

    protected function shopeeCsv(array $rows): UploadedFile
    {
        $lines = ['ID Pesanan,Status Pesanan,Waktu Pesanan,Item ID,Nama Item,Harga(Rp),Komisi Bersih Affiliate(Rp),Sub_id1'];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($v) => str_contains((string) $v, ',') ? '"' . $v . '"' : $v, $row));
        }

        return UploadedFile::fake()->createWithContent('laporan-shopee.csv', implode("\n", $lines));
    }

    protected function import(UploadedFile $file)
    {
        return Livewire::actingAs($this->user)
            ->test(Commissions::class)
            ->set('platform', 'shopee')
            ->set('report', $file)
            ->call('import');
    }

    public function test_product_ref_and_sub_id_are_read_from_urls(): void
    {
        $this->assertSame('6101928372', ProductRef::fromUrl('https://shopee.co.id/Somethinc-Serum-i.187313328.6101928372?sp_atk=x'));
        $this->assertSame('22', ProductRef::fromUrl('https://shopee.co.id/product/11/22'));
        $this->assertSame('1729384756123', ProductRef::fromUrl('https://shop-id.tokopedia.com/view/product/1729384756123?region=ID'));
        $this->assertSame('4567890', ProductRef::fromUrl('https://www.lazada.co.id/products/sepatu-i4567890-s1234.html'));
        $this->assertNull(ProductRef::fromUrl('https://s.shopee.co.id/AbCd'));

        $this->assertSame('tiktokbio', ProductRef::subIdFromUrl('https://shopee.co.id/x-i.1.2?utm_source=an_1234&utm_medium=affiliates&utm_content=TikTokBio--igstory--'));
        $this->assertSame('promo1', ProductRef::subIdFromUrl('https://invol.co/cl123?aff_sub=promo1'));
        $this->assertNull(ProductRef::subIdFromUrl('https://shopee.co.id/x-i.1.2?utm_content=abc'));

        // Saving a link derives both
        $this->assertSame('98765', $this->tws->product_ref);
        $this->assertSame('serumglowing', $this->serum->sub_id);
    }

    public function test_amounts_statuses_and_columns_are_understood(): void
    {
        $this->assertSame(12500.0, CommissionReportReader::parseAmount('Rp 12.500'));
        $this->assertSame(12500.0, CommissionReportReader::parseAmount('12,500'));
        $this->assertSame(1234.56, CommissionReportReader::parseAmount('1.234,56'));
        $this->assertSame(1234.56, CommissionReportReader::parseAmount('1234.56'));
        $this->assertSame(0.0, CommissionReportReader::parseAmount('-'));

        $this->assertSame('completed', CommissionReportReader::normalizeStatus('Selesai'));
        $this->assertSame('cancelled', CommissionReportReader::normalizeStatus('Dibatalkan'));
        $this->assertSame('cancelled', CommissionReportReader::normalizeStatus('Invalid'));
        $this->assertSame('pending', CommissionReportReader::normalizeStatus('Tertunda'));

        $reader = new CommissionReportReader();
        $mapping = $reader->detectMapping(['Order ID', 'Order Status', 'Order Time', 'Product ID', 'Product Name', 'Purchase Value', 'Estimated Commission', 'Sub ID']);
        $this->assertSame([0, 4, 2, 7, 6, 3, 1, 5], [
            $mapping['order_id'], $mapping['product_name'], $mapping['ordered_at'], $mapping['sub_id'],
            $mapping['commission'], $mapping['item_ref'], $mapping['status'], $mapping['order_value'],
        ]);
    }

    public function test_reader_handles_semicolons_and_title_lines(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rpt');
        file_put_contents($path, "\xEF\xBB\xBFLaporan Konversi Affiliate\nPeriode: 1-31 Okt\n\nID Pesanan;Komisi;Status\nA1;\"1.500\";Selesai\nA2;2.000;Batal\n");

        $data = (new CommissionReportReader())->read($path, 100);
        unlink($path);

        $this->assertSame(['ID Pesanan', 'Komisi', 'Status'], $data['headers']);
        $this->assertCount(2, $data['rows']);
        $this->assertSame(0, $data['mapping']['order_id']);
        $this->assertSame(1, $data['mapping']['commission']);
    }

    public function test_import_matches_orders_by_sub_id_then_item_id(): void
    {
        $this->import($this->shopeeCsv([
            ['2410AAA', 'Selesai', '2026-10-15 10:00:00', '111', 'Serum Niacinamide 20ml', '115.000', '8.050', 'serumglowing'],
            ['2410BBB', 'Tertunda', '2026-10-16 11:30:00', '98765', 'TWS Bluetooth 5.3', '89.000', '4.450', ''],
            ['2410CCC', 'Tertunda', '2026-10-17 09:00:00', '424242', 'Lampu Meja LED', '45.000', '2.250', ''],
            ['2410DDD', 'Dibatalkan', '2026-10-17 12:00:00', '98765', 'TWS Bluetooth 5.3', '89.000', '4.450', ''],
        ]))->assertHasNoErrors()->assertDispatched('toast', message: '4 pesanan diimpor, 3 cocok dengan link kamu.');

        $this->assertSame($this->serum->id, Conversion::where('order_id', '2410AAA')->value('link_id'));
        $this->assertSame($this->tws->id, Conversion::where('order_id', '2410BBB')->value('link_id'));
        $this->assertNull(Conversion::where('order_id', '2410CCC')->value('link_id'));
        $this->assertEquals(8050, Conversion::where('order_id', '2410AAA')->value('commission'));
        $this->assertSame('cancelled', Conversion::where('order_id', '2410DDD')->value('status'));
    }

    public function test_reimport_updates_status_without_duplicates_and_keeps_manual_assignment(): void
    {
        $this->import($this->shopeeCsv([
            ['2410BBB', 'Tertunda', '2026-10-16 11:30:00', '98765', 'TWS Bluetooth 5.3', '89.000', '4.450', ''],
            ['2410CCC', 'Tertunda', '2026-10-17 09:00:00', '424242', 'Lampu Meja LED', '45.000', '2.250', ''],
        ]));

        // Manually link the lamp to the serum link (e.g. a bundle), which also teaches the link its item ID
        $assigned = app(CommissionService::class)->assignGroup($this->user->id, 'i:424242', $this->tws->fresh());
        $this->assertSame(1, $assigned);
        $this->assertSame('98765', $this->tws->fresh()->product_ref, 'An existing product ref is not overwritten');

        $this->import($this->shopeeCsv([
            ['2410BBB', 'Selesai', '2026-10-16 11:30:00', '98765', 'TWS Bluetooth 5.3', '89.000', '4.450', ''],
            ['2410CCC', 'Selesai', '2026-10-17 09:00:00', '424242', 'Lampu Meja LED', '45.000', '2.250', ''],
        ]));

        $this->assertSame(2, Conversion::count());
        $this->assertSame('completed', Conversion::where('order_id', '2410BBB')->value('status'));
        $this->assertSame($this->tws->id, Conversion::where('order_id', '2410CCC')->value('link_id'));
    }

    public function test_assigning_a_group_from_the_page_learns_the_product(): void
    {
        $lamp = Link::create([
            'user_id' => $this->user->id,
            'title' => 'Lampu Meja',
            'original_url' => 'https://s.shopee.co.id/Lamp',
            'slug' => 'lampu-meja',
        ]);

        $this->import($this->shopeeCsv([
            ['2410CCC', 'Tertunda', '2026-10-17 09:00:00', '424242', 'Lampu Meja LED', '45.000', '2.250', ''],
        ]))
            ->set('assign.' . md5('i:424242'), $lamp->id)
            ->call('assignGroup', 'i:424242')
            ->assertDispatched('toast', message: '1 pesanan dihubungkan ke /lampu-meja.');

        $this->assertSame('424242', $lamp->fresh()->product_ref);
        $this->assertSame($lamp->id, Conversion::value('link_id'));
    }

    public function test_cannot_assign_orders_to_another_users_link(): void
    {
        $other = User::factory()->create(['role' => UserRole::AFFILIATE]);
        $foreign = Link::create([
            'user_id' => $other->id,
            'title' => 'Bukan punyamu',
            'original_url' => 'https://shopee.co.id/x-i.9.9',
            'slug' => 'foreign-commission',
        ]);

        $this->import($this->shopeeCsv([
            ['2410CCC', 'Tertunda', '2026-10-17 09:00:00', '424242', 'Lampu Meja LED', '45.000', '2.250', ''],
        ]))
            ->set('assign.' . md5('i:424242'), $foreign->id)
            ->call('assignGroup', 'i:424242')
            ->assertDispatched('toast', message: 'Pilih link dulu.');

        $this->assertNull(Conversion::value('link_id'));
    }

    public function test_summary_and_per_link_numbers(): void
    {
        $this->travelTo('2026-10-18 12:00:00');

        $this->import($this->shopeeCsv([
            ['2410AAA', 'Selesai', '2026-10-15 10:00:00', '111', 'Serum', '115.000', '8.050', 'serumglowing'],
            ['2410EEE', 'Tertunda', '2026-10-16 10:00:00', '111', 'Serum', '115.000', '8.050', 'serumglowing'],
            ['2410DDD', 'Dibatalkan', '2026-10-17 12:00:00', '98765', 'TWS', '89.000', '4.450', ''],
        ]));

        LinkClickSummary::create(['link_id' => $this->serum->id, 'user_id' => $this->user->id, 'date' => '2026-10-15', 'total_clicks' => 70, 'unique_clicks' => 60]);
        LinkClickSummary::create(['link_id' => $this->tws->id, 'user_id' => $this->user->id, 'date' => '2026-10-16', 'total_clicks' => 30, 'unique_clicks' => 25]);

        $service = app(CommissionService::class);
        $summary = $service->summary($this->user->id, 30);

        $this->assertSame(8050.0, $summary['completed']);
        $this->assertSame(8050.0, $summary['pending']);
        $this->assertSame(2, $summary['orders']);
        $this->assertSame(1, $summary['cancelled']);
        $this->assertSame(100, $summary['clicks']);
        $this->assertSame(161.0, $summary['per_click']);

        $perLink = $service->perLink($this->user->id, 30);
        $this->assertCount(1, $perLink, 'Cancelled orders do not create a row');
        $this->assertSame($this->serum->id, $perLink[0]['link']->id);
        $this->assertSame(70, $perLink[0]['clicks']);
        $this->assertSame(230.0, $perLink[0]['per_click']);

        // Other users never see these numbers
        $this->assertSame(0, $service->summary(User::factory()->create()->id, 30)['orders']);
    }

    public function test_file_without_required_columns_asks_for_mapping(): void
    {
        $file = UploadedFile::fake()->createWithContent('aneh.csv', "Kolom A,Kolom B\nx,y\n");

        Livewire::actingAs($this->user)
            ->test(Commissions::class)
            ->set('report', $file)
            ->assertSet('rowCount', 1)
            ->call('import')
            ->assertHasErrors(['mapping.order_id']);

        $this->assertSame(0, Conversion::count());
    }

    public function test_commission_page_renders(): void
    {
        $this->actingAs($this->user)->get('/dashboard/komisi')
            ->assertOk()
            ->assertSee('Impor laporan')
            ->assertSee('Supaya pesanan cocok otomatis');
    }
}
