import { defineConfig, type Plugin } from 'vite'
import react from '@vitejs/plugin-react'

const BACKEND = process.env.VITE_BACKEND_URL || 'http://127.0.0.1:8085'
const ADMIN_SECTIONS = new Set([
  'login',
  'categories',
  'taxonomy',
  'series',
  'collections',
  'comments',
  'users',
  'settings',
  'sync',
])

async function fetchAdminPath(): Promise<string> {
  if (process.env.ADMIN_PATH) {
    return process.env.ADMIN_PATH.replace(/^\/+|\/+$/g, '')
  }

  try {
    const res = await fetch(`${BACKEND}/api/site/admin-path`)
    if (res.ok) {
      const data = (await res.json()) as { path?: string }
      if (data.path) {
        return data.path
      }
    }
  } catch {
    // Laravel may be offline during first install.
  }

  return 'admin'
}

function adminDevPlugin(adminPath: string): Plugin {
  const prefix = `/${adminPath}`

  return {
    name: 'admin-dev-routes',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        const raw = req.url ?? ''
        const pathOnly = raw.split('?')[0]

        if (
          pathOnly.startsWith('/api')
          || pathOnly.startsWith('/storage')
          || pathOnly.startsWith('/theme-assets')
          || pathOnly.startsWith('/@')
          || pathOnly.startsWith('/src')
          || pathOnly.startsWith('/node_modules')
          || pathOnly.startsWith(prefix)
          || /\.[a-zA-Z0-9]+$/.test(pathOnly)
        ) {
          return next()
        }

        const normalized = pathOnly.replace(/\/+$/, '') || '/'
        const section = normalized.startsWith('/') ? normalized.slice(1) : normalized

        if (normalized === '/' || ADMIN_SECTIONS.has(section)) {
          res.statusCode = 404
          res.setHeader('Content-Type', 'text/plain; charset=utf-8')
          res.end(`Админка доступна только по адресу ${prefix}/`)
          return
        }

        next()
      })
    },
  }
}

export default defineConfig(async ({ command }) => {
  const adminPath = await fetchAdminPath()
  const base = command === 'build' ? './' : `/${adminPath}/`

  return {
    plugins: [react(), adminDevPlugin(adminPath)],
    base,
    build: {
      outDir: '../public/admin',
      emptyOutDir: true,
    },
    server: {
      proxy: {
        '/api': {
          target: BACKEND,
          changeOrigin: true,
        },
        '/storage': {
          target: BACKEND,
          changeOrigin: true,
        },
        '/theme-assets': {
          target: BACKEND,
          changeOrigin: true,
        },
      },
    },
  }
})
