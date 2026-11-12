const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const ts = require('typescript')
const vm = require('node:vm')

const catalog = {}
vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../app/(auth)/inventario/lib/generic-catalog-update.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, { exports: catalog })
test('Cambiar cotizable envía solamente ese campo, conservando precio y personalización', () => {
  const original = { nombre: 'Blusa', precio_venta: 25000, permite_personalizar: true, permite_cotizar: true }
  assert.deepEqual(JSON.parse(JSON.stringify(catalog.genericCatalogChanges(original, { ...original, permite_cotizar: false }))), { permite_cotizar: false })
  assert.deepEqual(JSON.parse(JSON.stringify(catalog.genericCatalogChanges({ ...original, permite_cotizar: false }, original))), { permite_cotizar: true })
  assert.deepEqual(JSON.parse(JSON.stringify(catalog.genericCatalogChanges(original, original))), {})
  const changed = catalog.genericCatalogChanges(original, { ...original, nombre: 'Blusa nueva', precio_venta: null, permite_personalizar: false })
  assert.deepEqual(JSON.parse(JSON.stringify(changed)), { nombre: 'Blusa nueva', precio_venta: null, permite_personalizar: false })
})

test('Kit mode and component definitions update independently without sending price or other permissions', () => {
  const original = { nombre: 'Kit', precio_venta: 75000, permite_cotizar: true, permite_personalizar: true, modo_kit: true, componentes_kit: [{ sku_generico_id: 1, cantidad_por_kit: 1, regla: 'obligatorio', grupo_alternativa: '' }] }
  const changes = edited => JSON.parse(JSON.stringify(catalog.genericCatalogChanges(original, edited)))
  assert.deepEqual(changes({ ...original, modo_kit: false }), { modo_kit: false })
  const definitions = original.componentes_kit.map(d => ({ ...d, cantidad_por_kit: 2 }))
  assert.deepEqual(changes({ ...original, componentes_kit: definitions }), { componentes_kit: definitions })
  assert.equal(original.componentes_kit[0].cantidad_por_kit, 1)
})

function harness() {
  const slots = [], cleanups = [], timers = new Map(), requests = []
  let cursor = 0, nextTimer = 0, args = [false, '', null]
  const react = {
    useState(initial) { const i = cursor++; if (!(i in slots)) slots[i] = initial; return [slots[i], value => slots[i] = typeof value === 'function' ? value(slots[i]) : value] },
    useRef(initial) { const i = cursor++; if (!(i in slots)) slots[i] = { current: initial }; return slots[i] },
    useEffect(effect, deps) { const i = cursor++; if (!slots[i] || deps.some((v, j) => v !== slots[i][j])) { cleanups[i]?.(); cleanups[i] = effect(); slots[i] = deps } },
  }
  const exports = {}
  const source = ts.transpileModule(fs.readFileSync(path.join(__dirname, '../hooks/useQuotableSkus.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText
  vm.runInNewContext(source, { exports, AbortController, URLSearchParams,
    setTimeout: callback => { timers.set(++nextTimer, callback); return nextTimer }, clearTimeout: id => timers.delete(id),
    require: name => name === 'react' ? react : { api: { get(url, options) { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b }); requests.push({ url, ...options, resolve, reject }); return promise } } },
  })
  const render = () => { cursor = 0; return exports.useQuotableSkus(...args) }
  render()
  return { render, requests, setArgs: (...value) => { args = value; render() }, flush() { const callbacks = [...timers.values()]; timers.clear(); return callbacks.map(fn => fn()) }, unmount() { cleanups.forEach(fn => fn?.()) } }
}

test('Solo carga al abrir y excluye productos no cotizables', async () => {
  const h = harness(); assert.equal(h.flush().length, 0)
  h.setArgs(true, '', 0); assert.equal(h.render().loading, true)
  const pending = h.flush(); assert.equal(h.requests[0].url, '/api/comercial-skus')
  h.requests[0].resolve([{ id: 1, permite_cotizar: true }, { id: 2, permite_cotizar: false }]); await Promise.all(pending)
  assert.deepEqual(Array.from(h.render().products, p => p.id), [1]); assert.equal(h.render().loading, false)
  h.setArgs(false, '', null); assert.equal(h.render().products.length, 0)
  h.setArgs(true, '', 0); h.flush(); assert.equal(h.requests.length, 2)
})

test('Buscar codifica el texto, agrupa pulsaciones y descarta respuestas antiguas', async () => {
  const h = harness(); h.setArgs(true, 'Blusa', 0); const first = h.flush()
  h.setArgs(true, 'Az', 0); h.setArgs(true, ' Azul & M ', 0); const second = h.flush()
  assert.equal(h.requests.length, 2); assert.equal(h.requests[0].signal.aborted, true)
  assert.equal(h.requests[1].url, '/api/comercial-skus?search=Azul+%26+M')
  h.requests[1].resolve([{ id: 3, permite_cotizar: true }]); await Promise.all(second)
  h.requests[0].resolve([{ id: 1, permite_cotizar: true }]); await Promise.all(first)
  assert.deepEqual(Array.from(h.render().products, p => p.id), [3])
  h.setArgs(true, '', 0); h.flush(); assert.equal(h.requests[2].url, '/api/comercial-skus')
})

test('Cambiar de ítem refresca aun con la misma búsqueda; cerrar cancela la respuesta', async () => {
  const h = harness(); h.setArgs(true, '', 0); const first = h.flush()
  h.setArgs(true, '', 1); const second = h.flush()
  assert.equal(h.requests.length, 2); assert.equal(h.requests[0].signal.aborted, true)
  h.setArgs(false, '', null)
  for (const r of h.requests) r.resolve([{ id: 1 }])
  await Promise.all([...first, ...second]); assert.equal(h.render().products.length, 0)
  assert.equal(h.render().loading, false); assert.equal(h.render().error, '')
})

test('Error permite reintentar y no se convierte en lista vacía silenciosa', async () => {
  const h = harness(); h.setArgs(true, '', 0); const first = h.flush()
  h.requests[0].reject(new Error('HTTP 500')); await Promise.all(first)
  assert.match(h.render().error, /Reintenta/); assert.equal(h.render().loading, false)
  h.render().reload(); h.render(); const second = h.flush()
  h.requests[1].resolve([{ id: 4, permite_cotizar: true }]); await Promise.all(second)
  assert.equal(h.render().error, ''); assert.deepEqual(Array.from(h.render().products, p => p.id), [4])
  h.unmount()
})
