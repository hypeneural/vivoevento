import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import { VitePWA } from "vite-plugin-pwa";
import path from "path";

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => ({
  server: {
    host: "::",
    port: 5173,
    hmr: {
      overlay: false,
    },
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
    },
  },
  plugins: [
    react(),
    VitePWA({
      strategies: "injectManifest",
      srcDir: "src",
      filename: "sw.ts",
      registerType: "autoUpdate",
      includeAssets: ["favicon.ico", "pwa-192.svg", "pwa-512.svg"],
      manifest: {
        name: "Evento Vivo Play",
        short_name: "EV Play",
        description: "Jogos publicos mobile-first do Evento Vivo.",
        theme_color: "#020617",
        background_color: "#020617",
        display: "standalone",
        start_url: "/",
        icons: [
          {
            src: "/pwa-192.svg",
            sizes: "192x192",
            type: "image/svg+xml",
            purpose: "any maskable",
          },
          {
            src: "/pwa-512.svg",
            sizes: "512x512",
            type: "image/svg+xml",
            purpose: "any maskable",
          },
        ],
      },
      injectManifest: {
        globPatterns: ["**/*.{js,css,html,ico,png,svg}"],
      },
    }),
  ],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
      "@eventovivo/shared-types": path.resolve(__dirname, "../../packages/shared-types/src"),
    },
    dedupe: [
      "react",
      "react-dom",
      "react/jsx-runtime",
      "react/jsx-dev-runtime",
      "@tanstack/react-query",
      "@tanstack/query-core",
    ],
  },
  build: {
    outDir: "dist",
    sourcemap: mode === "development",
    rollupOptions: {
      output: {
        manualChunks(id) {
          const normalized = id.replace(/\\/g, "/");

          if (normalized.includes("/node_modules/phaser/")) {
            return "vendor-phaser";
          }

          if (normalized.includes("/node_modules/pusher-js/")) {
            return "vendor-realtime";
          }

          if (
            normalized.includes("/node_modules/recharts/")
            || normalized.includes("/node_modules/victory-vendor/")
            || normalized.includes("/node_modules/react-smooth/")
            || normalized.includes("/node_modules/d3-")
          ) {
            return "vendor-charts";
          }

          if (normalized.includes("/node_modules/framer-motion/")) {
            return "vendor-motion";
          }

          if (normalized.includes("/node_modules/@radix-ui/")) {
            return "vendor-ui";
          }

          if (
            normalized.includes("/node_modules/react-hook-form/")
            || normalized.includes("/node_modules/@hookform/resolvers/")
            || normalized.includes("/node_modules/zod/")
          ) {
            return "vendor-forms";
          }

          if (normalized.includes("/src/modules/play/phaser/memory/")) {
            return "play-memory-runtime";
          }

          if (normalized.includes("/src/modules/play/phaser/puzzle/")) {
            return "play-puzzle-runtime";
          }

          return undefined;
        },
      },
    },
  },
}));
