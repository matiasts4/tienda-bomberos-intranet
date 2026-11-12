const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const ts = require('typescript')

function loadLib() {
  const source = fs.readFileSync(path.join(__dirname, '../lib/destinatario.ts'), 'utf8')
  const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
  const mod = { exports: {} }
  new Function('module', 'exports', outputText)(mod, mod.exports)
  return mod.exports
}

const { tipoDestinatarioDe, nombreDestinatario, DESTINATARIO_LABEL } = loadLib()

test('usa el tipo que entrega el backend', () => {
  assert.equal(tipoDestinatarioDe({ tipo_destinatario: 'persona_natural', cuerpo_id: '13001' }), 'persona_natural')
})

test('respaldo local: misma regla que el backend', () => {
  assert.equal(tipoDestinatarioDe({ cuerpo_id: '13001' }), 'bomberos')
  assert.equal(tipoDestinatarioDe({ compania: { id: '1' } }), 'bomberos')
  assert.equal(tipoDestinatarioDe({ cliente_id: 'A', cliente: { tipo: 'bomberos' } }), 'bomberos')
  assert.equal(tipoDestinatarioDe({ cliente_id: 'A', cliente: { tipo: 'particular' } }), 'persona_natural')
  assert.equal(tipoDestinatarioDe({ cliente_id: 'A', cliente: { tipo: 'empresa' } }), 'empresa')
  assert.equal(tipoDestinatarioDe({ cliente_id: 'A', cliente: {} }), 'empresa')
  assert.equal(tipoDestinatarioDe({ contacto_id: 4 }), 'persona_natural', 'solo contacto = persona natural')
  assert.equal(tipoDestinatarioDe({}), 'empresa')
})

test('un cuerpo manda sobre un cliente particular', () => {
  assert.equal(tipoDestinatarioDe({ cuerpo_id: '13001', cliente_id: 'A', cliente: { tipo: 'particular' } }), 'bomberos')
})

test('nombre del destinatario y etiquetas', () => {
  assert.equal(nombreDestinatario({ cliente: { nombre_empresa: 'Juan Pérez' } }), 'Juan Pérez')
  assert.equal(nombreDestinatario({ cuerpo: { nombre: 'Yungay' }, cliente: { nombre_empresa: 'X' } }), 'Yungay')
  assert.equal(nombreDestinatario({}), 'Sin cliente')
  assert.equal(DESTINATARIO_LABEL.persona_natural, 'Persona natural')
})
