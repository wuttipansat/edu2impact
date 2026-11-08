import path from 'path';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';

// The build is served from apps/buoyancy-boat-lab/ on the KU host (no Node there),
// so assets are addressed relative to this base path.
const base = process.env.BASE_PATH ?? '/apps/buoyancy-boat-lab/';

export default defineConfig({
  base,
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, 'src'),
      '@workspace/boat-physics': path.resolve(import.meta.dirname, 'packages/boat-physics/src/index.ts'),
    },
    dedupe: ['react', 'react-dom'],
  },
  build: {
    outDir: path.resolve(import.meta.dirname, '../../apps/buoyancy-boat-lab'),
    emptyOutDir: true,
  },
  server: { port: 5173, host: '127.0.0.1' },
});
