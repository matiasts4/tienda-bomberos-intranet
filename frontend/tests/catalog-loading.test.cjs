const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const ts = require('typescript')

const root = path.join(__dirname, '../app/(auth)/inventario')
const keys = ['unidades', 'origenes', 'grupos', 'familias', 'subfamilias', 'tipos', 'formatos', 'generos', 'colores', 'tallas']
const data = Object.fromEntries(keys.map(key => [key, [{ id: 1, nombre: key }]]))

function provider(get) {
  const slots = []
  let cursor = 0
  const react = {
    createContext: () => ({ Provider: 'Provider' }),
    useState(initial) {
      const index = cursor++
      if (!(index in slots)) slots[index] = initial
      return [slots[index], value => { slots[index] = value }]
    },
    useRef(initial) {
      const index = cursor++
      if (!(index in slots)) slots[index] = { current: initial }
      return slots[index]
    },
    useCallback: callback => callback,
  }
  const exported = {}
  const source = fs.readFileSync(path.join(root, 'CatalogoContext.tsx'), 'utf8')
  const compiled = ts.transpileModule(source, { compilerOptions: {
    module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020, jsx: ts.JsxEmit.ReactJSX,
  } }).outputText
  vm.runInNewContext(compiled, {
    exports: exported,
    require(name) {
      if (name === 'react') return react
      if (name === 'react/jsx-runtime') return { jsx: (type, props) => ({ type, props }) }
      if (name === '@/lib/api') return { api: { get } }
      throw new Error(name)
    },
  })
  return () => {
    cursor = 0
    return exported.CatalogoProvider({ children: null }).props.value
  }
}

test('direct Catalogue opening starts the catalog request without opening Create Product', async () => {
  const source = ts.createSourceFile('page.tsx', fs.readFileSync(path.join(root, 'page.tsx'), 'utf8'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX)
  let handler
  function visit(node) {
    if (ts.isJsxAttribute(node) && node.name.getText(source) === 'onManageGenericos') handler = node.initializer.expression.getText(source)
    ts.forEachChild(node, visit)
  }
  visit(source)
  assert.ok(handler)
  let calls = 0
  let opened = false
  const render = provider(async url => { assert.equal(url, '/api/catalogos/todos'); calls++; return data })
  const load = render().initCatalogos
  vm.runInNewContext(`(${handler})()`, {
    setIsManageGenericosOpen: value => { opened = value },
    setManageGenericosCreateMode: () => {},
    initCatalogos: load
  })
  await load()
  assert.equal(opened, true)
  assert.equal(calls, 1)
  assert.equal(render().catalogos.colores[0].nombre, 'colores')
  assert.equal(render().catalogos.tallas[0].nombre, 'tallas')
})

test('concurrent consumers await the same request and subsequent openings use cached data', async () => {
  let resolve
  let calls = 0
  const render = provider(() => { calls++; return new Promise(done => { resolve = done }) })
  const load = render().initCatalogos
  const first = load()
  const second = load()
  assert.equal(first, second)
  assert.equal(render().loading, true)
  await Promise.resolve()
  resolve(data)
  assert.equal(await first, data)
  assert.equal(await second, data)
  assert.equal(await load(), data)
  assert.equal(calls, 1)
  assert.equal(render().loading, false)
  assert.equal(render().isLoaded, true)
})

test('network failure exposes a recoverable error and retry performs a new request', async () => {
  let attempts = 0
  const render = provider(async () => { if (++attempts === 1) throw new Error('offline'); return data })
  await assert.rejects(render().initCatalogos(), /offline/)
  assert.match(render().error, /Revisa tu conexión/)
  assert.equal(render().loading, false)
  assert.equal(render().isLoaded, false)
  const retry = render().initCatalogos()
  assert.equal(render().error, null)
  await retry
  assert.equal(attempts, 2)
  assert.equal(render().isLoaded, true)
})

test('malformed responses are not cached as successful; genuinely empty lists remain valid', async () => {
  let attempts = 0
  const empty = Object.fromEntries(keys.map(key => [key, []]))
  const render = provider(async () => ++attempts === 1 ? { colores: [] } : empty)
  await assert.rejects(render().initCatalogos(), /incompleta/)
  assert.equal(render().isLoaded, false)
  assert.equal(await render().initCatalogos(), empty)
  assert.equal(render().isLoaded, true)
})
