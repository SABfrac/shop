import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import fs from 'fs'
import path from 'path'

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './src'),
            '@/views': path.resolve(__dirname, './src/views'),
            '@/services': path.resolve(__dirname, './src/services')
        }
    },
    server: {
        host: true,          // слушать 0.0.0.0 (нужно в Docker)
        port: 5173,
        watch: {
            usePolling: true,
            interval: 1000,
        },
        https: {
            key: fs.readFileSync('/app/ssl/localhost-key.pem'),
            cert: fs.readFileSync('/app/ssl/localhost.pem'),
        },
        proxy: {
            // Все запросы на /api пойдут в nginx контейнер (внутрисетевой https)
            '/api': {
                target: 'https://app:443',
                changeOrigin: true,
                secure: false, // т.к. сертификат self-signed в контейнере nginx
                ws: true,
            },
            '/images': {
                target: 'http://imaginary:9000', // Куда слать запрос реально
                changeOrigin: true,
                secure: false, // Игнорировать проблемы с SSL
                rewrite: (path) => path.replace(/^\/images/, ''),
            },



        },

        hmr: {
             protocol: 'wss',
            host: 'localhost',
            clientPort: 5173,
        },



    }
})