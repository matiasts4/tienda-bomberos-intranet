const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const ts = require('typescript')
const file = path.join(__dirname, '../app/(auth)/comercial/cotizaciones/[id]/personalizacion/client.tsx')
const compiled = ts.transpileModule(fs.readFileSync(file, 'utf8'), { compilerOptions: {
    module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020, jsx: ts.JsxEmit.ReactJSX,
} }).outputText
const nodes = tree => !tree || typeof tree !== 'object' ? [] : [tree, ...[tree.props?.children].flat(Infinity).flatMap(nodes)]
const content = tree => typeof tree === 'string' || typeof tree === 'number' ? String(tree) : !tree ? '' : [tree.props?.children].flat(Infinity).map(content).join('')

// Handler/state regression tests; these do not replace browser/Radix accessibility tests.
const kitLib = {}
vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../lib/kit-plan.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, { exports: kitLib })
const proposalLib = {}
vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../lib/commercial-personalization-proposal.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, { exports: proposalLib })
const textColorLib = {}
vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../lib/personalization-text-colors.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, { exports: textColorLib })
const positionLib = {}
vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../lib/personalization-positions.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, { exports: positionLib })
const editorStateLib = {}, preparationLib = {}, orderConfigurationLib = {}, preparationStatusLib = {}
for (const [source, exported] of [['order-editor-state', editorStateLib], ['commercial-preparation', preparationLib], ['order-configuration', orderConfigurationLib], ['order-preparation-status', preparationStatusLib]]) {
    vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, `../lib/${source}.ts`), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, { exports: exported })
}
const saveAck = put => ({ ok: true, items: put.body.items.map(row => ({ id: row.id, configuration_hash: 'b'.repeat(64) })) })
const kitCatalog = [11, 12, 13].map((id, index) => ({ sku_generico_id: id, nombre: ['Pantalón', 'Camisa', 'Polerón'][index], sku_base: 'SKU-' + id, cantidad_por_kit: 1, regla: index ? 'opcional' : 'obligatorio', grupo_alternativa: '', permite_personalizar: true, disponible: true, variantes: [{ sku: 'VAR-' + id, genero: 'Unisex', talla: 'M', color: 'Azul' }] }))
const proposal = { cantidad: 20, talla: 'M', color: 'Azul', genero: 'Unisex', tecnicas: ['Bordado'], observaciones: [] }

test('commercial proposal is opt-in and accepted data remains editable without saving or sending', async () => {
    const h = harness({ configsById: { 1: { grupos: [], propuesta_comercial: proposal } } }); await h.flush()
    assert.match(h.content(), /Propuesta basada en la cotización/)
    assert.equal(h.hasButton('Usar propuesta'), true)
    assert.equal(h.puts.length, 0)
    assert.equal(nodes(h.render()).some(n => n.type === 'Input' && n.props.type === 'number'), false)
})

test('accepting creates the proposed curve and technique but leaves designs pending', async () => {
    const h = harness({ configsById: { 1: { grupos: [], propuesta_comercial: proposal } } }); await h.flush()
    h.button('Usar propuesta').props.onClick(); h.render()
    assert.doesNotMatch(h.content(), /Propuesta basada en la cotización/)
    assert.equal(h.groupQuantity().props.value, 20)
    h.groupQuantity().props.onChange({ target: { value: '8' } }); h.render()
    const pending = h.button('Guardar').props.onClick()
    const group = h.puts[0].body.items[0].grupos[0]
    assert.equal(group.tallas[0].cantidad, 8)
    assert.equal(group.tallas[0].color, 'Azul')
    assert.equal(group.matrices[0].tecnica, 'Bordado')
    assert.equal(group.matrices[0].posicion, '')
    assert.equal(group.matrices[0].tipo_contenido, '')
    assert.equal(h.puts[0].body.enviar, false)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})

test('closing the floating proposal leaves the editor unchanged and it does not reappear', async () => {
    const h = harness({ extraDetails: [{ id: 3, producto: 'Camisa Oxford', cantidad: 20 }], configsById: {
        1: { grupos: [], propuesta_comercial: proposal },
    } }); await h.flush()
    assert.match(h.find(n => n.props['aria-label'] === 'Propuesta comercial del ítem').props.className, /\bfixed\b/)
    h.button('Cerrar aviso').props.onClick(); h.render()
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Camisa Oxford').props.onClick(); h.render()
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Polera').props.onClick(); h.render()
    assert.doesNotMatch(h.content(), /Propuesta basada en la cotización|Usar propuesta/)
    assert.equal(h.puts.length, 0)
})

test('saved configuration and unsaved manual edits cannot be replaced by the proposal', async () => {
    const saved = harness({ configsById: { 1: { propuesta_comercial: proposal } } }); await saved.flush()
    assert.equal(saved.hasButton('Usar propuesta'), false)
    assert.match(saved.content(), /Grupo B/)
    const manual = harness({ configsById: { 1: { grupos: [], propuesta_comercial: proposal } } }); await manual.flush()
    manual.defineFirstSize(); manual.render()
    assert.equal(manual.hasButton('Usar propuesta'), false)
    assert.equal(manual.groupQuantity().props.value, 1)
})

test('kits and sent quotes ignore the proposal even if an older API supplies it', async () => {
    for (const options of [{ kit: true }, { sent: true }]) {
        const h = harness({ ...options, configsById: { 1: { propuesta_comercial: proposal } } }); await h.flush()
        assert.doesNotMatch(h.content(), /Propuesta basada en la cotización/)
    }
})

test('partial proposals show only provided details and preserve empty draft values without selecting catalog defaults', async () => {
    const h = harness({ configsById: { 1: { grupos: [], propuesta_comercial: { ...proposal, talla: null, genero: null } } } }); await h.flush()
    assert.doesNotMatch(h.content(), /Por definir/)
    assert.match(h.content(), /Color:Azul/)
    h.button('Usar propuesta').props.onClick(); h.render()
    const pending = h.button('Guardar').props.onClick()
    const size = h.puts[0].body.items[0].grupos[0].tallas[0]
    assert.equal(size.talla, '')
    assert.equal(size.genero, '')
    assert.equal(size.color, 'Azul')
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})

test('empty or generic curves never display a commercial proposal even when quantity or technique exists', async () => {
    for (const fields of [
        { talla: null, color: null, genero: null },
        { talla: '', color: '  ', genero: '-' },
        { talla: 'GENÉRICO', color: 'Generico', genero: 'Generico' },
        { talla: 'Por definir', color: 'Sin especificar', genero: 'Sin definir' },
    ]) {
        const h = harness({ configsById: { 1: { grupos: [], propuesta_comercial: { ...proposal, ...fields } } } }); await h.flush()
        assert.doesNotMatch(h.content(), /Propuesta basada en la cotización|Ver propuesta comercial|Usar propuesta/)
        assert.match(h.content(), /Grupos de prendas/)
        assert.equal(h.puts.length, 0)
    }
})

test('acceptance and dismissal belong to each item and direct delivery keeps the proposed curve editable', async () => {
    const h = harness({ extraDetails: [{ id: 3, producto: 'Camisa Oxford', cantidad: 20 }], configsById: {
        1: { grupos: [], propuesta_comercial: { ...proposal, tecnicas: [] } },
        3: { grupos: [], propuesta_comercial: proposal },
    } }); await h.flush()
    h.button('Usar propuesta').props.onClick(); h.render()
    assert.match(h.content(), /Grupos de prendas/)
    assert.equal(h.groupQuantity().props.value, 20)
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Camisa Oxford').props.onClick(); await h.flush()
    assert.equal(h.hasButton('Usar propuesta'), true)
    h.button('Cerrar aviso').props.onClick(); h.render()
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Polera').props.onClick(); h.render()
    assert.doesNotMatch(h.content(), /Propuesta basada en la cotización/)
    assert.equal(h.groupQuantity().props.value, 20)
})

test('the editor identifies the selected quote item and resets its step without losing another product draft', async () => {
    const h = harness({ extraDetails: [{ id: 3, n_item: 7, producto: 'Camisa Oxford', sku: 'OXFORD', cantidad: 2 }] }); await h.flush()
    assert.equal(content(h.find(n => n.props.id === 'active-product-title')), 'Estás editando: Polera')
    h.groupButton('Grupo B').props.onClick(); h.render()
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    h.button('Personalización').props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'Tabs').props.value, 'posiciones')
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Camisa Oxford').props.onClick(); h.render()
    const context = h.find(n => n.type === 'section' && n.props['aria-labelledby'] === 'active-product-title')
    assert.equal(content(h.find(n => n.props.id === 'active-product-title')), 'Estás editando: Camisa Oxford')
    assert.match(content(context), /Ítem #7 · Producto individualSKUSKU: OXFORD2 unidades/)
    assert.equal(nodes(context).some(n => n.type === 'details' && content(n).includes('SKU: OXFORD')), true)
    assert.equal(context.props['aria-live'], 'polite')
    assert.equal(h.find(n => n.type === 'fieldset').props['aria-labelledby'], 'active-product-title')
    assert.equal(h.hasButton('Prendas y solicitudes'), true)
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Polera').props.onClick(); h.render()
    h.groupButton('Grupo B').props.onClick(); h.render()
    assert.equal(h.groupQuantity().props.value, 2)
})

test('kit context identifies the child, physical quantity, parent and combination; removal describes its scope', async () => {
    const composiciones = [6, 4].map((cantidad, index) => ({ id: 'C' + index, nombre: 'Combinación ' + index, cantidad,
        componentes: [11, 12].map(sku_generico_id => ({ sku_generico_id, grupos: [{ nombre_grupo: 'Grupo A', tallas: [], matrices: [{ tipo_contenido: 'texto_fijo', valor_fijo: 'BOMBEROS' }], variables: [] }] })) }))
    const h = harness({ kit: true, savedKit: { kit_version: 1, catalogo: kitCatalog, composiciones } }); await h.flush()
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Camisa · Kit Cuartel · Item #1 · Combinación 1').props.onClick(); h.render()
    const context = h.find(n => n.type === 'section' && n.props['aria-labelledby'] === 'active-product-title')
    assert.match(content(context), /Estás editando: CamisaÍtem #1 · Prenda del kit: Kit Cuartel · Combinación 1SKUSKU: SKU-124 unidades/)
    assert.equal(h.find(n => n.type === 'fieldset').props['aria-labelledby'], 'active-product-title')
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.historical, true)
    assert.equal(h.hasButton('Prendas y solicitudes'), true)
    assert.equal(h.puts.length, 0)
})

test('names belong to the matrix as an optional subsection, not a third main stage', async () => {
    const h = harness({ matrix: { tipo_contenido: 'texto_variable' } }); await h.flush()
    assert.equal(h.hasButton('Prendas y solicitudes'), true)
    assert.equal(h.hasButton('Personalización'), true)
    h.button('Personalización').props.onClick(); h.render()
    h.find(n => n.type === 'Tabs').props.onValueChange('variables'); h.render()
    assert.equal(h.find(n => n.type === 'Tabs').props.value, 'variables')
    for (const group of ['Grupo A', 'Grupo B']) {
        h.groupButton(group).props.onClick(); h.render()
        h.find(n => n.type === 'Select' && n.props.value === 'texto_variable').props.onValueChange('texto_fijo'); h.render()
    }
    assert.equal(h.find(n => n.type === 'Tabs').props.value, 'posiciones')
    assert.equal(h.find(n => n.type === 'TabsTrigger' && n.props.value === 'variables').props.disabled, true)
    assert.equal(h.puts.length + h.posts.length, 0)
})

