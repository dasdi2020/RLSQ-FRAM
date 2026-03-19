import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { resolve } from 'path';

export default defineConfig({
    plugins: [svelte()],

    root: resolve(__dirname),

    // En mode dev, Vite tourne sur un port séparé.
    // Le PHP sert l'API et les pages, Vite sert les assets JS/CSS.
    server: {
        port: 5173,
        strictPort: true,
        // Proxy les requêtes API vers le serveur PHP
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
        },
    },

    build: {
        // Build dans public/build/ pour que PHP puisse servir les assets compilés
        outDir: resolve(__dirname, '../public/build'),
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: resolve(__dirname, 'src/main.js'),
        },
    },
});
