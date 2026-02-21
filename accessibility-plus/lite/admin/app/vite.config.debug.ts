import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { fileURLToPath, URL } from "node:url";
import tailwindcss from "@tailwindcss/vite";

// Debug configuration for production builds
export default defineConfig({
  base: "/wp-content/plugins/accessibility-plus/lite/admin/app/dist",
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
  build: {
    // Enable source maps for debugging
    sourcemap: true,
    // Disable minification to keep code readable
    minify: false,
    // Enable detailed build info
    reportCompressedSize: true,
    // Keep console logs and debug statements
    rollupOptions: {
      output: {
        entryFileNames: `assets/[name].js`,
        chunkFileNames: `assets/[name].js`,
        assetFileNames: `assets/[name].[ext]`,
        // Preserve function names for better debugging
        generatedCode: {
          preset: 'es2015',
          symbols: true,
        },
      },
      // Enable detailed rollup logging
      onwarn(warning, warn) {
        warn(warning);
      },
    },
  },
  // Enable verbose logging
  logLevel: 'info',
  // Keep console logs in production
  define: {
    __DEV__: false,
    'process.env.NODE_ENV': '"production"',
    // Enable debug mode
    'process.env.DEBUG': '"true"',
  },
  // Enable detailed error reporting
  server: {
    hmr: {
      overlay: true,
    },
  },
  // Enable source map loading
  optimizeDeps: {
    include: ["@tanstack/react-router"]
  },
});
