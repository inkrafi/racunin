<?php

namespace App\Livewire\Dashboard;

use App\DTOs\CatalogProfileData;
use App\Models\Catalog;
use App\Services\CatalogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Katalog — Racunin')]
class CatalogSettings extends Component
{
    public string $username = '';
    public string $display_name = '';
    public string $bio = '';
    public string $avatar_url = '';
    public string $theme = 'ramuan';
    public array $social_links = [
        'instagram' => '',
        'tiktok' => '',
        'whatsapp' => '',
        'youtube' => '',
        'telegram' => '',
    ];
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50'],
            'display_name' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar_url' => ['nullable', 'string', 'url', 'max:1000'],
            'theme' => ['required', 'string', 'in:' . implode(',', array_keys(Catalog::themes()))],
            'social_links.*' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function mount(CatalogService $catalogService): void
    {
        $user = Auth::user();
        $catalog = $catalogService->getProfileForUser($user->id);

        if ($catalog) {
            $this->username = $catalog->username;
            $this->display_name = $catalog->display_name;
            $this->bio = $catalog->bio ?? '';
            $this->avatar_url = $catalog->avatar_url ?? '';
            $this->theme = $catalog->theme ?? 'ramuan';
            $this->is_active = (bool) $catalog->is_active;

            $links = $catalog->social_links ?? [];
            $this->social_links = array_merge([
                'instagram' => '',
                'tiktok' => '',
                'whatsapp' => '',
                'youtube' => '',
                'telegram' => '',
            ], $links);
        } else {
            // Defaults based on User
            $this->username = Str::slug($user->name) ?: 'affiliate-' . $user->id;
            $this->display_name = $user->name;
            $this->theme = 'ramuan';
        }
    }

    public function saveCatalog(CatalogService $catalogService): void
    {
        $this->validate();

        $user = Auth::user();

        $data = CatalogProfileData::fromArray([
            'username' => $this->username,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'theme' => $this->theme,
            'social_links' => $this->social_links,
            'is_active' => $this->is_active,
        ], $user->id);

        try {
            $catalogService->saveProfile($data);
            $this->dispatch('toast', message: 'Katalog disimpan.');
        } catch (\InvalidArgumentException $e) {
            $this->addError('username', $e->getMessage());
        }
    }

    public function render()
    {
        $user = Auth::user();
        return view('livewire.dashboard.catalog-settings', [
            'user' => $user,
            'publicUrl' => url('@' . ($this->username ?: 'username')),
        ]);
    }
}
