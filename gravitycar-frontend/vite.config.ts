import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: 3000,
    host: true,
    strictPort: true, // Fail if port 3000 is not available instead of trying other ports
    watch: {
      usePolling: true,
      interval: 1000
    }
  }
})
