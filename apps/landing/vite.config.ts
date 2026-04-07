import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";

// https://vitejs.dev/config/
export default defineConfig(() => ({
  server: {
    host: "::",
    port: 4174,
    hmr: {
      overlay: false,
    },
  },
  plugins: [react()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
    dedupe: ["react", "react-dom", "react/jsx-runtime", "react/jsx-dev-runtime"],
  },
  build: {
    outDir: "dist",
    // Phaser lives behind a lazy boundary and should not fail CI because of its isolated demo chunk size.
    chunkSizeWarningLimit: 1600,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes("node_modules")) {
            return;
          }

          if (id.includes("phaser")) return "phaser";
          if (id.includes("@rive-app")) return "rive-runtime";
          if (id.includes("gsap")) return "gsap";
          if (id.includes("motion")) return "motion";
          if (id.includes("react")) return "react-vendor";

          return "vendor";
        },
      },
    },
  },
}));
