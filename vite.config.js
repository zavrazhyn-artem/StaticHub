import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// Dev server is exposed via Cloudflare tunnel as vite-local.blastr.pro so
// pages on https://*-local.blastr.pro can fetch HMR/asset URLs same-protocol
// and avoid mixed-content blocking. The tunnel terminates TLS at CF and
// proxies plain http://127.0.0.1:5174 to it. Bind 0.0.0.0 so the tunnel
// agent can reach Vite from outside the WSL loopback if needed.
const VITE_DEV_HOST = 'vite-local.blastr.pro';
const VITE_DEV_PORT = 5174;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    server: {
        host: '0.0.0.0',
        port: VITE_DEV_PORT,
        strictPort: true,
        cors: true,
        // Tells laravel-vite-plugin what URL to write into public/hot, so
        // @vite() emits https://vite-local.blastr.pro/... asset URLs.
        origin: `https://${VITE_DEV_HOST}`,
        hmr: {
            host: VITE_DEV_HOST,
            protocol: 'wss',
            clientPort: 443,
        },
    },
});
