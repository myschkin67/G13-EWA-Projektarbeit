import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  // pfad auf dem uni server.
  // ohne das findet die index.html die css/js dateien nicht.
  base: '/ewa/g13/aplbeleg/',
  
  plugins: [
    vue(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
})