test('read-only context describes viewing and failed loads never claim a product is being edited', async () => {
    const h = harness({ sent: true }); await h.flush()
    const context = h.find(n => n.type === 'section' && n.props['aria-labelledby'] === 'active-product-title')
    assert.match(content(context), /Estás viendo: Polera.*Solo lectura/)
    assert.doesNotMatch(h.content(), /Estás editando|Editando/)
    const failed = harness({ failLoad: true }); await failed.flush()
    assert.equal(nodes(failed.render()).some(n => n.props.id === 'active-product-title'), false)
})
// Historical fixtures explicitly have commitments, so the suite continues to
// exercise compatibility. New-flow cases opt into a wholly uncommitted quote.
function harness({ failLoad = false, sent = false, matrix = {}, kit = false, savedKit = null, extraDetails = [], configsById = {}, designDraft = null, preparation = null, versioned = false, stockState = { status: 'ready', optionsByItem: {}, kitCatalogByItem: {}, refresh: async () => true } } = {}) {
    const slots = [], effects = [], puts = [], posts = [], gets = [], navigation = []
    let cursor = 0, tree, broken = failLoad, currentLeaveGuard
    let failSummary = false
    let summaryReader = null
    const preparationData = preparation ?? { available: true, supply_available: true, can_send: false, orders: [], items: [{ id: 1, number: 1, product: 'Polera', quantity: 2, revision: null,
        confirmed: false, design_ready: false, received: false, design_hash: 'd'.repeat(64), design_errors: [],
        curve: { hash: 'c'.repeat(64), errors: [], rows: [{ key: 'SKU', sku: 'SKU', quantity: 2, pending: 2, available: 4, reserved: 0, issued: 0, ambiguous: false }] } }] }
    const groups = ['A', 'B'].map(letter => ({ nombre_grupo: `Grupo ${letter}`, tallas: [{ genero: 'Unisex', color: 'Azul', talla: 'M', cantidad: 1 }],
        matrices: [{ posicion: 'Frente', tecnica: 'Bordado', tipo_contenido: 'texto_fijo', valor_fijo: 'BOMBEROS', ...matrix }], variables: [] }))
    const react = {
        useMemo(fn) { return fn() },
        useState(initial) { const id = cursor++; if (!(id in slots)) slots[id] = initial; return [slots[id], v => { slots[id] = typeof v === 'function' ? v(slots[id]) : v }] },
        useRef(initial) { const id = cursor++; return slots[id] ??= { current: initial } },
        useEffect(fn, deps) {
            const id = cursor++, prev = slots[id]
            if (!prev || deps.some((dep, index) => dep !== prev[index])) { slots[id] = deps; effects.push(fn) }
        },
    }
    const exported = {}
    const router = { push: value => navigation.push(value) }
    vm.runInNewContext(compiled, {
        crypto: require('node:crypto').webcrypto,
        exports: exported, process: { env: {} }, URL,
        window: { addEventListener() {}, removeEventListener() {}, location: {
            href: 'http://localhost:3000/comercial/cotizaciones/1/personalizacion', origin: 'http://localhost:3000',
            assign: value => navigation.push(value),
        } },
        require(name) {
            if (name === '@/hooks/useOrderStock') return { useOrderStock: () => stockState }
            if (name === '@/hooks/useOrderLeaveGuard') return { useOrderLeaveGuard: options => {
                currentLeaveGuard = {
                    requestLeave(intent) { if (options.busy) options.onBlocked('busy'); else if (options.dirty) options.onConfirm(intent); else options.onNavigate(intent.href) },
                    proceed(intent) { options.onNavigate(intent.href) },
                }
                return currentLeaveGuard
            } }
            if (name === '@/lib/kit-plan') return kitLib
            if (name === '@/lib/commercial-personalization-proposal') return proposalLib
            if (name === '@/lib/personalization-text-colors') return textColorLib
            if (name === '@/lib/personalization-positions') return positionLib
            if (name === '@/lib/order-editor-state') return editorStateLib
            if (name === '@/lib/order-configuration') return orderConfigurationLib
            if (name === '@/lib/commercial-preparation') return preparationLib
            if (name === '@/lib/order-preparation-status') return preparationStatusLib
            if (name === 'react') return react
            if (name === 'react/jsx-runtime') {
                const jsx = (type, props) => ({ type, props: type === 'OrderPreparationPanel' && typeof props.children === 'function' ? { ...props, children: props.children({
                    status: 'ready', getGroup: (itemId, childKey, groupId) => preparationData.items.find(item => item.id === itemId)?.groups?.find(group => group.id === groupId && group.child_key === childKey),
                    renderToolbar: () => null, renderGroupActions: () => null, renderItemDocuments: () => null, renderLegacyOperations: () => null,
                }) } : props })
                return { jsx, jsxs: jsx }
            }
            if (name === 'next/navigation') return { useRouter: () => router, useParams: () => ({ id: '1' }) }
            if (name === 'sonner') return { toast: new Proxy({}, { get: () => () => {} }) }
            if (name === '@/lib/api') return { api: {
                get: async url => {
                    gets.push(url)
                    if (url.endsWith('/preparacion')) { if (failSummary) throw new Error('summary unavailable'); return summaryReader ? summaryReader() : structuredClone(preparationData) }
                    if (url === '/api/cotizaciones/1') return { numero: 'CT-1', estado_personalizacion: sent ? 'sent_to_personalization' : null,
                        detalles: [{ id: 1, n_item: 1, producto: kit ? 'Kit Cuartel' : 'Polera', cantidad: kit ? 10 : 2 }, ...extraDetails, { id: 2, producto: 'BANDERA', cantidad: 1 }] }
                    if (broken) throw new Error('network failure')
                    if (url.endsWith('/design-draft')) return designDraft ?? { available: false, items: [] }
                    return { configuration_hash: 'a'.repeat(64), configuration_context_hash: 'e'.repeat(64), es_informativo_cotizacion: url.includes('/2/'), permite_personalizar: true, modo_kit: kit, kit_catalogo: kitCatalog, kit: savedKit, grupos: kit ? [] : structuredClone(groups), has_commitments: !versioned,
                        options: { generos: ['Unisex'], colores: ['Azul'], tallas: ['M'] }, ...configsById[Number(url.split('/')[3])] }
                },
                put: (url, body) => new Promise((resolve, reject) => puts.push({ url, body, resolve, reject })),
                post: (url, body) => new Promise((resolve, reject) => posts.push({ url, body, resolve, reject })),
            } }
            return new Proxy({}, { get: (_, key) => key })
        },
    })
    const render = () => { cursor = 0; tree = exported.PersonalizacionClient(); for (const effect of effects.splice(0)) effect(); return tree }
    const flush = async () => { await new Promise(resolve => setImmediate(resolve)); render(); render() }
    const find = predicate => {
        let n = nodes(tree).find(predicate)
        if (!n) {
            const design = nodes(tree).find(n => n.type === 'Button' && content(n).trim() === 'Personalización')
            if (design && !design.props['aria-pressed']) { design.props.onClick(); render(); n = nodes(tree).find(predicate) }
        }
        assert.ok(n, 'Expected control'); return n
    }
    const button = label => find(n => n.type === 'Button' && content(n).trim() === label)
    const groupButton = label => { const button = find(n => n.type === 'Button' && n.props.id?.startsWith('order-group-') && content(n).startsWith(label)); return { ...button, props: { ...button.props, onClick: () => { if (!button.props['aria-expanded']) button.props.onClick() } } } }
    const defineFirstSize = () => {
        const editor = find(n => n.type === 'GlobalOrderCurveEditor'), config = editor.props.config
        const row = { id: 'chosen-size', talla: 'M', color: 'Azul', genero: 'Unisex', cantidad: 1 }
        const groups = config.groups.length ? config.groups : [{ id: 'chosen-group', nombre_grupo: 'Grupo A', assignments: [], matrices: [], variables: [], supply_source: null, personalization_required: false }]
        editor.props.onChange({ ...config, curve: [...config.curve, row], groups: groups.map((group, i) => i === 0 ? { ...group, assignments: [...group.assignments, { curve_row_id: row.id, quantity: 1 }] } : group) })
    }
    const groupQuantity = () => {
        const editor = find(n => n.type === 'GroupAssignmentEditor')
        const { config, group } = editor.props
        const assignment = group.assignments[0]
        return { props: { value: assignment?.quantity ?? 0, onChange: event => {
            const quantity = Number(event.target.value), row = config.curve.find(row => row.id === assignment?.curve_row_id)
            assert.ok(row, 'The state test needs an assigned row')
            const curve = config.curve.map(candidate => candidate.id === row.id ? { ...candidate, cantidad: candidate.cantidad + quantity - assignment.quantity } : candidate)
            const groups = config.groups.map(candidate => candidate.id === group.id ? { ...candidate, assignments: candidate.assignments.map(value => value.curve_row_id === row.id ? { ...value, quantity } : value) } : candidate)
            find(n => n.type === 'GlobalOrderCurveEditor').props.onChange({ ...config, curve, groups })
        } } }
    }
    const sendAction = () => find(n => n.type === 'OrderPreparationFooter').props.onSend()
    render()
    return { render, flush, find, button, groupButton, groupQuantity, defineFirstSize, sendAction, puts, posts, gets, preparationData, navigation,
        leave: href => currentLeaveGuard.requestLeave({ kind: 'url', href }),
        hasButton: label => nodes(tree).some(n => n.type === 'Button' && content(n).trim() === label),
        failSummary: value => { failSummary = value }, setSummaryReader: fn => { summaryReader = fn }, content: () => content(tree), repair: () => { broken = false } }
}

test('dirty tracking identifies edited items and clears when actual values are restored', async () => {
    const h = harness({ extraDetails: [{ id: 3, producto: 'Camisa', cantidad: 2 }] }); await h.flush()
    assert.equal(h.hasButton('Guardar'), false)
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    assert.match(h.content(), /Cambios sin guardar en 1 ítem/)
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Camisa').props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirtyItemIds.join(','), '1')
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Polera').props.onClick(); h.render()
    h.groupQuantity().props.onChange({ target: { value: '1' } }); h.render()
    assert.equal(h.hasButton('Guardar'), false)
    assert.doesNotMatch(h.content(), /Cambios sin guardar|Por guardar/)
})

test('save and review waits for both acknowledgement and refreshed summary; it never confirms or requests stock', async () => {
    const h = harness(); await h.flush()
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    let resolveSummary
    h.setSummaryReader(() => new Promise(resolve => { resolveSummary = resolve }))
    const action = h.button('Guardar').props.onClick()
    await h.button('Guardar').props.onClick()
    assert.equal(h.puts.length, 1)
    h.puts[0].resolve(saveAck(h.puts[0])); await h.flush()
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.active, true)
    assert.match(h.content(), /actualizando estado/)
    assert.equal(h.puts[0].body.enviar, false)
    resolveSummary(h.preparationData); await action; await h.flush()
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, false)
    assert.equal(h.posts.length, 0)
})

