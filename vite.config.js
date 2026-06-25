import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/js/search-reset.js",
                "resources/js/form-guru.js",
                "resources/js/confirm-modal.js",
            ],
            refresh: true,
        }),
    ],
});
