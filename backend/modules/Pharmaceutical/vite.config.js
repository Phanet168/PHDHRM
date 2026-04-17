import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        outDir: '../../public/build',
        emptyOutDir: false,
        manifest: true,
    },
    plugins: [
        laravel({
            input: [
                __dirname + '/Resources/css/app.css',
                __dirname + '/Resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