test('acknowledged save with failed refresh stays saved, preserves the editor and blocks stale workshop readiness', async () => {
    const h = harness(); await h.flush(); h.failSummary(true)
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    const action = h.button('Guardar').props.onClick()
    h.puts[0].resolve(saveAck(h.puts[0])); await action; await h.flush()
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.active, true)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, false)
    assert.match(h.content(), /quedaron guardados.*No pudimos actualizar/)
    assert.equal(h.find(n => n.type === 'OrderPreparationFooter').props.summary, null)
    h.find(n => n.type === 'OrderPreparationPanel').props.onSummaryChange(h.preparationData); h.render()
    assert.equal(h.puts.length, 1)
    assert.equal(h.posts.length, 0)
    assert.equal(h.groupQuantity().props.value, 2)
})

test('failed contextual save keeps unsaved data and never navigates or marks the design ready', async () => {
    const h = harness(); await h.flush()
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    const action = h.button('Guardar').props.onClick()
    h.puts[0].reject(new TypeError('Failed to fetch')); await action; await h.flush()
    assert.match(h.content(), /Cambios sin guardar/)
    assert.equal(h.groupQuantity().props.value, 2)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, true)
    assert.equal(h.posts.length + h.navigation.length, 0)
})

test('design readiness is explicit in the editor, acknowledged by the server and invalidated visually by edits', async () => {
    const h = harness(); await h.flush()
    const ready = structuredClone(h.preparationData); ready.items[0].confirmed = true; ready.items[0].design_ready = true
    h.find(n => n.type === 'OrderPreparationPanel').props.onSummaryChange(ready); h.render()
    h.button('Personalización').props.onClick(); h.render()
    h.find(n => n.type === 'Textarea' && n.props.placeholder?.startsWith('Ej: CUERPO')).props.onChange({ target: { value: 'CAMBIO' } }); h.render()
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, true)
    assert.equal(h.find(n => n.type === 'OrderPreparationFooter').props.disabled, true)
    assert.equal(h.posts.length + h.puts.length, 0)
})

test('failed status refresh after historical design save never reports operational completion', async () => {
    const h = harness(); await h.flush()
    h.failSummary(true)
    h.button('Personalización').props.onClick(); h.render()
    h.find(n => n.type === 'Textarea' && n.props.placeholder?.startsWith('Ej: CUERPO')).props.onChange({ target: { value: 'CAMBIO' } }); h.render()
    const action = h.button('Guardar').props.onClick()
    h.puts[0].resolve(saveAck(h.puts[0])); await action; await h.flush()
    assert.equal(h.find(n => n.type === 'OrderPreparationFooter').props.summary, null)
    assert.match(h.content(), /No pudimos actualizar/)
    assert.equal(h.posts.length, 0)
})

test('informational products disappear from the working list and atomic payload, not the original quote', async () => {
    const h = harness(); await h.flush()
    assert.match(h.content(), /Productos \(1\)/)
    assert.match(h.content(), /Incluidos sin personalización \(1\)/)
    assert.match(h.content(), /BANDERA/)
    assert.match(h.content(), /No va al taller · Se mantiene en cotización y PDF/)
    const informational = h.find(n => n.type === 'section' && n.props['aria-labelledby'] === 'informational-items-title')
    assert.equal(nodes(informational).some(n => n.type === 'button'), false)
    const pending = h.sendAction()
    assert.equal(h.puts.length, 1)
    assert.equal(h.puts[0].url, '/api/cotizaciones/1/personalizacion-configuracion')
    assert.equal(h.puts[0].body.items.length, 1)
    assert.equal(h.puts[0].body.items[0].id, 1)
    assert.equal(h.puts[0].body.items.some(item => item.id === 2), false)
    h.puts[0].resolve({ ok: true, message: 'Enviado' }); await pending
})

test('editing group B does not jump back to group A and draft stays out of the workshop', async () => {
    const h = harness(); await h.flush()
    h.groupButton('Grupo B').props.onClick(); h.render()
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    assert.match(h.content(), /Grupo B/)
    const pending = h.button('Guardar').props.onClick()
    assert.equal(h.puts[0].body.enviar, false)
    assert.equal(h.puts[0].body.items[0].grupos[1].tallas[0].cantidad, 2)
    assert.equal(h.puts[0].body.items[0].grupos[0].tallas[0].cantidad, 1)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
    assert.equal(h.navigation.length, 0)
})

test('failed load blocks saving rather than filling blank configs; retry loads real data', async () => {
    const h = harness({ failLoad: true }); await h.flush()
    assert.equal(h.hasButton('Guardar'), false)
    assert.equal(h.puts.length, 0)
    h.repair(); await h.button('Reintentar carga').props.onClick(); await h.flush()
    assert.equal(h.hasButton('Guardar'), false, 'A recovered clean editor does not ask to save unchanged data')
    assert.match(h.content(), /Grupo B/)
})

test('double click sends once, server validation uses a modal and preserves all changes', async () => {
    const h = harness(); await h.flush()
    const pending = h.sendAction()
    await h.sendAction()
    assert.equal(h.puts.length, 1)
    h.puts[0].reject({ status: 422, data: { errors: { grupos: ['Polera: falta completar el logo.'] } } })
    await pending; h.render()
    assert.match(h.content(), /Polera: falta completar el logo/)
    assert.equal(h.find(n => n.type === 'AlertDialog').props.open, true)
    assert.match(h.content(), /Grupo B/)
    assert.equal(h.navigation.length, 0)
})

test('already sent quotes are read-only and their save handlers cannot dispatch again', async () => {
    const h = harness({ sent: true }); await h.flush()
    assert.equal(h.find(n => n.type === 'fieldset').props.disabled, true)
    assert.doesNotMatch(h.content(), /Guardar y enviar al taller/)
    await h.sendAction()
    assert.equal(h.puts.length, 0)
})

test('switching the shared item tabs preserves a historical draft without issuing operations', async () => {
    const h = harness(); await h.flush()
    h.groupButton('Grupo B').props.onClick(); h.render()
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    h.button('Personalización').props.onClick(); h.render()
    h.button('Prendas y solicitudes').props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.active, true)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, true)
    assert.equal(h.groupQuantity().props.value, 2)
    assert.equal(h.puts.length + h.posts.length, 0)
})

test('discard restores every item to the latest acknowledged save, including a different selected item', async () => {
    const h = harness({ extraDetails: [{ id: 3, producto: 'Camisa', cantidad: 2 }] }); await h.flush()
    const quantity = () => h.groupQuantity()
    quantity().props.onChange({ target: { value: '2' } }); h.render()
    const save = h.button('Guardar').props.onClick()
    h.puts[0].resolve(saveAck(h.puts[0])); await save; await h.flush()
    quantity().props.onChange({ target: { value: '3' } }); h.render()
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Camisa').props.onClick(); h.render()
    quantity().props.onChange({ target: { value: '4' } }); h.render()
    h.button('Descartar cambios').props.onClick(); h.render()
    h.button('Descartar borrador').props.onClick(); h.render()
    assert.equal(quantity().props.value, 1)
    assert.equal(h.hasButton('Guardar'), false)
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Polera').props.onClick(); h.render()
    assert.equal(quantity().props.value, 2, 'The latest server acknowledgement is the baseline, not the first loaded version')
    assert.equal(h.puts.length, 1)
    assert.equal(h.posts.length, 0)
    assert.equal(h.navigation.length, 0)
})

test('discarding before continuing restores the saved curve and enters garments without a mutation', async () => {
    const h = harness(); await h.flush()
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    h.button('Personalización').props.onClick(); h.render()
    h.button('Descartar cambios').props.onClick(); h.render()
    h.button('Descartar borrador').props.onClick(); h.render()
    h.button('Prendas y solicitudes').props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, false)
    assert.equal(h.groupQuantity().props.value, 1)
    assert.equal(h.puts.length + h.posts.length, 0)
})

test('discard restores independent kit children after freely switching between their drafts', async () => {
    const savedKit = { kit_version: 1, catalogo: kitCatalog, composiciones: [{ id: 'C1', nombre: 'Equipo', cantidad: 10,
        componentes: [11, 12].map(sku_generico_id => ({ sku_generico_id, grupos: [{ nombre_grupo: 'Grupo A',
            tallas: [{ genero: 'Unisex', color: 'Azul', talla: 'M', cantidad: 1 }], matrices: [], variables: [] }] })) }] }
    const h = harness({ kit: true, savedKit }); await h.flush()
    const quantity = () => h.groupQuantity()
    const child = name => h.find(n => n.type === 'button' && n.props['aria-label'] === `Personalizar ${name} · Kit Cuartel · Item #1 · Equipo`)
    quantity().props.onChange({ target: { value: '2' } }); h.render()
    child('Camisa').props.onClick(); h.render()
    quantity().props.onChange({ target: { value: '3' } }); h.render()
    child('Pantalón').props.onClick(); h.render()
    assert.equal(quantity().props.value, 2, 'Internal child navigation retains the shared draft')
    assert.equal(nodes(h.render()).some(n => n.type === 'AlertDialog' && n.props.open), false)
    h.button('Descartar cambios').props.onClick(); h.render()
    h.button('Descartar borrador').props.onClick(); h.render()
    assert.equal(quantity().props.value, 1)
    child('Camisa').props.onClick(); h.render()
    assert.equal(quantity().props.value, 1)
    assert.equal(h.hasButton('Guardar'), false)
    assert.equal(h.puts.length, 0)
    assert.equal(h.posts.length, 0)
})

test('leaving preserves the requested URL after cancel, save or explicit discard', async () => {
    for (const [href, action, expected] of [
        ['http://localhost:3000/compras?estado=pendiente#ordenes', 'save', '/compras?estado=pendiente#ordenes'],
        ['https://example.org/pedido/42?desde=erp#detalle', 'discard', 'https://example.org/pedido/42?desde=erp#detalle'],
    ]) {
        const h = harness(); await h.flush()
        h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
        h.leave(href); h.render()
        assert.equal(h.navigation.length, 0)
        h.button('Seguir editando').props.onClick(); h.render()
        assert.equal(h.groupQuantity().props.value, 2)
        assert.equal(h.navigation.length, 0)
        h.leave(href); h.render()
        if (action === 'save') {
            const saving = h.button('Guardar y continuar').props.onClick()
            assert.equal(h.navigation.length, 0)
            h.puts[0].resolve(saveAck(h.puts[0])); await saving; await h.flush()
            assert.equal(h.puts.length, 1)
            assert.equal(h.puts[0].body.enviar, false)
        } else {
            h.button('Salir sin guardar').props.onClick(); h.render()
            assert.equal(h.puts.length, 0)
        }
        assert.deepEqual(h.navigation, [expected])
        assert.equal(h.posts.length, 0)
    }
})

