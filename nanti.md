# Catatan: Log Aktivitas User (belum dikerjakan)

Status: **rencana / belum dieksekusi**. Simpan dulu, kerjakan kapan-kapan.

## Tujuan

Mencatat semua aktivitas user (siapa melakukan apa), dan halaman untuk melihatnya.
Akses hanya untuk **sysadmin**.

## Perkiraan effort

Tidak lama, tapi tidak murah. Bukan satu file — minimal:

- migration + model `activity_logs`
- tempat mencatat (middleware global atau observer Eloquent)
- controller + halaman Vue dengan filter
- route
- role/permission `SYSADMIN` (atau permission khusus)

Total sekitar 7–9 file, kira-kira 1,5–2x pekerjaan fitur mutasi stok terakhir.

## Dua pendekatan

### 1. Pakai `spatie/laravel-activitylog`

- Paling cepat ditulis, otomatis menangkap create/update/delete model.
- **Minus:** menambah dependency (perlu approval), isi log cenderung teknis
  (field apa yang berubah) dan kurang enak dibaca kasir/sysadmin.

### 2. Custom, meniru pola `item_logs` yang sudah ada — **direkomendasikan**

- Tabel `activity_logs`: `user_id`, `action`, `subject`, `description`, `ip`, `created_at`.
- Dicatat lewat observer global + login/logout.
- **Plus:** ringan, tanpa dependency baru, format bisa manusiawi
  (contoh: "Budi menghapus penjualan INV-...").
- Konsisten dengan pola aplikasi saat ini.

## Keputusan yang harus diambil sebelum eksekusi

1. **Cakupan** — hanya aksi penting (login, transaksi, hapus/edit, ubah stok,
   ubah user/permission), atau semua perubahan model termasuk update kecil?
2. **Diff** — simpan nilai sebelum/sesudah (lebih berat, tabel cepat besar)
   atau cukup deskripsi singkat?
3. **Retensi** — simpan selamanya atau auto-hapus setelah N bulan?

Kombinasi "aksi penting saja + deskripsi singkat + tanpa diff" = pekerjaan sedang,
masih wajar. Diff lengkap untuk semua model = mahal.

## Akses

Batasi ke sysadmin lewat permission baru (mis. `show activity_log`) yang hanya
di-assign ke role SYSADMIN, plus middleware di route — mengikuti pola
`permission:show report` yang sudah dipakai.

## Referensi pola yang sudah ada di project

- `app/Models/ItemLog.php` + tabel `item_logs`
- `app/Http/Controllers/ItemLogController.php`
- Middleware permission: `routes/web.php` (`->middleware('permission:...')`)
- Role/permission seeder: `database/seeders/RolePermissionSeeder.php`
