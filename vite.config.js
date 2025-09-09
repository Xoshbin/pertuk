/** @type {import('vite').UserConfig} */
import tailwindcss from "@tailwindcss/vite";

export default {
    plugins: [tailwindcss()],
    build: {
        assetsDir: "",
        rollupOptions: {
            input: ["resources/js/pertuk.js", "resources/css/pertuk.css"],
            output: {
                assetFileNames: "[name][extname]",
                entryFileNames: "[name].js",
            },
        },
    },
};
