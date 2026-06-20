/// <reference types="vitest" />
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

const isTest = typeof process.env.VITEST !== 'undefined';

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@/components': fileURLToPath(new URL('./src/components', import.meta.url)),
      '@/pages': fileURLToPath(new URL('./src/pages', import.meta.url)),
      '@/layouts': fileURLToPath(new URL('./src/layouts', import.meta.url)),
      '@/services': fileURLToPath(new URL('./src/services', import.meta.url)),
      '@/stores': fileURLToPath(new URL('./src/stores', import.meta.url)),
      '@/types': fileURLToPath(new URL('./src/types', import.meta.url)),
      '@/design-system': fileURLToPath(new URL('./src/design-system', import.meta.url)),
      ...(isTest ? {
        '@motionone/vue': fileURLToPath(new URL('./src/__tests__/motionStub.ts', import.meta.url))
      } : {})
    }
  },
  test: {
    globals: true,
    environment: 'jsdom',
    exclude: ['node_modules', 'dist', '.idea', '.git', '.cache', 'e2e/**/*'],
    setupFiles: ['./src/__tests__/setup.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
      exclude: ['node_modules/', 'src/__tests__/']
    }
  },
  server: {
    proxy: {
      '/storage': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
