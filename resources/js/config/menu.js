import {
    LayoutDashboard,
    Tag,
    Bookmark,
    Laptop,
    Smartphone,
    Headphones,
    Wrench,
    Users,
    Shield,
    ShieldCheck,
    KeyRound,
    UserCog,
    UserCheck,
    BarChart2,
    ShoppingCart,
    Settings,
    Package,
    ArrowLeftRight,
    Receipt,
    ShoppingBag,
    Truck,
    Wallet,
} from 'lucide-vue-next';

/**
 * Navigation menu configuration.
 * Setiap item bisa punya `children` untuk submenu.
 * `route` merujuk ke named route dari Ziggy.
 */
export const menuItems = [
    {
        key: 'dashboard',
        label: 'Dashboard',
        icon: LayoutDashboard,
        route: 'dashboard',
    },
    {
        key: 'master-data',
        label: 'Master Data',
        icon: Settings,
        children: [
            {
                key: 'category',
                label: 'Kategori',
                icon: Tag,
                route: 'master.category',
                permission: 'show category',
            },
            {
                key: 'brand',
                label: 'Brand',
                icon: Bookmark,
                route: 'master.brand',
                permission: 'show brand',
            },
            {
                key: 'supplier',
                label: 'Supplier',
                icon: Truck,
                route: 'master.supplier',
                permission: 'show supplier',
            },
        ],
    },
    {
        key: 'stock',
        label: 'Data Stok',
        icon: Package,
        children: [
            {
                key: 'stock-laptop',
                label: 'Laptop',
                icon: Laptop,
                route: 'stock.laptop',
                permission: 'show laptop',
            },
            {
                key: 'stock-gadget',
                label: 'Gadget',
                icon: Smartphone,
                route: 'stock.gadget',
                permission: 'show gadget',
            },
            {
                key: 'stock-aksesoris',
                label: 'Aksesoris',
                icon: Headphones,
                route: 'stock.aksesoris',
                permission: 'show aksesoris',
            },
            {
                key: 'stock-sparepart',
                label: 'Sparepart',
                icon: Wrench,
                route: 'stock.sparepart',
                permission: 'show sparepart',
            },
            {
                key: 'stock-mutasi',
                label: 'Mutasi Stok',
                icon: ArrowLeftRight,
                route: 'stock-movements.index',
                permission: 'show stock_movement',
            },
            {
                key: 'stock-price-tag',
                label: 'Price Tag',
                icon: Tag,
                route: 'price-tag',
                permission: 'show laptop',
            },
        ],
    },
    {
        key: 'transaksi',
        label: 'Transaksi',
        icon: Receipt,
        children: [
            {
                key: 'transaksi-penjualan',
                label: 'Penjualan',
                icon: ShoppingBag,
                route: 'transaksi.penjualan',
                permission: 'show sale',
            },
            {
                key: 'transaksi-pembelian',
                label: 'Pembelian',
                icon: Truck,
                route: 'transaksi.pembelian',
                permission: 'show purchase',
            },
            {
                key: 'transaksi-pengeluaran',
                label: 'Pengeluaran',
                icon: Receipt,
                route: 'expenses.index',
                permission: 'show expense',
            },
            {
                key: 'transaksi-kas',
                label: 'Kas',
                icon: Wallet,
                route: 'cashflow.index',
                permission: 'show cashflow',
            },
        ],
    },
    {
        key: 'manage-user',
        label: 'Manage User',
        icon: UserCog,
        roles: ['SYSADMIN'],
        children: [
            {
                key: 'users',
                label: 'User',
                icon: Users,
                route: 'users.index',
                permission: 'show user',
            },
            {
                key: 'roles',
                label: 'Role',
                icon: Shield,
                route: 'roles.index',
                roles: ['SYSADMIN'],
            },
            {
                key: 'permissions',
                label: 'Permission',
                icon: KeyRound,
                route: 'permissions.index',
                roles: ['SYSADMIN'],
            },
            {
                key: 'set-permission',
                label: 'Set Permission',
                icon: ShieldCheck,
                route: 'set-permission.index',
                roles: ['SYSADMIN'],
            },
        ],
    },
    {
        key: 'customers',
        label: 'Customer',
        icon: UserCheck,
        route: 'customers.index',
        permission: 'show customer',
    },
    {
        key: 'servis',
        label: 'Servis',
        icon: Wrench,
        route: 'servis.index',
        permission: 'show reparation',
    },
    {
        key: 'laporan',
        label: 'Laporan',
        icon: BarChart2,
        children: [
            {
                key: 'laporan-penjualan',
                label: 'Penjualan',
                icon: ShoppingCart,
                route: 'laporan.penjualan',
                permission: 'show report',
            },
            {
                key: 'laporan-servis',
                label: 'Servis',
                icon: Wrench,
                route: 'laporan.servis',
                permission: 'show report',
            },
            {
                key: 'laporan-pembelian',
                label: 'Pembelian',
                icon: Package,
                route: 'laporan.pembelian',
                permission: 'show report',
            },
            {
                key: 'laporan-mutasi',
                label: 'Mutasi Stok',
                icon: ArrowLeftRight,
                route: 'laporan.mutasi',
                permission: 'show report',
            },
        ],
    },
];

export function filterMenuByPermission(items, permissions, userRoles) {
    return items
        .map((item) => (item.children ? { ...item, children: filterMenuByPermission(item.children, permissions, userRoles) } : item))
        .filter((item) => {
            if (item.children) return item.children.length > 0;
            if (item.roles && !item.roles.some((role) => userRoles.includes(role))) return false;
            return !item.permission || permissions.includes(item.permission);
        });
}

// Menu khusus bottom nav mobile — hanya item utama (max 5)
export const mobileBottomNavItems = [
    { key: 'dashboard',  label: 'Dashboard',  icon: LayoutDashboard, route: 'dashboard' },
    { key: 'stock',      label: 'Stok',        icon: Package,         route: 'stock.laptop', permission: 'show laptop' },
    { key: 'customers',  label: 'Customer',    icon: UserCheck,       route: 'customers.index', permission: 'show customer' },
    { key: 'laporan',    label: 'Laporan',     icon: BarChart2,       route: 'laporan.penjualan', permission: 'show report' },
    { key: 'users',      label: 'User',        icon: Users,           route: 'users.index', permission: 'show user' },
];
