<?php

namespace App\Http\Controllers;

use App\Http\Requests\Promo\PromoRequest;
use App\Models\Promo;
use App\Support\LikePattern;
use App\Support\StockItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PromosController extends Controller
{
    public function index(Request $request): Response
    {
        $promos = Promo::query()
            ->when($request->search, fn ($query, $search) => $query->where('title', 'like', LikePattern::contains($search)))
            ->latest('starts_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Promo $promo) => [
                'id' => $promo->id,
                'title' => $promo->title,
                'type' => $promo->type,
                'value' => (float) $promo->value,
                'starts_at' => $promo->starts_at?->toDateString(),
                'ends_at' => $promo->ends_at?->toDateString(),
                'is_active' => $promo->is_active,
                'is_running' => $this->isRunning($promo),
                'item_types' => $promo->item_types ?? [],
            ]);

        return inertia('Promo/Index', [
            'promos' => $promos,
            'filters' => ['search' => $request->search],
            'itemTypeOptions' => array_keys(StockItem::types()),
        ]);
    }

    public function store(PromoRequest $request): RedirectResponse
    {
        Promo::create($this->payload($request));

        return back()->with('success', 'Promo berhasil ditambahkan.');
    }

    public function update(PromoRequest $request, Promo $promo): RedirectResponse
    {
        $promo->update($this->payload($request));

        return back()->with('success', 'Promo berhasil diperbarui.');
    }

    public function destroy(Promo $promo): RedirectResponse
    {
        // Judul sudah di-snapshot di nota, jadi menghapus promo tidak
        // mengubah riwayat transaksi.
        $promo->delete();

        return back()->with('success', 'Promo berhasil dihapus.');
    }

    private function isRunning(Promo $promo): bool
    {
        $today = now()->toDateString();

        return $promo->is_active
            && $promo->starts_at?->toDateString() <= $today
            && $promo->ends_at?->toDateString() >= $today;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PromoRequest $request): array
    {
        return [
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
