const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const appDir = path.join(__dirname, '../app')

/** Rutas reales del App Router: carpetas con page.tsx, sin los grupos "(auth)". */
function existingRoutes(dir = appDir, prefix = '') {
  const routes = new Set()
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (!entry.isDirectory()) continue
    const segment = /^\(.*\)$/.test(entry.name) ? '' : `/${entry.name}`
    const full = path.join(dir, entry.name)
    if (fs.existsSync(path.join(full, 'page.tsx'))) routes.add(prefix + segment || '/')
    for (const r of existingRoutes(full, prefix + segment)) routes.add(r)
  }
  return routes
}

const routes = existingRoutes()

test('existe la página /modulos, destino por defecto del login y del "Dashboard General"', () => {
  assert.ok(routes.has('/modulos'), 'falta app/(auth)/modulos/page.tsx')
  const login = fs.readFileSync(path.join(appDir, 'page.tsx'), 'utf8')
  assert.match(login, /\|\|\s*"\/modulos"/, 'el login debe seguir redirigiendo a /modulos por defecto')
})

test('cada tarjeta del dashboard apunta a una ruta que existe (sin enlaces rotos)', () => {
  const page = fs.readFileSync(path.join(appDir, '(auth)/modulos/page.tsx'), 'utf8')
  const hrefs = [...page.matchAll(/href: "([^"]+)"/g)].map((m) => m[1])
  assert.ok(hrefs.length > 0, 'el dashboard debe ofrecer al menos un módulo')
  const rotos = hrefs.filter((h) => !routes.has(h))
  assert.deepEqual(rotos, [], `tarjetas que llevan a un 404: ${rotos.join(', ')}`)
})