test('failed save when leaving keeps the exact destination pending and preserves all edited values', async () => {
    const h = harness(); await h.flush()
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    h.leave('/comercial/cotizaciones?pagina=3'); h.render()
    const save = h.button('Guardar y continuar').props.onClick()
    h.puts[0].reject(new TypeError('Failed to fetch')); await save; await h.flush()
    assert.deepEqual(h.navigation, [])
    assert.equal(h.groupQuantity().props.value, 2)
    assert.ok(nodes(h.render()).some(n => n.type === 'AlertDialog' && n.props.open && /Tienes cambios sin guardar/.test(content(n))))
    h.button('Salir sin guardar').props.onClick(); h.render()
    assert.deepEqual(h.navigation, ['/comercial/cotizaciones?pagina=3'])
})

test('issued designs save an isolated revision using its version and never dispatch again', async () => {
    const grupos = [{ nombre_grupo: 'Grupo A', tallas: [{ genero: 'Unisex', color: 'Azul', talla: 'M', cantidad: 2 }],
        matrices: [{ posicion: 'Frente', tecnica: 'Bordado', tipo_contenido: 'texto_fijo', valor_fijo: 'REVISIÓN' }], variables: [] }]
    const h = harness({ sent: true, designDraft: { available: true, can_save: true, items: [{ id: 1, version: 4, grupos }] } }); await h.flush()
    assert.match(h.content(), /Estás revisando el diseño de: Polera/)
    assert.equal(h.find(n => n.type === 'fieldset' && n.props.id === 'order-design-workspace').props.disabled, false)
    assert.equal(h.find(n => n.type === 'GlobalOrderCurveEditor').props.disabled, true, 'Physical sizes stay fixed after issue')
    h.button('Personalización').props.onClick(); h.render()
    h.find(n => n.type === 'Textarea' && n.props.placeholder?.startsWith('Ej: CUERPO')).props.onChange({ target: { value: 'DISEÑO NUEVO' } }); h.render()
    const pending = h.button('Guardar').props.onClick()
    assert.equal(h.puts[0].url, '/api/cotizaciones/1/design-draft')
    assert.equal(h.puts[0].body.items[0].version, 4)
    assert.equal(h.puts[0].body.items[0].grupos[0].matrices[0].valor_fijo, 'DISEÑO NUEVO')
    assert.match(h.content(), /Cambios sin guardar/)
    assert.equal(h.puts[0].body.enviar, false)
    h.puts[0].resolve({ available: true, items: [{ id: 1, version: 5 }] }); await pending; h.render()
    await h.sendAction()
    assert.equal(h.puts.length, 1)
    assert.equal(h.navigation.length, 0)
})

test('a versioned issued design opens its current revision instead of the original order configuration', async () => {
    const legacy = [{ nombre_grupo: 'Grupo actual', tallas: [{ talla: 'M', color: 'Azul', genero: 'Unisex', cantidad: 2 }],
        matrices: [{ posicion: 'Frente', tecnica: 'Bordado', tipo_contenido: 'texto_fijo', valor_fijo: 'ORIGINAL EMITIDO' }], variables: [] }]
    const original = orderConfigurationLib.toOrderConfiguration(legacy)
    original.groups[0].supply_source = 'external'
    const revision = structuredClone(original)
    revision.groups[0].matrices[0].valor_fijo = 'REVISIÓN GUARDADA'
    const h = harness({ sent: true, configsById: { 1: { order_configuration: original } },
        designDraft: { available: true, can_save: true, items: [{ id: 1, version: 7,
            grupos: orderConfigurationLib.materializeGroups(revision), order_configuration: revision }] } })
    await h.flush()
    const curve = h.find(n => n.type === 'GlobalOrderCurveEditor')
    assert.equal(curve.props.disabled, true)
    assert.equal(curve.props.config.groups[0].matrices[0].valor_fijo, 'REVISIÓN GUARDADA')
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.disabled, true)
    h.button('Personalización').props.onClick(); h.render()
    const text = h.find(n => n.type === 'Textarea' && n.props.placeholder?.startsWith('Ej: CUERPO'))
    assert.equal(text.props.value, 'REVISIÓN GUARDADA')
    text.props.onChange({ target: { value: 'SIGUIENTE REVISIÓN' } }); h.render()
    const pending = h.button('Guardar').props.onClick()
    const item = h.puts[0].body.items[0]
    assert.equal(h.puts[0].url, '/api/cotizaciones/1/design-draft')
    assert.equal(item.version, 7)
    assert.equal(item.grupos.order_version, 2)
    assert.equal(item.grupos.groups[0].matrices[0].valor_fijo, 'SIGUIENTE REVISIÓN')
    assert.equal(item.grupos.groups[0].supply_source, 'external')
    assert.equal(item.grupos.groups[0].assignments[0].quantity, 2)
    assert.equal(h.puts[0].body.enviar, false)
    h.puts[0].resolve({ available: true, items: [{ id: 1, version: 8 }] }); await pending
    assert.equal(original.groups[0].matrices[0].valor_fijo, 'ORIGINAL EMITIDO')
    assert.equal(h.posts.length, 0)
})

test('uncommitted items use a global curve before groups and save the versioned configuration without requesting garments', async () => {
    const h = harness({ versioned: true, configsById: { 1: { grupos: [] } } }); await h.flush()
    assert.match(h.content(), /Indica primero las cantidades por talla/)
    assert.equal(h.button('Crear grupo').props.disabled, true)
    const curve = h.find(n => n.type === 'GlobalOrderCurveEditor')
    assert.equal(curve.props.config.groups.length, 0)
    assert.equal(curve.props.config.curve.length, 0)
    curve.props.onChange({ order_version: 2, curve: [{ id: 'curve_1', talla: 'M', genero: 'Unisex', color: 'Azul', cantidad: 2 }], groups: [] }); h.render()
    assert.equal(h.button('Crear grupo').props.disabled, false)
    const pending = h.button('Guardar').props.onClick()
    assert.equal(h.puts[0].body.items[0].grupos.order_version, 2)
    assert.equal(h.puts[0].body.items[0].grupos.curve[0].cantidad, 2)
    assert.equal(h.puts[0].body.items[0].grupos.groups.length, 0)
    assert.equal(h.puts[0].body.enviar, false)
    assert.equal(h.posts.length, 0)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})

test('curve stays above an exclusive group accordion and switching or closing preserves the entire draft', async () => {
    const h = harness({ versioned: true }); await h.flush()
    const tree = () => nodes(h.render())
    const headers = () => nodes(h.find(n => n.props['aria-label'] === 'Grupos del ítem')).filter(n => n.type === 'Button' && n.props['aria-expanded'] !== undefined)
    assert.equal(tree().some(n => n.type === 'TabsList' && n.props['aria-label'] === 'Preparación del producto'), false)
    assert.ok(tree().findIndex(n => n.type === 'GlobalOrderCurveEditor') < tree().findIndex(n => n.props['aria-label'] === 'Grupos del ítem'))
    h.find(n => n.type === 'GroupAssignmentEditor').props.onChange({ nombre_grupo: 'Guardia nocturna' }); h.render()
    headers()[1].props.onClick(); h.render()
    assert.equal(headers().filter(n => n.props['aria-expanded']).length, 1)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.group.nombre_grupo, 'Grupo B')
    headers()[1].props.onClick(); h.render()
    assert.equal(headers().filter(n => n.props['aria-expanded']).length, 0)
    assert.equal(tree().some(n => n.type === 'GroupAssignmentEditor'), false)
    assert.equal(h.find(n => n.type === 'GlobalOrderCurveEditor').props.config.groups[0].nombre_grupo, 'Guardia nocturna')
    headers()[0].props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.group.nombre_grupo, 'Guardia nocturna')
    assert.equal(h.puts.length + h.posts.length, 0)
})

test('creating a group opens only that group with no automatic allocation or supply source and preserves other designs', async () => {
    const h = harness({ versioned: true }); await h.flush()
    const before = structuredClone(h.find(n => n.type === 'GlobalOrderCurveEditor').props.config)
    h.button('Crear grupo').props.onClick(); h.render()
    const headers = nodes(h.find(n => n.props['aria-label'] === 'Grupos del ítem')).filter(n => n.type === 'Button' && n.props['aria-expanded'] !== undefined)
    assert.equal(headers.length, 3)
    assert.equal(headers.filter(n => n.props['aria-expanded']).length, 1)
    assert.equal(headers[2].props['aria-expanded'], true)
    const editor = h.find(n => n.type === 'GroupAssignmentEditor')
    assert.equal(editor.props.group.assignments.length, 0)
    assert.equal(editor.props.group.supply_source, null)
    assert.equal(editor.props.group.color, null)
    assert.equal(editor.props.group.genero, null)
    assert.deepEqual(JSON.parse(JSON.stringify(editor.props.config.groups.slice(0, 2))), before.groups)
    assert.deepEqual(JSON.parse(JSON.stringify(editor.props.config.curve)), before.curve)
    assert.equal(h.puts.length + h.posts.length, 0)
})

test('versioned preparation names the tasks and scopes design navigation to the selected group without issuing requests', async () => {
    const h = harness({ versioned: true }); await h.flush()
    assert.equal(h.hasButton('Solicitar y seguir prendas'), false)
    assert.equal(h.hasButton('Preparar pedido'), false)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.embedded, true)
    assert.match(h.content(), /Grupos/)
    assert.match(h.content(), /2 de 2 prendas asignadas/)
    h.find(n => n.type === 'GroupAssignmentEditor').props.onChange({ nombre_grupo: 'Guardia diurna' }); h.render()
    h.button('Personalización').props.onClick(); h.render()
    assert.equal(nodes(h.render()).some(n => n.type === 'GroupDesignDialog'), false)
    assert.match(h.content(), /Guardia diurna/)
    assert.doesNotMatch(h.content(), /Diseño para las.*prendas seleccionadas/)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.active, true)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, true)
    assert.ok(h.find(n => n.type === 'OrderPreparationPanel').props.dirtyGroupKeys.length > 0)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.group.nombre_grupo, 'Guardia diurna')
    assert.match(h.content(), /Guardia diurna/)
    assert.equal(h.puts.length, 0)
    assert.equal(h.posts.length, 0)
})

test('group design preserves the same draft and group when switching item tabs without saving', async () => {
    const h = harness({ versioned: true }); await h.flush()
    h.button('Personalización').props.onClick(); h.render()
    const groupId = h.find(n => n.type === 'GroupAssignmentEditor').props.group.id
    h.find(n => n.type === 'Textarea' && n.props.placeholder?.startsWith('Ej: CUERPO')).props.onChange({ target: { value: 'DISEÑO EN BORRADOR' } }); h.render()
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, true)
    h.button('Prendas y solicitudes').props.onClick(); h.render()
    assert.equal(nodes(h.render()).some(n => n.type === 'GroupDesignDialog'), false)
    h.button('Personalización').props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'Textarea' && n.props.placeholder?.startsWith('Ej: CUERPO')).props.value, 'DISEÑO EN BORRADOR')
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.group.id, groupId)
    assert.equal(h.puts.length + h.posts.length, 0)
    const groups = nodes(h.render()).filter(n => n.type === 'article' && n.props['data-order-group'])
    assert.deepEqual(groups.map(n => n.props['data-group-tone']), ['muted', 'light'])
    assert.match(groups[0].props.className, /bg-slate-100/)
    assert.match(groups[1].props.className, /bg-white/)
})

