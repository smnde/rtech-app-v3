<?php

namespace App\Providers;

use App\Models\Accessory;
use App\Models\Gadget;
use App\Models\Laptop;
use App\Models\Sparepart;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Schema::defaultStringLength(191);
        JsonResource::withoutWrapping();

        Relation::morphMap([
            'laptop' => Laptop::class,
            'gadget' => Gadget::class,
            'accessory' => Accessory::class,
            'sparepart' => Sparepart::class,
        ]);
    }
}
