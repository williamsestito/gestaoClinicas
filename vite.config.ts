import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        // Sem plugin de fontes: usamos a pilha de fontes do sistema
        // operacional (ver --font-sans em resources/css/app.css), para que
        // o build nunca dependa de acesso externo (fonts.bunny.net).
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
    // Necessario para o Vite rodar dentro do container "node" do Docker Compose
    // e ainda assim ser acessivel/atualizavel (HMR) a partir do navegador no host.
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true,
        },
    },
});
