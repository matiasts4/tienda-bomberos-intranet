const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const ts = require('typescript')

const filename = path.join(__dirname, '../app/(auth)/inventario/components/ManageGenericosModal.tsx')
const source = ts.createSourceFile(filename, fs.readFileSync(filename, 'utf8'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX)
const declarations = source.statements.filter(node =>
  ts.isFunctionDeclaration(node) && node.name?.text === 'isDecorativeItem' ||
  ts.isVariableStatement(node) && node.declarationList.declarations.some(d => d.name.getText(source) === 'DECORATIVE_PRODUCT_NAMES')
).map(node => node.getText(source)).join('\n')
const exported = {}
vm.runInNewContext(ts.transpileModule(declarations + '\nexport { isDecorativeItem }', {
  compilerOptions: { module: ts.ModuleKind.CommonJS },
}).outputText, { exports: exported })

test('Kit Cuartel is visible before kit mode is enabled, without changing its record', () => {
  const item = Object.freeze({ id: 96, nombre: 'Kit Cuartel', sku_base: '111600600000000', modo_kit: false })
  assert.equal(exported.isDecorativeItem(item), false)
  assert.equal(item.modo_kit, false)
  assert.equal(exported.isDecorativeItem({ ...item, modo_kit: true }), false)
})

test('other Kit Cuartel names remain configurable and decorative items remain excluded', () => {
  for (const nombre of ['Kit Cuartel - 3 prendas', 'KIT CUARTEL full-5 prendas', ' kit cuartel ']) {
    assert.equal(exported.isDecorativeItem({ nombre, modo_kit: false }), false, nombre)
  }
  for (const nombre of ['NOMBRE', 'CARGO', 'BANDERA', 'FRASE', 'LOGO', 'PARCHE 2,5 X 10', 'PARCHE 5 X 10']) {
    assert.equal(exported.isDecorativeItem({ nombre, modo_kit: false }), true, nombre)
  }
  assert.equal(exported.isDecorativeItem({ nombre: 'Texto variable', sku_base: '117700000100000' }), true)
  assert.equal(exported.isDecorativeItem({ nombre: 'Polera Piqué', sku_base: '111220320100000' }), false)
})