test('versioned items keep design completion separate from the legacy ready action even after confirming sizes', async () => {
    const h = harness({ versioned: true }); await h.flush()
    const summary = structuredClone(h.preparationData)
    summary.items[0].confirmed = true
    h.find(n => n.type === 'OrderPreparationPanel').props.onSummaryChange(summary); h.render()
    assert.doesNotMatch(h.content(), /Marcar diseño listo/)
    assert.equal(h.hasButton('Ir a solicitar prendas'), false)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.active, true)
    assert.equal(h.posts.length, 0)
})

test('one product keeps its selector and context visible across tabs without losing the draft', async () => {
    const h = harness({ versioned: true }); await h.flush()
    const selector = () => h.find(n => n.props.id === 'order-products')
    assert.doesNotMatch(selector().props.className, /(?:^| )hidden(?: |$)/)
    assert.match(content(h.find(n => n.props.id === 'active-product-title')), /Polera/)
    assert.equal(h.hasButton('Ver producto'), false)
    h.find(n => n.type === 'GroupAssignmentEditor').props.onChange({ nombre_grupo: 'Guardia nocturna' }); h.render()
    h.button('Personalización').props.onClick(); h.render()
    assert.doesNotMatch(selector().props.className, /(?:^| )hidden(?: |$)/)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.group.nombre_grupo, 'Guardia nocturna')
    h.button('Prendas y solicitudes').props.onClick(); h.render()
    assert.doesNotMatch(selector().props.className, /(?:^| )hidden(?: |$)/)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, true)
    assert.equal(h.puts.length + h.posts.length, 0)
})

test('kits and multiple products retain their visible selectors and independent item hierarchy', async () => {
    const savedKit = { kit_version: 1, catalogo: kitCatalog, composiciones: [{ id: 'C1', nombre: 'Equipo', cantidad: 10,
        componentes: [11, 12].map(sku_generico_id => ({ sku_generico_id, grupos: [] })) }] }
    for (const options of [{ kit: true, savedKit }, { extraDetails: [{ id: 3, producto: 'Camisa', cantidad: 2 }] }]) {
        const h = harness(options); await h.flush()
        assert.doesNotMatch(h.find(n => n.props.id === 'order-products').props.className, /(?:^| )hidden(?: |$)/)
        assert.equal(h.hasButton('Ver producto'), false)
        if (options.kit) assert.equal(nodes(h.render()).filter(n => n.props['aria-label']?.startsWith('Prendas de Kit Cuartel')).length, 1)
        else assert.equal(nodes(h.render()).filter(n => n.type === 'button' && n.props['aria-label']?.startsWith('Personalizar ')).length, 2)
        assert.equal(h.puts.length + h.posts.length, 0)
    }
})

test('group selection has one active group and its design action preserves that scope without saving or requesting', async () => {
    const h = harness({ versioned: true }); await h.flush()
    const groupButtons = () => nodes(h.find(n => n.props['aria-label'] === 'Grupos del ítem')).filter(n => n.type === 'Button' && n.props['aria-expanded'] !== undefined)
    assert.equal(groupButtons().filter(n => n.props['aria-expanded']).length, 1)
    assert.match(content(groupButtons()[0]), /Grupo A.*1 prenda.*Seleccionado/)
    groupButtons()[1].props.onClick(); h.render()
    assert.equal(groupButtons().filter(n => n.props['aria-expanded']).length, 1)
    assert.equal(groupButtons()[1].props['aria-expanded'], true)
    const selectedId = h.find(n => n.type === 'GroupAssignmentEditor').props.group.id
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.group.nombre_grupo, 'Grupo B')
    h.button('Personalización').props.onClick(); h.render()
    assert.equal(nodes(h.render()).some(n => n.type === 'GroupDesignDialog'), false)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.group.id, selectedId)
    assert.equal(nodes(h.render()).filter(n => n.type === 'PreparationProgress').length, 0)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, false)
    assert.equal(h.puts.length + h.posts.length, 0)
})

test('the floating save appears only for pending work and does not reserve a permanent page band', async () => {
    const h = harness({ versioned: true }); await h.flush()
    const dock = () => h.find(n => n.type === 'OrderSaveDock')
    assert.equal(dock().props.active, false)
    assert.equal(h.render().props.style?.paddingBottom, undefined)
    assert.equal(h.hasButton('Guardar'), false)
    assert.equal(nodes(h.render()).some(n => n.props['aria-label'] === 'Continuar con el pedido'), false)
    h.find(n => n.type === 'GroupAssignmentEditor').props.onChange({ nombre_grupo: 'Turno A' }); h.render()
    assert.equal(dock().props.active, true)
    assert.equal(h.render().props.style?.paddingBottom, undefined)
    assert.equal(nodes(h.render()).some(n => n.props['aria-label'] === 'Continuar con el pedido'), false)
    const pending = h.button('Guardar').props.onClick(); h.render()
    assert.equal(dock().props.active, true)
    assert.equal(h.button('Guardar').props.disabled, true)
    assert.equal(h.puts[0].body.enviar, false)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending; await h.flush()
    assert.equal(dock().props.active, false)
    assert.equal(h.hasButton('Guardar'), false)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, false)
    assert.equal(h.posts.length, 0)
})

test('group headers report incomplete design while tracking remains mounted in the same editor', async () => {
    const base = harness({ versioned: true }); await base.flush()
    const configuration = structuredClone(base.find(n => n.type === 'GlobalOrderCurveEditor').props.config)
    configuration.groups.forEach(group => { group.supply_source = 'pte' })
    const preparation = structuredClone(base.preparationData)
    Object.assign(preparation.items[0], { order_version: 2, confirmed: true, design_errors: ['Completa el diseño de Grupo B'], groups: configuration.groups.map(group => ({
        id: group.id, child_key: null, name: group.nombre_grupo, quantity: 1, personalization_required: true, received: false, sent: false, can_send: false,
        blockers: ['Completa el diseño'], design_errors: ['Completa el diseño'],
    })) })
    const h = harness({ versioned: true, preparation, configsById: { 1: { order_configuration: configuration } } }); await h.flush()
    h.find(n => n.type === 'OrderPreparationPanel').props.onSummaryChange(preparation); h.render()
    assert.match(h.content(), /Diseño por completar/)
    assert.equal(h.hasButton('Ir a solicitar prendas'), false)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.active, true)
    assert.equal(h.puts.length + h.posts.length, 0)
})

test('confirming sizes alone does not lock the versioned editor, while a real reservation keeps its protection', async () => {
    const draft = harness({ versioned: true }); await draft.flush()
    const configuration = structuredClone(draft.find(n => n.type === 'GlobalOrderCurveEditor').props.config)
    const preparation = structuredClone(draft.preparationData)
    preparation.items[0].confirmed = true
    const h = harness({ versioned: true, preparation, configsById: { 1: { order_configuration: configuration, has_commitments: true } } }); await h.flush()
    h.find(n => n.type === 'OrderPreparationPanel').props.onSummaryChange(preparation); h.render()
    assert.equal(h.find(n => n.type === 'GlobalOrderCurveEditor').props.disabled, false)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.disabled, false)
    const reserved = structuredClone(preparation)
    reserved.items[0].curve.rows[0].reserved = 1
    h.find(n => n.type === 'OrderPreparationPanel').props.onSummaryChange(reserved); h.render()
    assert.equal(h.find(n => n.type === 'GlobalOrderCurveEditor').props.disabled, true)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.disabled, true)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.designDisabled, false)
    assert.equal(h.posts.length, 0)
    assert.equal(h.puts.length, 0)
})

test('an entirely uncommitted quote adapts every product and every kit child together when loaded and saved', async () => {
    const kit = { kit_version: 1, catalogo: kitCatalog, composiciones: [{ id: 'C1', nombre: 'Equipo', cantidad: 2,
        componentes: [{ sku_generico_id: 11, grupos: [] }, { sku_generico_id: 12, grupos: [] }] }] }
    const h = harness({ versioned: true, extraDetails: [{ id: 3, n_item: 2, producto: 'Kit Cuartel', cantidad: 2 }],
        configsById: { 3: { modo_kit: true, kit, grupos: [] } } }); await h.flush()
    const curve = h.find(n => n.type === 'GlobalOrderCurveEditor')
    assert.equal(curve.props.config.order_version, 2)
    const changed = structuredClone(curve.props.config); changed.curve[0].cantidad = 3
    curve.props.onChange(changed); h.render()
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Camisa · Kit Cuartel · Item #2 · Equipo').props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'GlobalOrderCurveEditor').props.config.groups.length, 0)
    const pending = h.button('Guardar').props.onClick()
    const [simple, kitItem] = h.puts[0].body.items
    assert.equal(simple.grupos.order_version, 2)
    assert.equal(simple.grupos.curve[0].cantidad, 3)
    assert.equal(kitItem.id, 3)
    for (const child of kitItem.kit.composiciones[0].componentes) {
        assert.equal(child.grupos.order_version, 2)
        assert.equal(child.grupos.groups.length, 0)
    }
    assert.equal(h.posts.length, 0)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})

test('historical and uncommitted siblings share the new workspace while preserving legacy payloads', async () => {
    const h = harness({ versioned: true, extraDetails: [{ id: 3, producto: 'Camisa', cantidad: 2 }],
        configsById: { 1: { has_commitments: true }, 3: { grupos: [] } } }); await h.flush()
    assert.equal(h.hasButton('Prendas y solicitudes'), true)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.disabled, true)
    h.find(n => n.type === 'button' && n.props['aria-label'] === 'Personalizar Camisa').props.onClick(); h.render()
    assert.equal(nodes(h.render()).some(n => n.type === 'GlobalOrderCurveEditor'), true)
    h.defineFirstSize(); h.render()
    const pending = h.button('Guardar').props.onClick()
    for (const item of h.puts[0].body.items) assert.equal(Array.isArray(item.grupos), true)
    assert.equal(h.puts[0].body.items[0].grupos[0].tallas[0].cantidad, 1)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})

test('matrix positions include the Excel options and save selection without changing historical instructions or sizes', async () => {
    const h = harness({ matrix: { posicion: 'Frente Izquierdo' } }); await h.flush()
    h.button('Personalización').props.onClick(); h.render()
    const select = h.find(n => n.type === 'Select' && n.props.value === 'Frente Izquierdo')
    const choices = nodes(select).filter(n => n.type === 'SelectItem').map(n => n.props.value)
    for (const position of ['Pecho Izquierdo', 'Pecho Derecho', 'Espalda Superior', 'Espalda Centro',
        'Brazo Izquierdo', 'Brazo Derecho', 'Cuello', 'Frente', 'Izquierdo', 'Derecho', 'Atrás', 'Otro',
        'Espalda', 'Manga Izquierda', 'Manga Derecha', 'Frente Izquierdo']) assert.ok(choices.includes(position), position)
    assert.equal(new Set(choices).size, choices.length)
    assert.equal(h.puts.length, 0)
    select.props.onValueChange('Espalda Superior'); h.render()
    const pending = h.button('Guardar').props.onClick()
    const [a, b] = h.puts[0].body.items[0].grupos
    assert.equal(a.matrices[0].posicion, 'Espalda Superior')
    assert.equal(b.matrices[0].posicion, 'Frente Izquierdo')
    assert.equal(a.matrices[0].valor_fijo, 'BOMBEROS')
    assert.equal(a.tallas[0].cantidad, 1)
    assert.equal(a.tallas[0].color, 'Azul')
    assert.equal(h.puts[0].body.enviar, false)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})

