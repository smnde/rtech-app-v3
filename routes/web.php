<?php

use App\Http\Controllers\AccesoriesController;
use App\Http\Controllers\BrandsController;
use App\Http\Controllers\CartsController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\CustomerNotesController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpensesController;
use App\Http\Controllers\GadgetsController;
use App\Http\Controllers\ItemLogController;
use App\Http\Controllers\LaptopsController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\PriceTagController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchasesController;
use App\Http\Controllers\ReparationsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RolePermissionsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SparepartsController;
use App\Http\Controllers\StockMovementsController;
use App\Http\Controllers\SuppliersController;
use App\Http\Controllers\UsersController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Halaman utama
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Semua route yang butuh autentikasi
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::patch('/stock/laptop/{laptop}/price', [LaptopsController::class, 'updatePrice'])->middleware('permission:edit laptop')->name('stock.laptop.price');
    Route::patch('/stock/laptop/{laptop}/post', [LaptopsController::class, 'markPosted'])->middleware('permission:edit laptop')->name('stock.laptop.post');
    Route::post('/stock/laptop/{laptop}/logs', [ItemLogController::class, 'store'])->middleware('permission:edit laptop')->name('stock.laptop.logs.store');
    Route::post('/stock/laptop/{laptop}/issues', [ItemLogController::class, 'storeIssue'])->middleware('permission:edit laptop')->name('stock.laptop.issues.store');
    Route::patch('/stock/laptop/{laptop}/issues/{issueId}', [ItemLogController::class, 'toggleIssue'])->middleware('permission:edit laptop')->name('stock.laptop.issues.toggle');
    Route::delete('/item-logs/{itemLog}', [ItemLogController::class, 'destroy'])->name('item-logs.destroy');

    // Profil (semua user, ubah nama sendiri)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // === Master Data ===
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/category', [CategoriesController::class, 'index'])->middleware('permission:show category')->name('category');
        Route::post('/category', [CategoriesController::class, 'store'])->middleware('permission:add category')->name('category.store');
        Route::put('/category/{category}', [CategoriesController::class, 'update'])->middleware('permission:edit category')->name('category.update');
        Route::delete('/category/{category}', [CategoriesController::class, 'destroy'])->middleware('permission:delete category')->name('category.destroy');

        Route::get('/brand', [BrandsController::class, 'index'])->middleware('permission:show brand')->name('brand');
        Route::post('/brand', [BrandsController::class, 'store'])->middleware('permission:add brand')->name('brand.store');
        Route::put('/brand/{brand}', [BrandsController::class, 'update'])->middleware('permission:edit brand')->name('brand.update');
        Route::delete('/brand/{brand}', [BrandsController::class, 'destroy'])->middleware('permission:delete brand')->name('brand.destroy');

        Route::get('/supplier', [SuppliersController::class, 'index'])->middleware('permission:show supplier')->name('supplier');
        Route::post('/supplier', [SuppliersController::class, 'store'])->middleware('permission:add supplier')->name('supplier.store');
        Route::put('/supplier/{supplier}', [SuppliersController::class, 'update'])->middleware('permission:edit supplier')->name('supplier.update');
        Route::delete('/supplier/{supplier}', [SuppliersController::class, 'destroy'])->middleware('permission:delete supplier')->name('supplier.destroy');
    });

    // === Data Stok ===
    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/laptop', [LaptopsController::class, 'index'])->middleware('permission:show laptop')->name('laptop');
        Route::get('/laptop/{laptop}', [LaptopsController::class, 'show'])->middleware('permission:show laptop')->name('laptop.show');
        Route::post('/laptop', [LaptopsController::class, 'store'])->middleware('permission:add laptop')->name('laptop.store');
        Route::put('/laptop/{laptop}', [LaptopsController::class, 'update'])->middleware('permission:edit laptop')->name('laptop.update');
        Route::delete('/laptop/{laptop}', [LaptopsController::class, 'destroy'])->middleware('permission:delete laptop')->name('laptop.destroy');

        Route::get('/gadget', [GadgetsController::class, 'index'])->middleware('permission:show gadget')->name('gadget');
        Route::get('/gadget/{gadget}', [GadgetsController::class, 'show'])->middleware('permission:show gadget')->name('gadget.show');
        Route::post('/gadget', [GadgetsController::class, 'store'])->middleware('permission:add gadget')->name('gadget.store');
        Route::put('/gadget/{gadget}', [GadgetsController::class, 'update'])->middleware('permission:edit gadget')->name('gadget.update');
        Route::delete('/gadget/{gadget}', [GadgetsController::class, 'destroy'])->middleware('permission:delete gadget')->name('gadget.destroy');

        Route::get('/aksesoris', [AccesoriesController::class, 'index'])->middleware('permission:show aksesoris')->name('aksesoris');
        Route::get('/aksesoris/{accessory}', [AccesoriesController::class, 'show'])->middleware('permission:show aksesoris')->name('aksesoris.show');
        Route::post('/aksesoris', [AccesoriesController::class, 'store'])->middleware('permission:add aksesoris')->name('aksesoris.store');
        Route::put('/aksesoris/{accessory}', [AccesoriesController::class, 'update'])->middleware('permission:edit aksesoris')->name('aksesoris.update');
        Route::delete('/aksesoris/{accessory}', [AccesoriesController::class, 'destroy'])->middleware('permission:delete aksesoris')->name('aksesoris.destroy');

        Route::get('/sparepart', [SparepartsController::class, 'index'])->middleware('permission:show sparepart')->name('sparepart');
        Route::get('/sparepart/{sparepart}', [SparepartsController::class, 'show'])->middleware('permission:show sparepart')->name('sparepart.show');
        Route::post('/sparepart', [SparepartsController::class, 'store'])->middleware('permission:add sparepart')->name('sparepart.store');
        Route::put('/sparepart/{sparepart}', [SparepartsController::class, 'update'])->middleware('permission:edit sparepart')->name('sparepart.update');
        Route::delete('/sparepart/{sparepart}', [SparepartsController::class, 'destroy'])->middleware('permission:delete sparepart')->name('sparepart.destroy');
    });

    // === User & Customer ===
    Route::get('/users', [UsersController::class, 'index'])->middleware('permission:show user')->name('users.index');
    Route::post('/users', [UsersController::class, 'store'])->middleware('permission:add user')->name('users.store');
    Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UsersController::class, 'destroy'])->middleware('permission:delete user')->name('users.destroy');

    Route::middleware('role:SYSADMIN')->group(function () {
        Route::get('/roles', [RolesController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RolesController::class, 'store'])->name('roles.store');
        Route::delete('/roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');

        Route::get('/permissions', [PermissionsController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [PermissionsController::class, 'store'])->name('permissions.store');

        Route::get('/set-permission', [RolePermissionsController::class, 'index'])->name('set-permission.index');
        Route::put('/set-permission/{role}', [RolePermissionsController::class, 'update'])->name('set-permission.update');
    });

    Route::get('/price-tag', [PriceTagController::class, 'index'])->middleware('permission:show laptop')->name('price-tag');

    Route::get('/customers', [CustomersController::class, 'index'])->middleware('permission:show customer')->name('customers.index');
    Route::post('/customers', [CustomersController::class, 'store'])->middleware('permission:add customer')->name('customers.store');
    Route::put('/customers/{customer}', [CustomersController::class, 'update'])->middleware('permission:edit customer')->name('customers.update');
    Route::delete('/customers/{customer}', [CustomersController::class, 'destroy'])->middleware('permission:delete customer')->name('customers.destroy');
    Route::get('/customers/{customer}', [CustomersController::class, 'show'])->middleware('permission:show customer')->name('customers.show');
    Route::post('/customers/{customer}/notes', [CustomerNotesController::class, 'store'])->middleware('permission:edit customer')->name('customers.notes.store');
    Route::put('/customers/{customer}/notes/{note}', [CustomerNotesController::class, 'update'])->middleware('permission:edit customer')->name('customers.notes.update');
    Route::delete('/customers/{customer}/notes/{note}', [CustomerNotesController::class, 'destroy'])->middleware('permission:edit customer')->name('customers.notes.destroy');

    // === Servis ===
    Route::get('/servis', [ReparationsController::class, 'index'])->middleware('permission:show reparation')->name('servis.index');
    Route::get('/servis/{reparation}', [ReparationsController::class, 'show'])->middleware('permission:show reparation')->name('servis.show');
    Route::post('/servis', [ReparationsController::class, 'store'])->middleware('permission:add reparation')->name('servis.store');
    Route::put('/servis/{reparation}', [ReparationsController::class, 'update'])->middleware('permission:edit reparation')->name('servis.update');
    Route::patch('/servis/{reparation}/konfirmasi', [ReparationsController::class, 'confirm'])->middleware('permission:edit reparation')->name('servis.confirm');
    Route::patch('/servis/{reparation}/status', [ReparationsController::class, 'changeStatus'])->middleware('permission:edit reparation')->name('servis.status');
    Route::delete('/servis/{reparation}', [ReparationsController::class, 'destroy'])->middleware('permission:delete reparation')->name('servis.destroy');

    // === Transaksi ===
    Route::prefix('transaksi')->name('transaksi.')->group(function () {
        Route::get('/penjualan', [SalesController::class, 'index'])->middleware('permission:show sale')->name('penjualan');
        Route::get('/penjualan/create', [SalesController::class, 'create'])->middleware('permission:add sale')->name('penjualan.create');
        Route::post('/penjualan', [SalesController::class, 'store'])->middleware('permission:add sale')->name('penjualan.store');
        Route::get('/pembelian', [PurchasesController::class, 'index'])->middleware('permission:show purchase')->name('pembelian');
        Route::get('/pembelian/create', [PurchasesController::class, 'create'])->middleware('permission:add purchase')->name('pembelian.create');
        Route::post('/pembelian', [PurchasesController::class, 'store'])->middleware('permission:add purchase')->name('pembelian.store');

        Route::prefix('keranjang/{type}')->whereIn('type', ['sale', 'purchase'])->name('keranjang.')->group(function () {
            Route::get('/', [CartsController::class, 'show'])->name('show');
            Route::get('/hold', [CartsController::class, 'held'])->name('held');
            Route::put('/customer', [CartsController::class, 'updateCustomer'])->name('customer');
            Route::post('/item', [CartsController::class, 'addItem'])->name('item.store');
            Route::post('/hold', [CartsController::class, 'hold'])->name('hold');
        });
        Route::put('/keranjang/item/{item}', [CartsController::class, 'updateItem'])->name('keranjang.item.update');
        Route::delete('/keranjang/item/{item}', [CartsController::class, 'destroyItem'])->name('keranjang.item.destroy');
        Route::post('/keranjang/hold/{cart}/resume', [CartsController::class, 'resume'])->name('keranjang.resume');
        Route::delete('/keranjang/hold/{cart}', [CartsController::class, 'destroy'])->name('keranjang.destroy');
    });

    // === Pengeluaran ===
    Route::get('/pengeluaran', [ExpensesController::class, 'index'])->middleware('permission:show expense')->name('expenses.index');
    Route::post('/pengeluaran', [ExpensesController::class, 'store'])->middleware('permission:add expense')->name('expenses.store');
    Route::put('/pengeluaran/{expense}', [ExpensesController::class, 'update'])->middleware('permission:edit expense')->name('expenses.update');
    Route::delete('/pengeluaran/{expense}', [ExpensesController::class, 'destroy'])->middleware('permission:delete expense')->name('expenses.destroy');

    // === Mutasi Stok ===
    Route::get('/mutasi-stok', [StockMovementsController::class, 'index'])->middleware('permission:show stock_movement')->name('stock-movements.index');
    Route::post('/mutasi-stok', [StockMovementsController::class, 'store'])->middleware('permission:add stock_movement')->name('stock-movements.store');

    // === Laporan ===
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/penjualan', [ReportsController::class, 'sales'])->middleware('permission:show report')->name('penjualan');
        Route::get('/servis', [ReportsController::class, 'servis'])->middleware('permission:show report')->name('servis');
        Route::get('/pembelian', [ReportsController::class, 'pembelian'])->middleware('permission:show report')->name('pembelian');
        Route::get('/pengeluaran', [ReportsController::class, 'pengeluaran'])->middleware('permission:show report')->name('pengeluaran');
        Route::get('/mutasi', [ReportsController::class, 'mutasi'])->middleware('permission:show report')->name('mutasi');
    });

});

require __DIR__.'/auth.php';
