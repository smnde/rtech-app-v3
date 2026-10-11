# Progress — RTech App v3 (Frontend)

Frontend only. Backend (Laravel) belum disentuh untuk pekerjaan transaksi ini.
Stack: Laravel + Inertia + Vue 3 `<script setup>` + Tailwind v3.
Build pakai **bun**: `~/.bun/bin/bun run build` (npm tidak terinstall).

---

## Status terakhir

Terakhir dikerjakan: **Transaksi Pembelian — rework sumber pembelian + picker** (selesai, belum di-build).

> ⚠️ Setiap habis mengedit file `.vue`, WAJIB build ulang. Kalau UI tidak berubah
> padahal source sudah diedit, hampir pasti **bundle belum di-rebuild** — bukan kode salah.
> - Build sekali: `~/.bun/bin/bun run build`
> - Auto (HMR) saat menyunting: `~/.bun/bin/bun run dev`

---

## Yang sudah selesai

### Transaksi Penjualan (`Pages/Transaksi/PenjualanCreate.vue`)
- [x] Customer picker: search → dropdown muncul **hanya setelah mengetik** (bukan saat focus)
- [x] Dropdown hasil: avatar + ★ Member + telepon; kalau tak ada match → tombol **"Tambah customer baru: …"** → `CustomerFormModal`
- [x] Customer terpilih → card kecil dengan tombol X untuk hapus
- [x] PO / Non-PO **dihapus**; No. PO & No. Invoice **dihapus** (invoice di-generate BE)
- [x] Status jadi **tab**: `Lunas` / `Hutang` / `Keep`
- [x] Hutang & Keep → muncul **Jumlah Bayar** (opsional) + **Jatuh Tempo** (opsional) + baris "Sisa Hutang"
- [x] Metode bayar jadi **tab**: `Cash` / `Transfer`
- [x] Cart: item bisa **expand/collapse** untuk lihat spek (kode, spek, harga)
- [x] Input harga di cart **dihapus** (edit harga lewat panel Rincian)
- [x] Live search item **di-reset** setelah barang masuk keranjang
- [x] Tombol kecil **"Jadikan bonus"** (ikon Gift) di cart → harga jadi 0, tombol hilang, muncul badge **FREE** hijau; diskon item di Rincian ikut dihilangkan untuk item bonus
- [x] Save/checkout **diblokir** sampai customer dipilih (`canSave`)
- [x] Scroll hanya di dalam card (bukan sehalaman); card checkout **sticky**, isinya scroll sendiri
- [x] Rincian bisa dilipat (expand/collapse)

### Transaksi Pembelian (`Pages/Transaksi/PembelianCreate.vue`)
- [x] **Tanggal** di paling atas card checkout — sekarang **di kedua panel** (mobile & desktop).
      (Catatan: sebelumnya baru dipindah di panel mobile saja, desktop ketinggalan → sudah disamakan)
- [x] Dua invoice: `invoice` **BE-generated** (tidak ada di FE) + `buy_from_reference` = **No. Nota Supplier** (opsional, manual)
- [x] Field input invoice lama (`number`) **dihapus**
- [x] **Sumber Pembelian** sebagai tab di paling atas card checkout: `Dari Supplier` / `Dari Customer`
      - **Dari Supplier** → tab PO/Non-PO muncul, picker supplier tampil
      - **Dari Customer** → tab PO/Non-PO **hilang**, picker supplier **hilang** → diganti picker customer
      - `jenis` dikirim `null` kalau sumbernya customer
      - Pindah sumber otomatis bersihkan pilihan lawan (`setBuyFrom`)
- [x] **No. Nota Supplier** sekarang di-gate `v-if="buyFromSupplier"` → hilang kalau sumbernya customer.
      Nilai `buy_from_reference` **tidak direset** saat pindah ke customer (biar tidak hilang kalau balik lagi)
- [x] **Supplier picker** (bukan `AppSelect` lagi): search → dropdown (avatar biru + telepon) → "Tambah supplier baru: …" kalau tak ada match → `SupplierFormModal` → card terpilih + X
- [x] **Customer picker** untuk sumber customer — sama persis dengan pola Penjualan
- [x] **Tombol "Barang Baru"** di sebelah live search (desktop & mobile tab Cari) → menu pilihan jenis **Sparepart / Aksesoris / Laptop / Gadget** → buka modal form yang sesuai
      - Barang baru **langsung masuk keranjang** dan langsung terpilih
