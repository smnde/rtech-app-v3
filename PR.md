# PR: Perbaikan Performa & UI Mobile

## Sudah dikerjakan

### Performa
- **N+1 keranjang** — `CartItem::product()` diubah jadi morph relation; `items.product` di-eager load di Sales, Purchases, Carts. `CartResource` akses relasi sekali per item (sebelumnya 3x query per item).
- **N+1 list servis** — `Reparation::confirmedTotal()` menghitung dari relasi `stages.items` yang sudah dimuat. Tidak ada lagi query `sum` per baris di index, show, dan Laporan Servis.
- **Dashboard** — agregat today digabung jadi 1 query per tabel, cash flow in/out 1 query, `whereDate` diganti `whereBetween`, list `needPrice`/`needPost` dibatasi 100 baris.
- **Indeks DB** — migration `2026_10_18_120000_add_performance_indexes.php`:
  `sales(created_at)`, `sales(payment_status, created_at)`, `purchases(created_at)`,
  `reparations(status, created_at)`, `reparations(process_deadline_at)`, `reparations(check_deadline_at)`,
  `cash_flows(type, flow_date)`, `cash_flows(flow_date)`,
  `stock_movements(reference)`, `stock_movements(movement_date)`.
  **Perlu dijalankan:** `php artisan migrate`.
- **Filter tanggal** — `whereDate(created_at)` diganti perbandingan range (`"{$from} 00:00:00"` / `"{$to} 23:59:59"`), `flow_date`/`movement_date` pakai perbandingan langsung, supaya indeks terpakai.
- **Bundle** — `jspdf` diubah jadi dynamic import di `LaporanPageBase.vue` (hanya dimuat saat tombol PDF ditekan).
- `StockMovement::$dateFormat = 'Y-m-d'` agar `movement_date` tersimpan sebagai date polos (filter range benar).

### Nota / PDF Kirim ke WA
- Nota digambar vektor langsung ke jsPDF (tanpa html2canvas) — tidak lagi terpotong di mobile.
- Ukuran halaman 210mm × tinggi isi (orientation landscape untuk menjaga dimensi agar tidak ditukar jsPDF).
- Logo asli dari `/icons/icon-192.png` (sebelumnya placeholder "R").
- Footer servis jadi bullet ringkas yang di-wrap.

### UI Mobile
- Pagination mobile ringkas (`< 1 / 12 >`) + teks "Menampilkan X–Y dari Z".
- `AppTagInput`: tombol `+` untuk menambah pill (mobile tanpa Enter) — dipakai di Servis, Laptop, Gadget.
- Validasi FE `CPU, GPU, Memory, Storage, Display` wajib diisi di form Laptop.
- Detail Servis: tombol header dirapikan, aksi sekunder masuk menu `...`.
- Modal Konfirmasi & Rincian Biaya tidak overflow di mobile.
- Card list Servis di index dibuat lebih ringkas.

## Belum diperbaiki (perlu keputusan / pekerjaan lanjutan)

### Performa
1. **Payload customer di list Servis & form Sales** — `ReparationsController::index` dan `SalesController::create` mengirim seluruh daftar customer setiap load. Rencana: endpoint JSON pencarian (`/customers?q=`) + load saat modal dibuka.
2. **Payload produk di kasir** — `SalesController::sellableProducts()` mengirim seluruh katalog stok > 0 (termasuk JSON spesifikasi). Rencana: search endpoint server-side saat mengetik.
3. **Search `LIKE '%x%'`** — tidak bisa pakai indeks. Opsi: MySQL FULLTEXT, atau ubah ke prefix search + minimum 3 karakter.
4. **Permission per-route** — 58 route pakai middleware `permission:`; spatie query role/permission tiap request, lalu `HandleInertiaRequests` query lagi `getAllPermissions()` + `getRoleNames()`. Opsi: cache per user per request, pindahkan pengecekan ke level group, atau `Gate::before` untuk SYSADMIN.
5. **`HandleInertiaRequests`** — mengirim seluruh model user (termasuk field sensitif/timestamps). Sebaiknya select `id, name, username`.
6. **Payload data stok** — `LaptopsController` dkk. mengambil semua kolom (termasuk JSON besar: `specification`, `features`, `accessories`, `issue_notes`). Brand/supplier juga query ulang tiap load → bisa di-cache.
7. **Pencarian `whereHas('stages', ...)` di list servis** — saat search aktif, `orWhereHas` bisa lambat di data besar; pertimbangkan indeks `reparation_stages(reparation_id, status)`.

### Lain-lain
8. **Berkas `srv-261018-03-pdf.pdf`** di root project belum dihapus (file sampel hasil uji user).
9. **Dashboard `Reparation::whereNotIn('status', ...)->count()`** — belum pakai indeks `status` secara optimal (bisa `whereIn` status aktif).

## Catatan uji
- `php artisan test --compact` — 136 lulus.
- `~/.bun/bin/bun run build` — sukses.
- Migration indeks belum dijalankan (menunggu akses DB).
