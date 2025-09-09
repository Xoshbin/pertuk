import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/pertuk.css", "resources/js/pertuk.js"],
            refresh: true,
        }),
    ],
});