test('text and name colors are independent of garment variants and preserve historical colors', async () => {
    for (const tecnica of ['Bordado', 'Estampado']) {
        for (const tipo_contenido of ['texto_fijo', 'texto_variable']) {
            const h = harness({ matrix: { tecnica, tipo_contenido, color: 'Hilo especial 123' }, configsById: {
                1: { options: { generos: ['Unisex'], colores: ['Azul Marino'], tallas: ['M'] } },
            } }); await h.flush()
            const garment = h.find(n => n.type === 'GroupAssignmentEditor')
            assert.equal(orderConfigurationLib.groupVariantSelection(garment.props.config, garment.props.group).color, 'Azul')
            h.button('Personalización').props.onClick(); h.render()
            const text = h.find(n => n.type === 'Select' && n.props.value === 'Hilo especial 123')
            const choices = nodes(text).filter(n => n.type === 'SelectItem').map(n => n.props.value)
            for (const color of ['Blanco', 'Negro', 'Rojo', 'Dorado', 'Plateado', 'Azul Marino', 'Hilo especial 123']) assert.ok(choices.includes(color))
            assert.equal(new Set(choices).size, choices.length)
            assert.equal(h.puts.length, 0)
            text.props.onValueChange('Blanco'); h.render()
            const pending = h.button('Guardar').props.onClick()
            const [a, b] = h.puts[0].body.items[0].grupos
            assert.equal(a.matrices[0].color, 'Blanco')
            assert.equal(a.matrices[0].tecnica, tecnica)
            assert.equal(a.tallas[0].color, 'Azul')
            assert.equal(b.matrices[0].color, 'Hilo especial 123')
            assert.equal(h.puts[0].body.enviar, false)
            h.puts[0].resolve(saveAck(h.puts[0])); await pending
        }
    }
})

test('image to fixed text clears the image URL and image color for both techniques, preserving other groups', async () => {
    const url = 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/78/Flag_of_Chile.svg/3840px-Flag_of_Chile.svg.png'
    for (const tecnica of ['Bordado', 'Estampado']) {
        const h = harness({ matrix: { tecnica, tipo_contenido: 'logo', valor_fijo: url, color: 'Full Color', comentarios: 'Centrado' } })
        await h.flush()
        h.find(n => n.type === 'Select' && n.props.value === 'logo').props.onValueChange('texto_fijo'); h.render()
        assert.equal(h.find(n => n.type === 'Textarea' && n.props.placeholder?.startsWith('Ej: CUERPO')).props.value, '')
        assert.doesNotMatch(h.content(), /Flag_of_Chile|Vista Previa Texto Fijo/)
        const pending = h.button('Guardar').props.onClick()
        const [a, b] = h.puts[0].body.items[0].grupos
        assert.equal(a.matrices[0].valor_fijo, '')
        assert.equal(a.matrices[0].color, '')
        assert.equal(a.matrices[0].tecnica, tecnica)
        assert.equal(a.matrices[0].posicion, 'Frente')
        assert.equal(a.matrices[0].comentarios, 'Centrado')
        assert.equal(b.matrices[0].valor_fijo, url)
        assert.equal(b.matrices[0].color, 'Full Color')
        h.puts[0].resolve(saveAck(h.puts[0])); await pending
    }
})

test('text to image/list clears incompatible content; selecting the same type keeps the entered text', async () => {
    for (const type of ['logo', 'texto_variable', 'texto_fijo']) {
        const h = harness({ matrix: { color: 'Rojo' } }); await h.flush()
        h.find(n => n.type === 'Select' && n.props.value === 'texto_fijo').props.onValueChange(type); h.render()
        if (type === 'texto_fijo') {
            assert.equal(h.hasButton('Guardar'), false, 'Choosing the existing type leaves the saved order unchanged')
            assert.equal(h.find(n => n.type === 'Textarea' && n.props.placeholder?.startsWith('Ej: CUERPO')).props.value, 'BOMBEROS')
            assert.equal(h.puts.length, 0)
            continue
        }
        const pending = h.button('Guardar').props.onClick()
        const matrix = h.puts[0].body.items[0].grupos[0].matrices[0]
        assert.equal(matrix.tipo_contenido, type)
        assert.equal(matrix.valor_fijo, type === 'texto_fijo' ? 'BOMBEROS' : '')
        assert.equal(matrix.color, type === 'logo' ? 'Full Color' : type === 'texto_fijo' ? 'Rojo' : '')
        h.puts[0].resolve(saveAck(h.puts[0])); await pending
    }
})

test('changing technique preserves text, and long text preview wraps inside its card', async () => {
    const text = 'BOMBEROS'.repeat(50)
    const h = harness({ matrix: { valor_fijo: text, color: 'Rojo' } }); await h.flush()
    h.find(n => n.type === 'Select' && n.props.value === 'Bordado').props.onValueChange('Estampado'); h.render()
    const preview = h.find(n => n.type === 'span' && content(n) === text)
    assert.match(preview.props.className, /w-full/)
    assert.match(preview.props.className, /overflow-wrap:anywhere/)
    const pending = h.button('Guardar').props.onClick()
    const matrix = h.puts[0].body.items[0].grupos[0].matrices[0]
    assert.equal(matrix.tecnica, 'Estampado')
    assert.equal(matrix.valor_fijo, text)
    assert.equal(matrix.color, 'Rojo')
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})

