/**
 * Opsi dummy untuk select di modal form — sementara sampai dikirim dari BE.
 * `id` mengikuti kolom FK di DB (brand_id, supplier_id, category_id).
 */
export const brandOptions = [
    { id: 1,  label: 'Apple' },
    { id: 2,  label: 'Samsung' },
    { id: 3,  label: 'Asus' },
    { id: 4,  label: 'Lenovo' },
    { id: 5,  label: 'Xiaomi' },
    { id: 6,  label: 'Oppo' },
    { id: 7,  label: 'HP' },
    { id: 8,  label: 'Acer' },
    { id: 9,  label: 'Anker' },
    { id: 10, label: 'Logitech' },
    { id: 11, label: 'Spigen' },
];

/**
 * Supplier untuk select / picker di transaksi pembelian.
 * `phone` dipakai oleh supplier picker di PembelianCreate (sama seperti customer).
 */
export const supplierOptions = [
    { id: 1, label: 'PT Sumber Elektronik', phone: '021-5550-1122' },
    { id: 2, label: 'CV Digital Nusantara', phone: '021-7788-3344' },
    { id: 3, label: 'PT Global Gadget',     phone: '031-8899-5566' },
    { id: 4, label: 'UD Teknologi Jaya',    phone: '022-6677-9900' },
];

/**
 * Customer untuk select di transaksi penjualan.
 * Id-nya sejalan dengan dummy `customers` di Customers/Index.vue.
 * `phone` & `is_member` dipakai oleh customer picker di PenjualanCreate.
 */
export const customerOptions = [
    { id: 1, label: 'Budi Santoso',   phone: '0812-3456-7890', is_member: 1 },
    { id: 2, label: 'Dewi Rahayu',    phone: '0813-2233-4455', is_member: 0 },
    { id: 3, label: 'Andi Wijaya',    phone: '0857-8899-1122', is_member: 1 },
    { id: 4, label: 'Rina Kusuma',    phone: '0821-5566-7788', is_member: 0 },
    { id: 5, label: 'Hendra Saputra', phone: '0878-1234-5678', is_member: 0 },
];

export const categoryOptions = [
    { id: 1, label: 'Laptop' },
    { id: 2, label: 'Gadget' },
    { id: 3, label: 'Aksesoris' },
    { id: 4, label: 'Sparepart' },
    { id: 5, label: 'Peripherals' },
];

/**
 * Produk untuk baris item transaksi (penjualan / pembelian).
 * `buy_price` dipakai sebagai harga beli default saat dipilih di Pembelian,
 * `sell_price` dipakai sebagai harga jual default di Penjualan.
 * `spec` ditampilkan di cart (expand) supaya kasir tidak salah ambil barang.
 */
export const productOptions = [
    { id: 1,  label: 'Asus VivoBook 15 X1502',   code: 'LPT-001', spec: 'i5-1235U · 8GB DDR4 · 512GB SSD · 15.6" FHD',      buy_price: 6800000,  sell_price: 7500000 },
    { id: 2,  label: 'Lenovo IdeaPad Slim 5i',    code: 'LPT-002', spec: 'i5-12450H · 16GB DDR5 · 512GB SSD · 14" WUXGA',    buy_price: 8400000,  sell_price: 9200000 },
    { id: 3,  label: 'Apple MacBook Air M2',      code: 'LPT-003', spec: 'Apple M2 · 8GB · 256GB SSD · 13.6" Liquid Retina',  buy_price: 17000000, sell_price: 18500000 },
    { id: 4,  label: 'Samsung Galaxy A55',        code: 'GDG-002', spec: 'Exynos 1480 · 8/256GB · 6.6" AMOLED · 5000mAh',     buy_price: 4300000,  sell_price: 4999000 },
    { id: 5,  label: 'Xiaomi Redmi Note 13',      code: 'GDG-004', spec: 'Snapdragon 685 · 8/256GB · 6.67" AMOLED · 5000mAh', buy_price: 2100000,  sell_price: 2499000 },
    { id: 6,  label: 'Charger Apple 20W',         code: 'AKS-001', spec: 'USB-C Power Delivery 20W · Original',               buy_price: 185000,   sell_price: 280000 },
    { id: 7,  label: 'Mouse Logitech M185',       code: 'AKS-006', spec: 'Wireless 2.4GHz · 1000 DPI · Baterai AA',            buy_price: 95000,    sell_price: 155000 },
    { id: 8,  label: 'LCD iPhone 14 Pro',         code: 'SPR-014', spec: 'OLED · Original Service Pack',                      buy_price: 780000,   sell_price: 1200000 },
    { id: 9,  label: 'Baterai Samsung A55',       code: 'SPR-022', spec: 'Li-Ion 5000mAh · EB-BA556ABY',                      buy_price: 195000,   sell_price: 350000 },
    { id: 10, label: 'Keyboard Lenovo IdeaPad',   code: 'SPR-031', spec: 'Layout US · Backlight · Original',                  buy_price: 240000,   sell_price: 420000 },
];

export const labelOf = (options, id) =>
    options.find((o) => o.id === id)?.label ?? '—';

export const brandLabel = (id) => labelOf(brandOptions, id);

export const productById = (id) => productOptions.find((p) => p.id === id) ?? null;

export const productLabel = (id) => productById(id)?.label ?? '—';

export const supplierLabel = (id) => labelOf(supplierOptions, id);
