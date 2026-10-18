<?php

namespace App\Livewire\Dashboard;

use App\Actions\AI\DiscoverProductAction;
use App\Models\ProductDiscovery as ProductDiscoveryModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Ide produk — Racunin')]
class ProductDiscovery extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'analyze'; // 'analyze' or 'history'

    #[Url]
    public string $search = '';

    #[Url]
    public string $viralFilter = '';

    public string $productInput = '';
    public ?ProductDiscoveryModel $latestDiscovery = null;
    public ?ProductDiscoveryModel $selectedDiscovery = null;
    public bool $showDetailModal = false;

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['analyze', 'history'], true) ? $tab : 'analyze';
        $this->resetPage();
    }

    public function analyze(DiscoverProductAction $discoverAction): void
    {
        $this->validate([
            'productInput' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $userId = Auth::id();
        $rateLimitKey = 'ai-discovery:' . $userId;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->addError('productInput', "Batas permintaan analisis produk tercapai. Harap tunggu {$seconds} detik.");
            return;
        }

        RateLimiter::hit($rateLimitKey, 600);

        try {
            $this->latestDiscovery = $discoverAction->execute($userId, $this->productInput);
            $this->tab = 'analyze';
            $this->productInput = '';
            
            $this->dispatch('toast', message: 'Analisis selesai.');
        } catch (\Exception $e) {
            Log::error('DiscoverProductAction Error: ' . $e->getMessage());
            $this->addError('productInput', 'Analisis belum berhasil karena layanan AI tidak merespons. Coba lagi dalam beberapa saat.');
        }
    }

    public function viewDetail(int $id): void
    {
        $this->selectedDiscovery = ProductDiscoveryModel::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if ($this->selectedDiscovery) {
            $this->showDetailModal = true;
        }
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedDiscovery = null;
    }

    public function deleteHistory(int $id): void
    {
        ProductDiscoveryModel::where('id', $id)->where('user_id', Auth::id())->delete();
        
        if ($this->latestDiscovery && $this->latestDiscovery->id === $id) {
            $this->latestDiscovery = null;
        }

        if ($this->selectedDiscovery && $this->selectedDiscovery->id === $id) {
            $this->closeDetailModal();
        }

        $this->dispatch('toast', message: 'Analisis dihapus.');
    }

    public function render()
    {
        $userId = Auth::id();

        $historyCount = ProductDiscoveryModel::where('user_id', $userId)->count();

        $query = ProductDiscoveryModel::where('user_id', $userId);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('product_input', 'ilike', '%' . $this->search . '%')
                  ->orWhere('target_audience', 'ilike', '%' . $this->search . '%')
                  ->orWhere('pitch_angle', 'ilike', '%' . $this->search . '%')
                  ->orWhere('reasoning', 'ilike', '%' . $this->search . '%');
            });
        }

        if (!empty($this->viralFilter)) {
            $query->where('viral_potential', $this->viralFilter);
        }

        $history = $query->latest()->paginate(9);

        return view('livewire.dashboard.product-discovery', [
            'tab' => $this->tab,
            'search' => $this->search,
            'viralFilter' => $this->viralFilter,
            'latestDiscovery' => $this->latestDiscovery,
            'selectedDiscovery' => $this->selectedDiscovery,
            'showDetailModal' => $this->showDetailModal,
            'history' => $history,
            'historyCount' => $historyCount,
        ]);
    }
}