test('choosing kit children nests them in one item and uses the existing personalization editor', async () => {
    const h = harness({ kit: true }); await h.flush()
    assert.match(h.content(), /Productos \(1\)/)
    assert.doesNotMatch(h.content(), /Composición de|Distribuye los kits/)
    h.button('Elegir SKU hijos').props.onClick(); h.render()
    const picker = h.find(n => n.type === 'KitChildrenPicker')
    const composition = { id: 'C1', nombre: 'Combinación 1', cantidad: 10, componentes: [11,12].map(sku_generico_id => ({sku_generico_id, grupos: []})) }
    picker.props.onApply({kit_version:1, catalogo:kitCatalog, composiciones:[composition]}); h.render()
    assert.match(h.content(), /Productos \(1\)/)
    assert.match(h.content(), /Prendas y solicitudesPersonalización/)
    assert.doesNotMatch(h.content(), /3\. Nombres/)
    const parent = h.find(n => n.type === 'section' && n.props['aria-label'] === 'Item #1: Kit Cuartel')
    assert.match(content(parent), /10 kits.*Ítem #1.*Prendas del kit \(2\)/)
    assert.equal(nodes(parent).filter(n => n.type === 'ul').length, 1)
    assert.equal(nodes(parent).filter(n => n.type === 'li').length, 2)
    const childCard = label => h.find(n => n.type === 'button' && n.props['aria-label']?.startsWith('Personalizar '+label))
    assert.equal(childCard('Pantalón').props['aria-pressed'], true)
    childCard('Camisa').props.onClick(); h.render()
    assert.equal(childCard('Camisa').props['aria-pressed'], true)
    h.defineFirstSize(); h.render()
    h.groupQuantity().props.onChange({target:{value:'10'}}); h.render()
    childCard('Pantalón').props.onClick(); h.render()
    childCard('Camisa').props.onClick(); h.render()
    assert.equal(h.groupQuantity().props.value, 10)
    const pending = h.button('Guardar').props.onClick()
    const payload=h.puts[0].body
    assert.equal(payload.items.length,1)
    assert.equal(payload.items[0].id,1)
    assert.equal(payload.items[0].kit.composiciones[0].componentes[1].grupos[0].tallas[0].cantidad,10)
    assert.equal(payload.items[0].kit.composiciones[0].componentes[0].grupos.length,0)
    assert.equal(payload.enviar,false)
    h.puts[0].resolve(saveAck(h.puts[0]));await pending
    assert.equal(h.navigation.length,0)
})

test('existing mixed compositions load as distinct children, preserving quantities and independent drafts', async () => {
    const composiciones = [[6,[11,12]],[3,[11,13]],[1,[11,12,13]]].map(([cantidad,ids],index)=>({
        id:'C'+index,nombre:'Combinación '+index,cantidad, componentes:ids.map(sku_generico_id=>({sku_generico_id,grupos:[{nombre_grupo:'Grupo A',tallas:[],matrices:[{tipo_contenido:'texto_fijo',valor_fijo:'BOMBEROS',tecnica:'Bordado',posicion:'Frente'}],variables:[]}]})),
    }))
    const h=harness({kit:true,savedKit:{kit_version:1,catalogo:kitCatalog,composiciones},extraDetails:[{id:3,producto:'Ítem con solicitud',cantidad:2}],configsById:{1:{has_commitments:false},3:{modo_kit:false,has_commitments:true}}});await h.flush()
    assert.match(h.content(),/Productos \(2\)/)
    const card=h.find(n=>n.type==='button' && n.props['aria-label']==='Personalizar Camisa · Kit Cuartel · Item #1 · Combinación 0')
    card.props.onClick();h.render()
    h.defineFirstSize();h.render()
    h.groupQuantity().props.onChange({target:{value:'6'}});h.render()
    h.button('Crear grupo').props.onClick();h.render()
    assert.match(h.content(),/Grupo 2/)
    h.button('Elegir prendas').props.onClick();h.render()
    const picker=h.find(n=>n.type==='KitChildrenPicker')
    assert.equal(orderConfigurationLib.orderGroups(picker.props.kit.composiciones[0].componentes[1].grupos)[0].tallas[0].cantidad,6)
    picker.props.onApply(picker.props.kit);h.render()
    assert.match(h.content(),/Grupo 2/)
    const pending=h.button('Guardar').props.onClick()
    assert.equal(h.puts[0].body.items[0].kit.composiciones.length,3)
    assert.equal(h.puts[0].body.items[0].kit.composiciones[0].componentes[0].grupos[0].tallas.length,0)
    assert.equal(h.puts[0].body.items[0].kit.composiciones[0].componentes[1].grupos[0].tallas[0].cantidad,6)
    h.puts[0].resolve(saveAck(h.puts[0]));await pending
})

test('canceling child selection leaves the current configuration intact', async () => {
    const h=harness({kit:true});await h.flush()
    h.button('Elegir SKU hijos').props.onClick();h.render()
    h.find(n=>n.type==='KitChildrenPicker').props.onClose();h.render()
    assert.match(h.content(),/Productos \(1\)/)
    assert.equal(h.puts.length,0)
})

test('unconfigured children from an earlier draft open directly in the normal editor', async () => {
    const savedKit = { kit_version: 1, catalogo: kitCatalog, composiciones: [{ id: 'C1', nombre: 'Equipo', cantidad: 10,
        componentes: [{ sku_generico_id: 11, grupos: [] }, { sku_generico_id: 12, grupos: [] }],
    }] }
    const h = harness({ kit: true, savedKit }); await h.flush()
    assert.match(h.content(), /Productos \(1\)/)
    assert.match(h.content(), /Prendas y solicitudesPersonalización/)
    assert.doesNotMatch(h.content(), /3\. Nombres/)
    assert.equal(h.puts.length, 0)
    assert.equal(savedKit.composiciones[0].componentes[0].grupos.length, 0)
})

test('kit child selection is blocked after workshop submission', async () => {
    const h=harness({kit:true,sent:true});await h.flush()
    assert.equal(h.button('Elegir prendas').props.disabled,true)
    h.button('Elegir prendas').props.onClick();h.render()
    assert.equal(h.find(n=>n.type==='fieldset').props.disabled,true)
    assert.doesNotMatch(h.content(),/Guardar/)
    assert.equal(h.puts.length,0)
})

test('two kits with the same name and children remain separate parent items in the sidebar and payload', async () => {
    const plan = quantity => ({ kit_version: 1, catalogo: kitCatalog, composiciones: [{ id: 'C1', nombre: 'Equipo', cantidad: quantity,
        componentes: [11, 12].map(sku_generico_id => ({ sku_generico_id, grupos: [] })),
    }] })
    const h = harness({ kit: true, savedKit: plan(10), extraDetails: [
        { id: 3, n_item: 2, producto: 'Kit Cuartel', cantidad: 4 },
        { id: 4, n_item: 3, producto: 'Polera individual', cantidad: 2 },
    ], configsById: {
        3: { modo_kit: true, kit: plan(4), grupos: [] },
        4: { modo_kit: false, kit: null },
    } }); await h.flush()
    assert.match(h.content(), /Productos \(3\)/)
    for (const number of [1, 2]) {
        const parent = h.find(n => n.type === 'section' && n.props['aria-label'] === `Item #${number}: Kit Cuartel`)
        const list = nodes(parent).find(n => n.type === 'ul')
        assert.equal(nodes(list).filter(n => n.type === 'li').length, 2)
        assert.doesNotMatch(content(list), /Item #|Polera individual/)
        assert.ok(nodes(parent).some(n => n.type === 'Button' && n.props['aria-label'] === `Elegir SKU hijos de Kit Cuartel · Item #${number}`))
    }
    const card = number => h.find(n => n.type === 'button' && n.props['aria-label'] === `Personalizar Camisa · Kit Cuartel · Item #${number} · Equipo`)
    card(1).props.onClick(); h.render()
    h.defineFirstSize(); h.render()
    h.groupQuantity().props.onChange({ target: { value: '10' } }); h.render()
    card(2).props.onClick(); h.render()
    h.defineFirstSize(); h.render()
    h.groupQuantity().props.onChange({ target: { value: '4' } }); h.render()
    card(1).props.onClick(); h.render()
    assert.equal(h.groupQuantity().props.value, 10)
    const pending = h.button('Guardar').props.onClick()
    const items = h.puts[0].body.items
    assert.deepEqual(Array.from(items, item => item.id), [1, 3, 4])
    for (const [id, quantity] of [[1, 10], [3, 4]]) assert.equal(items.find(item => item.id === id).kit.composiciones[0].componentes[1].grupos[0].tallas[0].cantidad, quantity)
    assert.equal(items.find(item => item.id === 4).kit, undefined)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})

test('a product without customization can configure its empty physical curve without activating embroidery', async () => {
    const h = harness({ configsById: { 1: { permite_personalizar: false, grupos: [], tipo_personalizacion: 'none' } } }); await h.flush()
    assert.match(h.content(), /Grupos de prendas/)
    assert.doesNotMatch(h.content(), /Activar Personalización/)
    h.defineFirstSize(); h.render()
    assert.match(h.content(), /Cambios sin guardar/)
    const pending = h.button('Guardar').props.onClick()
    assert.equal(h.puts[0].body.items[0].grupos[0].tallas.length, 1)
    assert.equal(h.puts[0].body.items[0].grupos[0].matrices.length, 0)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})

test('a malformed successful save never discards dirty state or reports confirmation', async () => {
    const h = harness(); await h.flush()
    h.defineFirstSize(); h.render()
    const pending = h.button('Guardar').props.onClick()
    h.puts[0].resolve({ message: 'proxy response' }); await pending; h.render()
    assert.match(h.content(), /Cambios sin guardar/)
    assert.match(h.content(), /No pudimos confirmar el guardado/)
    assert.equal(h.navigation.length, 0)
})

test('normal saves submit the loaded persisted version and adopt only the acknowledged normalized version', async () => {
    const h = harness(); await h.flush()
    const edit = value => { h.groupQuantity().props.onChange({ target: { value } }); h.render() }
    edit('2')
    let action = h.button('Guardar').props.onClick()
    assert.equal(h.puts[0].body.items[0].base_hash, 'a'.repeat(64))
    h.puts[0].resolve(saveAck(h.puts[0])); await action; await h.flush()
    edit('1')
    action = h.button('Guardar').props.onClick()
    assert.equal(h.puts[1].body.items[0].base_hash, 'b'.repeat(64))
    h.puts[1].resolve(saveAck(h.puts[1])); await action; await h.flush()
    assert.equal(h.hasButton('Guardar'), false)
})

test('a concurrent draft conflict keeps local edits, blocks repeated writes and explicitly offers server reload', async () => {
    const h = harness(); await h.flush()
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    const action = h.button('Guardar').props.onClick()
    h.puts[0].reject({ status: 409, data: { code: 'draft_conflict', message: 'El pedido cambió desde que lo abriste.' } })
    await action; await h.flush()
    assert.equal(h.groupQuantity().props.value, 2)
    assert.match(h.content(), /descarta tu borrador local de todos los ítems/)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, true)
    h.find(n => n.type === 'AlertDialogAction' && content(n) === 'Seguir editando').props.onClick(); h.render()
    await h.button('Revisar conflicto').props.onClick(); h.render()
    assert.equal(h.puts.length, 1)
    await h.button('Cargar versión guardada').props.onClick(); await h.flush()
    assert.equal(h.groupQuantity().props.value, 1)
    assert.equal(h.hasButton('Guardar'), false)
    assert.equal(h.puts.length, 1, 'Reload never replaces server data')
})

test('refreshing stock after preparation changes preserves every unsaved edit and the original concurrency base', async () => {
    let refreshes = 0
    const stockState = { status: 'ready', optionsByItem: { 1: { variantes: [{ talla: 'M', color: 'Azul', genero: 'Unisex', available: 3 }] } }, kitCatalogByItem: {}, refresh: async () => { refreshes++; return true } }
    const h = harness({ versioned: true, stockState }); await h.flush()
    const editor = () => h.find(n => n.type === 'GroupAssignmentEditor')
    editor().props.onChange({ nombre_grupo: 'Mi borrador' }); h.render()
    h.find(n => n.type === 'OrderPreparationPanel').props.onSummaryChange(h.preparationData); h.render()
    assert.equal(refreshes, 1)
    assert.equal(editor().props.group.nombre_grupo, 'Mi borrador')
    assert.equal(h.find(n => n.type === 'GlobalOrderCurveEditor').props.stockVariants[0].available, 3)
    h.find(n => n.type === 'GlobalOrderCurveEditor').props.onRefreshStock()
    assert.equal(refreshes, 2)
    const action = h.button('Guardar').props.onClick()
    assert.equal(h.puts[0].body.items[0].base_hash, 'a'.repeat(64))
    h.puts[0].resolve(saveAck(h.puts[0])); await action; await h.flush()
})

test('an editor response without its persisted version cannot enable unsafe saving', async () => {
    const h = harness({ configsById: { 1: { configuration_hash: undefined } } }); await h.flush()
    assert.match(h.content(), /No pudimos cargar todos los productos/)
    assert.equal(h.hasButton('Guardar'), false)
    assert.equal(h.puts.length, 0)
})

test('discarding a conflicted local draft still requires fresh server data before issuing operations', async () => {
    const h = harness(); await h.flush()
    h.groupQuantity().props.onChange({ target: { value: '2' } }); h.render()
    const action = h.button('Guardar').props.onClick()
    h.puts[0].reject({ status: 409, data: { code: 'draft_conflict', message: 'El pedido cambió.' } })
    await action; await h.flush()
    h.find(n => n.type === 'AlertDialogAction' && content(n) === 'Seguir editando').props.onClick(); h.render()
    h.button('Descartar cambios').props.onClick(); h.render()
    h.button('Descartar borrador').props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.dirty, false)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.disabled, true)
    assert.match(h.content(), /Pedido cambiado en otra sesión/)
    h.button('Prendas y solicitudes').props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.active, true)
    assert.equal(h.find(n => n.type === 'OrderPreparationPanel').props.disabled, true)
    assert.equal(h.posts.length, 0)
})

test('a group dispatch summary keeps the original draft base so the pending group can be saved without reloading', async () => {
    const h = harness({ versioned: true }); await h.flush()
    const loaded = h.find(n => n.type === 'GlobalOrderCurveEditor').props.config
    const summary = structuredClone(h.preparationData)
    Object.assign(summary.items[0], { order_version: 2, confirmed: true, groups: loaded.groups.map((group, index) => ({
        id: group.id, child_key: null, name: group.nombre_grupo, quantity: 1, personalization_required: true,
        sent: index === 0, received: index === 0, can_send: false, blockers: [], design_hash: String(index).repeat(64),
    })) })
    // This is the callback invoked by OrderPreparationPanel after its verified dispatch response.
    h.find(n => n.type === 'OrderPreparationPanel').props.onSummaryChange(summary); h.render()
    let editor = h.find(n => n.type === 'GroupAssignmentEditor')
    assert.equal(editor.props.designDisabled, true, 'A is now immutable in the editor')
    const buttons = nodes(h.find(n => n.props['aria-label'] === 'Grupos del ítem')).filter(n => n.type === 'Button' && n.props['aria-expanded'] !== undefined)
    buttons[1].props.onClick(); h.render()
    editor = h.find(n => n.type === 'GroupAssignmentEditor')
    assert.equal(editor.props.designDisabled, false, 'B remains editable while it waits')
    editor.props.onChange({ nombre_grupo: 'B pendiente editado después de enviar A' }); h.render()
    const reads = h.gets.filter(url => url === '/api/cotizacion-items/1/personalizacion').length
    const action = h.button('Guardar').props.onClick()
    assert.equal(h.puts[0].body.items[0].base_hash, 'a'.repeat(64))
    assert.equal(h.puts[0].body.items[0].grupos.groups[1].nombre_grupo, 'B pendiente editado después de enviar A')
    assert.equal(h.puts[0].body.items[0].grupos.groups[0].nombre_grupo, loaded.groups[0].nombre_grupo)
    h.puts[0].resolve(saveAck(h.puts[0])); await action; await h.flush()
    assert.equal(h.gets.filter(url => url === '/api/cotizacion-items/1/personalizacion').length, reads, 'No reload or draft discard is required')
    assert.doesNotMatch(h.content(), /Pedido cambiado en otra sesión/)
    assert.equal(h.hasButton('Guardar'), false)
})

