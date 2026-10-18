<?php

namespace App\Livewire\Dashboard;

use App\Actions\Commissions\ImportCommissionReportAction;
use App\Actions\Commissions\MatchConversionsAction;
use App\Models\Link;
use App\Services\CommissionService;
use App\Support\CommissionReportReader;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Commission per link: import an affiliate report (CSV), check the detected
 * columns, then see orders and commission next to clicks.
 */
#[Layout('components.layouts.app')]
#[Title('Komisi — Racunin')]
class Commissions extends Component
{
    use WithFileUploads;

    public const PLATFORMS = [
        'shopee' => 'Shopee',
        'tiktok' => 'TikTok Shop / Tokopedia',
        'lazada' => 'Lazada',
        'other' => 'Lainnya',
    ];

    /** 0 = all time */
    #[Url]
    public int $days = 30;

    public string $platform = 'shopee';

    public $report = null;

    /** Column review step, filled after a file is read */
    public array $headers = [];
    public array $mapping = [];
    public array $sampleRow = [];
    public int $rowCount = 0;

    /** md5(unmatched product group) => chosen link id */
    public array $assign = [];

    public function setDays(int $days): void
    {
        $this->days = in_array($days, [0, 30, 90], true) ? $days : 30;
    }

    /**
     * Read the uploaded file as soon as it is chosen and propose a column mapping.
     */
    public function updatedReport(): void
    {
        $reader = app(CommissionReportReader::class);
        $this->resetErrorBag('report');
        $this->validate([
            'report' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $data = $reader->read($this->report->getRealPath(), ImportCommissionReportAction::MAX_ROWS);

        if (count($data['headers']) < 2 || $data['rows'] === []) {
            $this->addError('report', 'File ini tidak berisi tabel pesanan. Ekspor laporan sebagai CSV dari dashboard affiliate, lalu coba lagi.');
            $this->cancelImport();
            return;
        }

        $this->headers = $data['headers'];
        $this->mapping = array_map(fn ($index) => $index === null ? '' : (string) $index, $data['mapping']);
        $this->sampleRow = $data['rows'][0];
        $this->rowCount = count($data['rows']);
    }

    public function import(CommissionReportReader $reader, ImportCommissionReportAction $action): void
    {
        if (! $this->report || $this->headers === []) {
            $this->addError('report', 'Pilih file laporan dulu.');
            return;
        }

        $this->validate([
            'platform' => ['required', 'in:' . implode(',', array_keys(self::PLATFORMS))],
            'report' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $mapping = [];
        foreach (CommissionReportReader::FIELDS as $field) {
            $index = $this->mapping[$field] ?? '';
            $mapping[$field] = is_numeric($index) && isset($this->headers[(int) $index]) ? (int) $index : null;
        }

        foreach (CommissionReportReader::REQUIRED_FIELDS as $field) {
            if ($mapping[$field] === null) {
                $this->addError('mapping.' . $field, 'Pilih kolom untuk ' . $this->fieldLabels()[$field] . '.');
                return;
            }
        }

        $rateKey = 'commission-import:' . Auth::id();
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $this->addError('report', 'Terlalu banyak impor. Coba lagi dalam ' . RateLimiter::availableIn($rateKey) . ' detik.');
            return;
        }
        RateLimiter::hit($rateKey, 600);

        $data = $reader->read($this->report->getRealPath(), ImportCommissionReportAction::MAX_ROWS);
        $import = $action->execute(Auth::id(), $this->platform, $this->report->getClientOriginalName(), $data['rows'], $mapping);

        $this->cancelImport();
        $this->dispatch('toast', message: "{$import->rows_imported} pesanan diimpor, {$import->rows_matched} cocok dengan link kamu.");
    }

    public function cancelImport(): void
    {
        $this->reset(['report', 'headers', 'mapping', 'sampleRow', 'rowCount']);
    }

    public function rematch(MatchConversionsAction $action): void
    {
        $matched = $action->execute(Auth::id());

        $this->dispatch('toast', message: $matched > 0
            ? "{$matched} pesanan berhasil dicocokkan."
            : 'Tidak ada pesanan baru yang cocok. Isi Sub ID di link, atau pilih link secara manual.');
    }

    public function assignGroup(string $groupKey, CommissionService $service): void
    {
        $linkId = (int) ($this->assign[md5($groupKey)] ?? 0);
        $link = Link::forUser(Auth::id())->find($linkId);

        if (! $link) {
            $this->dispatch('toast', message: 'Pilih link dulu.');
            return;
        }

        $count = $service->assignGroup(Auth::id(), $groupKey, $link);
        unset($this->assign[md5($groupKey)]);

        $this->dispatch('toast', message: "{$count} pesanan dihubungkan ke /{$link->slug}.");
    }

    /**
     * @return array<string, string>
     */
    public function fieldLabels(): array
    {
        return [
            'order_id' => 'ID pesanan',
            'commission' => 'Komisi',
            'status' => 'Status pesanan',
            'sub_id' => 'Sub ID',
            'item_ref' => 'ID produk',
            'product_name' => 'Nama produk',
            'order_value' => 'Nilai pembelian',
            'ordered_at' => 'Waktu pesanan',
        ];
    }

    public function render(CommissionService $service)
    {
        $userId = Auth::id();
        $days = $this->days === 0 ? null : $this->days;

        return view('livewire.dashboard.commissions', [
            'hasData' => $service->hasConversions($userId),
            'summary' => $service->summary($userId, $days),
            'perLink' => $service->perLink($userId, $days),
            'unmatched' => $service->unmatched($userId),
            'unmatchedCount' => $service->unmatchedCount($userId),
            'imports' => $service->recentImports($userId),
            'links' => Link::forUser($userId)->latest()->get(['id', 'title', 'slug']),
            'fieldLabels' => $this->fieldLabels(),
        ]);
    }
}
