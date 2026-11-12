const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const ts = require('typescript')
const vm = require('node:vm')
const root = path.join(__dirname, '../app/(auth)/inventario')
const compile = file => ts.transpileModule(fs.readFileSync(path.join(root, file), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const options = {}
vm.runInNewContext(compile('lib/variant-options.ts'), { exports: options, Intl })

test('Tallas de ropa y números tienen orden comercial; el catálogo original permanece intacto', () => {
  const names = ['4XL', 'ESPECIAL', 'M', 'XXS', 'XS', 'XXL', 'XL', 'L', 'S', '3XL', '42', '8', '10', 'Única']
  const sizes = names.map((nombre, id) => ({ id: 99 - id, nombre }))
  assert.deepEqual(Array.from(options.sortSizes(sizes), item => item.nombre), ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '8', '10', '42', 'Única', 'ESPECIAL'])
  assert.deepEqual(sizes.map(item => item.nombre), names)
  assert.deepEqual(Array.from(options.sortSizes([{ id: 1, nombre: '3XL' }, { id: 2, nombre: '2XL' }, { id: 3, nombre: 'XL' }]), item => item.nombre), ['XL', '2XL', '3XL'])
})

test('Buscar colores ignora acentos, mayúsculas y orden de palabras; devuelve todos al limpiar', () => {
  const colors = [{ id: 9, nombre: 'Azul Marino / Rojo' }, { id: 1, nombre: 'Blanco' }, { id: 2, nombre: 'Café' }]
  assert.deepEqual(Array.from(options.searchColors(colors, ' rojo AZUL  '), item => item.id), [9])
  assert.deepEqual(Array.from(options.searchColors(colors, 'cafe'), item => item.id), [2])
  assert.equal(options.searchColors(colors, 'inexistente').length, 0)
  assert.deepEqual(Array.from(options.searchColors(colors, ''), item => item.id), [9, 1, 2])
  assert.equal(colors[0].id, 9)
})

function deferred() { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b }); return { promise, resolve, reject } }
function harness() {
  const slots = [], pending = [], writes = [], saved = [], notices = []
  let cursor = 0, args = [42, 'SKU-A', 'Blusa Blanco M', true], cleanup = []
  const react = {
    useState: initial => { const i = cursor++; if (!(i in slots)) slots[i] = initial; return [slots[i], value => slots[i] = typeof value === 'function' ? value(slots[i]) : value] },
    useRef: initial => { const i = cursor++; if (!(i in slots)) slots[i] = { current: initial }; return slots[i] },
    useEffect: (effect, deps) => {
      const i = cursor++, old = slots[i]
      if (!old || deps.some((value, j) => value !== old[j])) { cleanup[i]?.(); cleanup[i] = effect(); slots[i] = deps }
    },
  }
  const exp = {}
  vm.runInNewContext(compile('hooks/useVariantName.ts'), { exports: exp, console, encodeURIComponent,
    require: name => name === 'react' ? react : name.endsWith('pte-notice') ? { pteNotice: (...args) => notices.push(args) } : {
      api: { patch: (url, payload) => { const d = deferred(); pending.push(d); writes.push({ url, payload }); return d.promise } },
    },
  })
  const render = () => { cursor = 0; return exp.useVariantName(...args, name => saved.push(name)) }
  render() // effects run before the next render
  return { render, writes, pending, saved, notices, setArgs: value => { args = value; render() }, unmount: () => cleanup.forEach(fn => fn?.()) }
}

test('Guardar envía solo nombre y su valor anterior para el SKU de la fila; bloquea doble envío', async () => {
  const h = harness(); h.render().start(); h.render().setDraft('  Blusa especial  ')
  const editor = h.render(), promise = editor.save()
  void editor.save()
  h.render().cancel()
  assert.equal(h.writes.length, 1)
  assert.equal(h.render().editing, true)
  assert.equal(h.writes[0].url, '/api/sku-genericos/42/variants/SKU-A/name')
  assert.deepEqual(JSON.parse(JSON.stringify(h.writes[0].payload)), { nombre: 'Blusa especial', nombre_anterior: 'Blusa Blanco M' })
  h.pending[0].resolve({ sku: 'SKU-A', nombre: 'Blusa especial' }); await promise
  assert.deepEqual(h.saved, ['Blusa especial'])
  assert.equal(h.render().editing, false); assert.equal(h.notices[0][0], 'success')
})

test('Nombre vacío, demasiado largo, sin cambios, cancelación o sin permiso no escriben', async () => {
  const h = harness(); h.render().start(); h.render().setDraft('   '); await h.render().save()
  assert.match(h.render().error, /1 y 150/)
  h.render().setDraft('a'.repeat(151)); await h.render().save()
  h.render().setDraft('Blusa Blanco M'); await h.render().save()
  assert.equal(h.render().editing, false)
  h.render().start(); h.render().setDraft('Otro'); h.render().cancel()
  assert.equal(h.render().draft, 'Blusa Blanco M')
  h.setArgs([42, 'SKU-A', 'Blusa Blanco M', false]); h.render().setDraft('Otro'); await h.render().save()
  assert.equal(h.writes.length, 0)
})

test('Error o conflicto conserva el nombre escrito y permite reintentar', async () => {
  const h = harness(); h.render().start(); h.render().setDraft('Mi nombre')
  const p = h.render().save(); h.pending[0].reject({ status: 409, data: { message: 'El nombre cambió en otra sesión' } }); await p
  assert.equal(h.render().draft, 'Mi nombre'); assert.equal(h.render().editing, true)
  assert.match(h.render().error, /otra sesión/); assert.equal(h.saved.length, 0)
  const retry = h.render().save(); assert.equal(h.writes.length, 2)
  h.pending[1].resolve({ sku: 'SKU-A', nombre: 'Mi nombre' }); await retry
  assert.deepEqual(h.saved, ['Mi nombre'])
})

test('Respuestas después de cambiar variante o cerrar no alteran otra fila ni muestran éxito', async () => {
  for (const close of [false, true]) {
    const h = harness(); h.render().start(); h.render().setDraft('Nuevo A')
    const p = h.render().save()
    if (close) h.unmount(); else h.setArgs([42, 'SKU-B', 'Nombre B', true])
    h.pending[0].resolve({ sku: 'SKU-A', nombre: 'Nuevo A' }); await p
    assert.equal(h.saved.length, 0); assert.equal(h.notices.length, 0)
    if (!close) assert.equal(h.render().draft, 'Nombre B')
  }
})