test('garment preparation reports the selected groups configuration independently of delivery', async () => {
    const h = harness({ versioned: true }); await h.flush()
    const buttons = () => nodes(h.find(n => n.props['aria-label'] === 'Grupos del ítem')).filter(n => n.type === 'Button' && n.props['aria-expanded'] !== undefined)
    const selected = () => content(buttons().find(n => n.props['aria-expanded']))
    assert.match(selected(), /Elige Bodega o Encargo/)
    h.find(n => n.type === 'GroupAssignmentEditor').props.onChange({ supply_source: 'pte' }); h.render()
    assert.match(selected(), /configurados.*solicitud y entrega.*seguimiento/)
    assert.equal(nodes(h.render()).some(n => n.type === 'GroupDesignCopy'), false)
    h.button('Personalización').props.onClick(); h.render()
    assert.equal(nodes(h.render()).some(n => n.type === 'GroupDesignCopy'), true)
    h.button('Prendas y solicitudes').props.onClick(); h.render()
    buttons()[1].props.onClick(); h.render()
    assert.match(selected(), /Elige Bodega o Encargo/)
    h.find(n => n.type === 'GroupAssignmentEditor').props.onChange({ supply_source: 'external', assignments: [] }); h.render()
    assert.match(selected(), /Asigna prendas/)
    assert.equal(h.puts.length + h.posts.length, 0)
})

test('committing A locks only its physical fields; B and the unassigned curve remain editable', async () => {
    const seed = harness({ versioned: true }); await seed.flush()
    const configuration = structuredClone(seed.find(n => n.type === 'GlobalOrderCurveEditor').props.config)
    const preparation = structuredClone(seed.preparationData)
    const physical = preparation.items[0].curve
    preparation.items[0].groups = configuration.groups.map((group, index) => ({ id: group.id, child_key: null, name: group.nombre_grupo, product: 'Polera', quantity: 1,
        supply_source: group.supply_source ?? 'pte', personalization_required: true, design_hash: String(index ? 'e' : 'd').repeat(64), received: false, sent: false, can_send: false,
        work_id: null, sent_at: null, blockers: ['Esperar entrega completa de Bodega'],
        supply: { curve: { hash: 'a'.repeat(64), errors: [], rows: [] }, plan_hash: 'f'.repeat(64), confirmed: index === 0, preparation_id: index === 0 ? 1 : null,
            revision: index === 0 ? 1 : null, requested: index === 0, can_request: false, blockers: [], legacy: false } }))
    preparation.group_flow = true; preparation.items[0].order_version = 2
    const h = harness({ versioned: true, preparation, configsById: { 1: { order_configuration: configuration, has_commitments: true } } }); await h.flush()
    h.find(n => n.type === 'OrderPreparationPanel').props.onSummaryChange(preparation); h.render()
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.disabled, true)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.designDisabled, false)
    assert.equal(h.find(n => n.type === 'GlobalOrderCurveEditor').props.disabled, false)
    nodes(h.find(n => n.props['aria-label'] === 'Grupos del ítem')).filter(n => n.type === 'Button' && n.props['aria-expanded'] !== undefined)[1].props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.disabled, false)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.designDisabled, false)
    assert.equal(h.puts.length + h.posts.length, 0)
})


test('lost save response recovers the frozen attempt and keeps later edits pending', async () => {
    const h = harness(); await h.flush()
    const quantity = () => h.groupQuantity()
    quantity().props.onChange({target:{value:'2'}}); h.render()
    let action = h.button('Guardar').props.onClick()
    h.puts[0].reject(new TypeError('response lost after commit')); await action; await h.flush()
    quantity().props.onChange({target:{value:'3'}}); h.render()
    action = h.button('Guardar').props.onClick()
    assert.equal(h.puts[1].body.recover_uncertain, true)
    assert.equal(h.puts[1].body.items[0].context_hash, 'e'.repeat(64))
    assert.equal(JSON.stringify(h.puts[1].body.items), JSON.stringify(h.puts[0].body.items))
    h.puts[1].resolve(saveAck(h.puts[1])); await action; await h.flush()
    assert.equal(quantity().props.value, 3)
    assert.match(h.content(), /Cambios sin guardar/)
    action = h.button('Guardar').props.onClick()
    assert.equal(h.puts[2].body.recover_uncertain, undefined)
    assert.equal(h.puts[2].body.items[0].base_hash, 'b'.repeat(64))
    assert.equal(h.puts[2].body.items[0].grupos[0].tallas[0].cantidad, 3)
    h.puts[2].resolve(saveAck(h.puts[2])); await action; await h.flush()
    assert.equal(h.hasButton('Guardar'), false)
})

test('uncertain recovery never turns a genuine concurrent conflict into a fresh base', async () => {
    const h = harness(); await h.flush()
    h.groupQuantity().props.onChange({target:{value:'2'}}); h.render()
    let action = h.button('Guardar').props.onClick()
    h.puts[0].reject(new TypeError('lost')); await action; await h.flush()
    action = h.button('Guardar').props.onClick()
    h.puts[1].reject({status:409,data:{code:'draft_conflict',message:'Otra sesión cambió el pedido'}}); await action; await h.flush()
    assert.match(h.content(), /Pedido cambiado en otra sesión/)
    assert.equal(h.groupQuantity().props.value, 2)
})

test('initial dispatch metadata protects A during summary failure while B remains editable', async () => {
    const seed = harness({versioned:true}); await seed.flush()
    const configuration = structuredClone(seed.find(n => n.type === 'GlobalOrderCurveEditor').props.config)
    const a = configuration.groups[0], b = configuration.groups[1]
    const h = harness({versioned:true,configsById:{1:{grupos:configuration,order_configuration:configuration,has_commitments:true,group_dispatches:{['individual|'+a.id]:{kind:'workshop'}}}}}); await h.flush()
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.designDisabled, true)
    h.find(n => n.type === 'Button' && n.props['aria-controls'] === undefined && content(n).includes(b.nombre_grupo) && n.props['aria-expanded'] === false).props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.designDisabled, false)
    h.find(n => n.type === 'GroupAssignmentEditor').props.onChange({nombre_grupo:'B conservado'});h.render()
    h.failSummary(true); h.find(n => n.type === 'OrderPreparationPanel').props.onSummaryChange(null);h.render()
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.designDisabled, false)
    assert.match(h.content(), /B conservado/)
})

test('unknown emission state is provisionally protected and recovery only unlocks unsent groups', async () => {
    const seed = harness({versioned:true});await seed.flush()
    const configuration=structuredClone(seed.find(n=>n.type==='GlobalOrderCurveEditor').props.config)
    const h=harness({versioned:true,configsById:{1:{order_configuration:configuration,has_commitments:true}}});await h.flush()
    assert.equal(h.find(n=>n.type==='GroupAssignmentEditor').props.designDisabled,true)
    assert.match(h.content(), /comprobando qué diseños/)
    const summary=structuredClone(h.preparationData)
    summary.items[0].groups=configuration.groups.map((group,index)=>({id:group.id,child_key:null,sent:index===0}))
    h.find(n=>n.type==='OrderPreparationPanel').props.onSummaryChange(summary);h.render()
    assert.equal(h.find(n=>n.type==='GroupAssignmentEditor').props.designDisabled,true)
    h.find(n=>n.type==='Button' && n.props['aria-expanded']===false && content(n).includes(configuration.groups[1].nombre_grupo)).props.onClick();h.render()
    assert.equal(h.find(n=>n.type==='GroupAssignmentEditor').props.designDisabled,false)
})

test('comments labels target unique controls across positions, groups and kit children', async()=>{
    const h=harness();await h.flush()
    const label=h.find(n=>n.type==='label' && content(n)==='Comentarios (opcional)')
    assert.ok(label.props.htmlFor)
    assert.equal(h.find(n=>n.type==='Textarea' && n.props.id===label.props.htmlFor).props.value,'')
    h.groupButton('Grupo B').props.onClick();h.render()
    const other=h.find(n=>n.type==='label' && content(n)==='Comentarios (opcional)')
    assert.notEqual(other.props.htmlFor,label.props.htmlFor)
})

test('legacy no-art groups require an explicit saved choice and preserve that choice per group', async () => {
    const groups = ['Guardia A', 'Guardia B'].map(nombre_grupo => ({
        nombre_grupo, tallas: [{ talla: 'M', genero: 'Unisex', color: 'Azul', cantidad: 1 }], matrices: [], variables: [],
    }))
    const h = harness({ configsById: { 1: { grupos: groups } } }); await h.flush()
    h.find(n=>n.type==='GroupAssignmentEditor').props.onChange({personalization_required:false,personalization_confirmed:true});h.render()
    assert.equal(h.find(n=>n.type==='GroupAssignmentEditor').props.group.personalization_confirmed,true)
    assert.equal(h.puts.length, 0)
    const pending = h.button('Guardar').props.onClick()
    const saved = h.puts[0].body.items[0].grupos
    assert.equal(saved[0].personalization_required, false)
    assert.equal(saved[0].personalization_confirmed, true)
    assert.equal(saved[1].personalization_confirmed, undefined)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})

test('removing legacy artwork records an explicit no-personalization choice without saving automatically', async () => {
    const h = harness(); await h.flush()
    h.find(n=>n.type==='GroupAssignmentEditor').props.onChange({personalization_required:false,personalization_confirmed:true,matrices:[],variables:[],archivo_adjunto:null});h.render()
    assert.equal(h.puts.length, 0)
    const pending = h.button('Guardar').props.onClick()
    const groups = h.puts[0].body.items[0].grupos
    assert.equal(groups[0].personalization_required,false)
    assert.equal(groups[0].personalization_confirmed,true)
    assert.equal(groups[0].matrices.length,0)
    assert.equal(groups[1].matrices.length,1)
    h.puts[0].resolve(saveAck(h.puts[0])); await pending
})


test('a committed empty historical item uses the new tabs and never upgrades storage or issues a request on load', async () => {
    const h = harness({ configsById: { 1: { grupos: [], has_commitments: true, group_dispatches: {} } } }); await h.flush()
    assert.equal(h.hasButton('Prendas y solicitudes'), true)
    assert.equal(h.hasButton('Personalización'), true)
    assert.equal(h.hasButton('Prendas y pedidos'), false)
    assert.doesNotMatch(h.content(), /1\. Tallas|2\. Matriz Visual/)
    const curve = h.find(n => n.type === 'GlobalOrderCurveEditor')
    assert.equal(curve.props.config.curve.length, 0)
    assert.equal(curve.props.disabled, true)
    h.button('Personalización').props.onClick(); h.render()
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.historical, true)
    assert.equal(h.find(n => n.type === 'GroupAssignmentEditor').props.designDisabled, false)
    assert.equal(h.posts.length + h.puts.length, 0)
})

test('loading and failed loads never flash the historical navigation', async () => {
    const h = harness({ failLoad: true })
    assert.equal(h.hasButton('Prendas y pedidos'), false)
    await h.flush()
    assert.equal(h.hasButton('Prendas y pedidos'), false)
    assert.doesNotMatch(h.content(), /1\. Tallas|2\. Matriz Visual/)
    assert.equal(h.puts.length + h.posts.length, 0)
})
