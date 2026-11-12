const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const ts = require('typescript')

// Corre en la zona de Chile: el bug solo aparece con UTC negativo
process.env.TZ = 'America/Santiago'
const source = fs.readFileSync(path.join(__dirname, '../lib/vencimiento-cotizacion.ts'), 'utf8')
const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
const mod = { exports: {} }
new Function('module', 'exports', 'require', outputText)(mod, mod.exports, require)
const { diasHasta, estadoVencimiento, formatoFechaDMY, fechaPropuestaReagendar } = mod.exports

const hoy = new Date(2026, 9, 28, 21, 30) // 28-10 de noche, ya 29-10 en UTC

test('una fecha date de Laravel se lee como ese día, no el anterior', () => {
  assert.equal(diasHasta('2026-10-28T00:00:00.000000Z', hoy), 0)
  assert.equal(diasHasta('2026-10-28', hoy), 0)
  assert.equal(diasHasta('2026-10-27T00:00:00.000000Z', hoy), -1)
  assert.equal(formatoFechaDMY('2026-10-28T00:00:00.000000Z'), new Date(2026, 9, 28).toLocaleDateString('es-CL'))
})

test('reagendar una vencida para hoy o después la saca del rojo', () => {
  const vencida = { fecha_vencimiento: '2026-10-20T00:00:00.000000Z', ultima_gestion: null }
  assert.equal(estadoVencimiento(vencida, hoy), 'vencida')

  const aHoy = { fecha_vencimiento: '2026-10-28T00:00:00.000000Z', ultima_gestion: { fecha_vencimiento_nueva: '2026-10-28T00:00:00.000000Z' } }
  assert.equal(estadoVencimiento(aHoy, hoy), 'por_vencer')

  const aLaSemana = { fecha_vencimiento: '2026-11-10T00:00:00.000000Z', ultima_gestion: { fecha_vencimiento_nueva: '2026-11-10T00:00:00.000000Z' } }
  assert.equal(estadoVencimiento(aLaSemana, hoy), 'gestionada')
  assert.equal(estadoVencimiento({ fecha_vencimiento: '2026-11-10', ultima_gestion: null }, hoy), 'normal')
})

test('al reagendar una vencida se propone hoy, no la fecha vencida', () => {
  assert.equal(fechaPropuestaReagendar('2026-10-20T00:00:00.000000Z', hoy), '2026-10-28')
  assert.equal(fechaPropuestaReagendar('2026-11-05T00:00:00.000000Z', hoy), '2026-11-05')
  assert.equal(fechaPropuestaReagendar(null, hoy), '2026-10-28')
})
