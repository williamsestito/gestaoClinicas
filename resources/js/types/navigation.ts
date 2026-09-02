import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    disabled?: boolean;
    /** Chave de permissão exigida para exibir o item (ver PermissionKey no backend). Omitido = sempre visível a qualquer membro ativo. */
    permission?: string;
};

export type NavGroup = {
    title: string;
    items: NavItem[];
};
