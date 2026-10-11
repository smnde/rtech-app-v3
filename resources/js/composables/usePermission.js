import { usePage } from '@inertiajs/vue3';

export function usePermission() {
    const page = usePage();

    const can = (permission) => (page.props.auth?.permissions ?? []).includes(permission);

    const hasRole = (role) => (page.props.auth?.roles ?? []).includes(role);

    return { can, hasRole };
}
