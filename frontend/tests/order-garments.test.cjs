const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const ts = require('typescript')

function loadLib() {
  const source = fs.readFileSync(path.join(__dirname, '../lib/order-garments.ts'), 'utf8')
  const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
  const mod = { exports: {} }
  new Function('module', 'exports', outputText)(mod, mod.exports)
  return mod.exports
}

const { groupItemsByGarment, garmentLabel } = loadLib()

// Orden real de la demo: 40 poleras M, 40 polares L y 20 camisas M.
const ordenMixta = [
  { product_type: 'Camisa Oxford MC', size: 'M', quantity: 20 },
  { product_type: 'Polar Tactico', size: 'L', quantity: 40 },
  { product_type: 'Polera Polo MC', size: 'M', quantity: 40 },
]

const porPrenda = (groups) => Object.fromEntries(groups.map((g) => [g.product, { total: g.total, sizes: Object.fromEntries(g.sizes) }]))

test('separa cada prenda: las tallas M de poleras y camisas no se mezclan', () => {
  const groups = groupItemsByGarment(ordenMixta)
  assert.equal(groups.length, 3)
  assert.deepEqual(porPrenda(groups), {
    'Camisa Oxford MC': { total: 20, sizes: { M: 20 } },
    'Polar Tactico': { total: 40, sizes: { L: 40 } },
    'Polera Polo MC': { total: 40, sizes: { M: 40 } },
  })
})

test('suma líneas repetidas de la misma prenda y talla, y separa tallas distintas', () => {
  const groups = groupItemsByGarment([
    { product_type: 'Polera Polo MC', size: 'M', quantity: 10 },
    { product_type: 'Polera Polo MC', size: 'L', quantity: 3 },
    { product_type: 'Polera Polo MC', size: 'M', quantity: 5 },
  ])
  assert.deepEqual(porPrenda(groups), { 'Polera Polo MC': { total: 18, sizes: { M: 15, L: 3 } } })
})

test('tolera datos incompletos: prenda o talla vacías y cantidades como texto', () => {
  const groups = groupItemsByGarment([
    { product_type: '  ', size: '', quantity: '5' },
    { product_type: null, size: null, quantity: 2 },
  ])
  assert.deepEqual(porPrenda(groups), { 'Prenda sin especificar': { total: 7, sizes: { 'Única': 7 } } })
})

test('sin ítems no hay grupos', () => {
  assert.deepEqual(groupItemsByGarment([]), [])
  assert.deepEqual(groupItemsByGarment(null), [])
  assert.deepEqual(groupItemsByGarment(undefined), [])
})

test('la etiqueta corta no presenta una orden mixta como si fuera una sola prenda', () => {
  assert.equal(garmentLabel(groupItemsByGarment(ordenMixta)), '3 prendas')
  assert.equal(garmentLabel(groupItemsByGarment([{ product_type: 'Polar Tactico', size: 'L', quantity: 4 }])), 'Polar Tactico')
  assert.equal(garmentLabel([]), 'Prenda sin especificar')
})

test('ordena las tallas como se leen al cortar (S, M, L…) y deja al final las no estándar', () => {
  const [polera] = groupItemsByGarment([
    { product_type: 'Polera Polo MC', size: 'L', quantity: 1 },
    { product_type: 'Polera Polo MC', size: '44', quantity: 1 },
    { product_type: 'Polera Polo MC', size: 's', quantity: 1 },
    { product_type: 'Polera Polo MC', size: 'M', quantity: 1 },
  ])
  assert.deepEqual(polera.sizes.map(([talla]) => talla), ['s', 'M', 'L', '44'])
})

test('respeta el orden en que las prendas se ingresaron en la cotización', () => {
  assert.deepEqual(groupItemsByGarment(ordenMixta).map((g) => g.product), ['Camisa Oxford MC', 'Polar Tactico', 'Polera Polo MC'])
})
