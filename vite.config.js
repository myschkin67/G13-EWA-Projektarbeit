import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  // WICHTIG: Dein Pfad auf dem Uni-Server
  base: '/ewa/g13/aplbeleg/',
  
  plugins: [
    vue(),
    // DevTools entfernt, damit der Build sauber durchläuft
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
})
