import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

export default defineConfig({
    plugins: [svelte(), tailwindcss()],

    root: resolve(__dirname),

    resolve: {
        alias: {
            '$lib': resolve(__dirname, 'src/lib'),
        },
    },

    server: {
        port: 5173,
        strictPort: true,
        proxy: {
            '/api': { target: 'http://localhost:8000', changeOrigin: true },
            '/graphql': { target: 'http://localhost:8000', changeOrigin: true },
        },
    },

    build: {
        outDir: resolve(__dirname, '../public/build'),
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: resolve(__dirname, 'src/main.js'),
        },
    },

    // Monaco Editor needs these optimizations
    optimizeDeps: {
        include: ['monaco-editor'],
    },
});
