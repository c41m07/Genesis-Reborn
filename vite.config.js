import {defineConfig} from "vite";

export default defineConfig({
    publicDir: false,
    build: {
        emptyOutDir: true,
        lib: {
            entry: "public/assets/js/app.js",
            name: "GenesisApp",
            fileName: () => "app.js",
            formats: ["es"]
        },
        rollupOptions: {
            output: {
                assetFileNames: "[name][extname]",
                chunkFileNames: "[name].js",
                entryFileNames: "[name].js"
            }
        }
    }
});
