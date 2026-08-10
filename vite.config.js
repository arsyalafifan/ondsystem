import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // Diisi VITE_HOST (alamat IP komputer ini) saat menguji dari ponsel.
        // Tanpa itu Vite hanya mendengarkan localhost, dan ponsel tidak bisa
        // mengambil CSS/JS-nya sehingga halaman tampil polos tanpa gaya.
        host: process.env.VITE_HOST ? true : 'localhost',
        hmr: process.env.VITE_HOST ? { host: process.env.VITE_HOST } : undefined,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
