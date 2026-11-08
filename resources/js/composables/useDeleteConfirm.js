import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
import { usePermission } from '@/composables/usePermission';
import { useToast } from '@/composables/useToast';

/**
 * Konfirmasi hapus terpusat.
 * Untuk role SYSADMIN, modal otomatis minta password dan password dikirim
 * ke server (backend memverifikasi ulang lewat middleware `sysadmin.password`).
 */
export function useDeleteConfirm() {
    const confirm = useConfirm();
    const { hasRole } = usePermission();
    const toast = useToast();

    const confirmDelete = async ({
        url,
        title,
        message = '',
        confirmLabel = 'Hapus',
        options = {},
    }) => {
        const requirePassword = hasRole('SYSADMIN');

        const ok = await confirm.confirm({
            title,
            message,
            confirmLabel,
            variant: 'danger',
            requirePassword,
        });
        if (!ok) return false;

        const data = requirePassword ? { password: confirm.state.password } : {};

        router.delete(url, {
            preserveScroll: true,
            ...options,
            data,
            onError: options.onError ?? ((errors) => {
                toast.error(Object.values(errors)[0] ?? 'Gagal menghapus data.');
            }),
        });

        return true;
    };

    return { confirmDelete };
}