- [x] Cart: expand/collapse spek, harga tidak bisa diedit di cart, live search reset setelah add
- [x] Status jadi tab: `Pending` / `Sebagian` / `Diterima` / `Batal`
- [x] Metode bayar jadi tab: `Cash` / `Transfer`
- [x] Scroll hanya di dalam card; card checkout sticky
- [x] `canSave` — blokir save sampai sumber (supplier/customer) dipilih + keranjang tidak kosong

### File baru / diubah
- **BARU** `Components/Suppliers/SupplierFormModal.vue` — modal tambah/edit supplier
  (field: `name` wajib, `phone` opsional, `description` opsional — mengikuti tabel `suppliers`)
- `composables/formOptions.js` — `supplierOptions` dapat field `phone`; `customerOptions` dapat `phone` + `is_member`; `productOptions` dapat `spec`; semua dapat `buy_price`/`sell_price`
- `Pages/Transaksi/PenjualanCreate.vue` — semua perubahan Penjualan di atas
- `Pages/Transaksi/PembelianCreate.vue` — semua perubahan Pembelian di atas

---

## Yang BELUM selesai / TODO

- [ ] **Reminder user**: jalankan `~/.bun/bin/bun run build` sebelum cek UI
- [ ] **Hapus `console.log`** sebelum commit:
      - `PenjualanCreate.vue` → `console.log('submit penjualan', payload)`
      - `PembelianCreate.vue` → `console.log('submit pembelian', payload)`
      (sengaja dibiarkan untuk debugging; user belum bilang hapus)
- [ ] **Integrasi BE** — semua payload masih `console.log`, belum dikirim ke backend:
      - Penjualan → `sales` + `sale_details`
      - Pembelian → `purchases` + `purchase_details`
- [ ] **Halaman list** `Penjualan.vue` & `Pembelian.vue` masih data dummy
      - `Pembelian.vue` masih pakai field `number` (No. PO) & `supplier_id` — belum ikut skema baru (`buy_from_reference`, `customer_id`, `buy_from`)
- [ ] **Halaman list Pembelian** perlu ikut menampilkan sumber (supplier vs customer)
      sesuai skema baru

---

## Catatan teknis / jebakan

- **`replace_all` tidak bisa dipercaya.** Sudah 2× lapor "success" tapi cuma sebagian berubah
  (indentasi beda antar panel). **Selalu grep verifikasi** setelah `replace_all`.
- **Template ref jangan pakai jalur bertitik.** `ref="itemModalRefs.sparepart"` **tidak dijamin terisi**
  di `<script setup>` — harus binding top-level (`const sparepartModalRef = ref(null)`) lalu
  `ref="sparepartModalRef"`. (Sudah diperbaiki.)
- **`productOptions` adalah state modul.** Barang baru dari modal di-push ke situ, jadi:
  - hidup hanya selama sesi (belum ada BE)
  - muncul juga di halaman lain yang pakai daftar itu
- **Skema DB yang relevan** (dari migrasi, sebagai acuan payload):
  - `purchases`: `supplier_id` (nullable), `customer_id` (nullable), `buy_from_reference` (nullable, indexed),
    `invoice` (**unique, BE-generated**), `total`, `notes` → konfirmasi: "beli dari customer" memang
    sudah didukung skema (kedua FK nullable)
  - `purchase_details`: `purchase_id`, `item_id`, `qty`, `buy_price`, `subtotal`
    → **tidak ada kolom diskon per-item di DB** (FE punya `discount`, perlu diputuskan nanti)
  - `suppliers`: `name` (unique), `phone` (nullable), `description` (nullable)
- **Scroll pattern**: root `flex h-full flex-col`, header `shrink-0`,
  konten `flex-1 min-h-0 overflow-y-auto scrollbar-styled`.
  Card checkout: `lg:max-h-[calc(100vh-220px)] lg:overflow-y-auto scrollbar-styled`.
  (`lg:sticky` praktis inert begitu scroll halaman dimatikan.)
- **Style token**: `slate-*` → `gray-*`, `indigo-*` → `primary-*`, `emerald-*` → `green-*`.
  Ikon pakai `lucide-vue-next`.

---

## Verifikasi terakhir

`PembelianCreate.vue` (per terakhir edit):
- `div` 132/132, `template` 2/2, `AppCard` 4/4, `AppButton` 7/7
- `v-if="buyFromSupplier"` = 6 (Jenis ×2, Supplier ×2, No. Nota ×2)
- `AppDatePicker v-model="form.date"` = 2 (mobile + desktop)
- Tidak ada sisa: `isPo`, `AppSelect`, `form.number`, deklarasi ganda
