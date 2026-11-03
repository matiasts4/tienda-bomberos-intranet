<?php
// Eloquent y Validator reales, sin iniciar Laravel ni permitir conexiones SQL.
require __DIR__.'/../../vendor/autoload.php';
\Illuminate\Support\Facades\Facade::setFacadeApplication(new \Illuminate\Container\Container());
\Illuminate\Support\Facades\Validator::swap(new \Illuminate\Validation\Factory(new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es')));
\Illuminate\Database\Eloquent\Model::setConnectionResolver(new class implements \Illuminate\Database\ConnectionResolverInterface {
    public function connection($name = null) { throw new RuntimeException('Esta prueba prohíbe conexiones a BD.'); }
    public function getDefaultConnection() { return 'forbidden'; }
    public function setDefaultConnection($name) {}
});
use App\Models\{DetalleCotizacion, PteSkuGenerico, Personalizacion, Cotizacion, Estado};
use App\Services\{KitPlan, KitPersonalization, PersonalizationWorkflow};
use Illuminate\Validation\ValidationException;
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function rejected($fn, $message) { try { $fn(); } catch (ValidationException) { return; } throw new RuntimeException($message); }
$catalog = [];
foreach ([['Pantalón', 'obligatorio', 1], ['Camisa', 'opcional', 1], ['Polerón', 'opcional', 1]] as $i => [$name, $rule, $factor]) $catalog[] = ['sku_generico_id' => $i + 1, 'nombre' => $name, 'sku_base' => '1111000000'.($i + 1).'0000', 'regla' => $rule, 'grupo_alternativa' => '', 'cantidad_por_kit' => $factor, 'permite_personalizar' => true, 'disponible' => true, 'variantes' => [['sku' => '1111000000'.($i + 1).'0101', 'talla' => 'M', 'color' => 'Azul', 'genero' => 'Unisex']]];
$groups = fn ($quantity, $personalized = false) => [['nombre_grupo' => 'A', 'tallas' => [['talla' => 'M', 'color' => 'Azul', 'genero' => 'Unisex', 'cantidad' => $quantity]], 'matrices' => $personalized ? [['tecnica' => 'Bordado', 'posicion' => 'Frente', 'tipo_contenido' => 'texto_fijo', 'valor_fijo' => 'BOMBEROS']] : [], 'variables' => []]];
$compositions = [];
foreach ([[6, [1,2]], [3, [1,3]], [1, [1,2,3]]] as $i => [$quantity, $ids]) $compositions[] = ['id' => 'c'.$i, 'nombre' => 'Composición '.$i, 'cantidad' => $quantity, 'componentes' => array_map(fn ($id) => ['sku_generico_id' => $id, 'grupos' => $groups($quantity, $id === 2)], $ids)];
$plan = KitPlan::normalize($catalog, $compositions, 10, true);
$physical = [];
foreach (KitPlan::children($plan) as $child) $physical[$child['nombre']] = ($physical[$child['nombre']] ?? 0) + $child['cantidad'];
check($physical === ['Pantalón' => 10, 'Camisa' => 7, 'Polerón' => 4], '10 kits deben producir 10/7/4 prendas');
$parent = new DetalleCotizacion(['cantidad' => 10, 'producto' => 'Kit Cuartel', 'sku' => '111100000000000', 'configuracion' => $plan, 'tipo_personalizacion' => 'kit']);
$parent->forceFill(['id' => 42, 'n_item' => 1]);
$parent->setRelation('personalizationGeneric', new PteSkuGenerico(['modo_kit' => true, 'es_informativo_cotizacion' => false]));
check(KitPersonalization::commercialDetails([['n_item' => 1, 'sku' => $parent->sku, 'cantidad' => 10, 'producto' => 'Nombre comercial editado']], collect([$parent]))[1] === $parent, 'Guardar comercial conserva identidad y snapshot');
rejected(fn () => KitPersonalization::commercialDetails([['n_item' => 1, 'sku' => $parent->sku, 'cantidad' => 9]], collect([$parent])), 'No invalidar composición cambiando cantidad comercial');
rejected(fn () => KitPersonalization::normalize($parent, ['composiciones' => 'malformed'], false), 'Composiciones debe ser lista');
rejected(fn () => KitPlan::normalize($catalog, ['malformed'], 10, false), 'Composición no objeto');
rejected(fn () => KitPlan::normalize($catalog, [['id' => 'X', 'cantidad' => 1, 'componentes' => ['malformed']]], 10, false), 'Componente no objeto');
$normalized = KitPersonalization::normalize($parent, ['composiciones' => $compositions, 'catalogo' => [['nombre' => 'NoAutorizado']]], true);
check($normalized === $plan, 'El cliente no puede sustituir la definición histórica');
$children = KitPersonalization::operationalItems($parent);
check(count($children) === 7 && $parent->cantidad === 10 && $parent->producto === 'Kit Cuartel', 'Padre comercial intacto; hijos independientes');
check(count(KitPersonalization::manifest($parent)) === 7, 'Manifiesto de variantes físicas');
rejected(fn () => KitPlan::normalize($catalog, $compositions, 9, true), 'Sobreasignación debe fallar');
rejected(fn () => KitPlan::normalize($catalog, $compositions, 11, true), 'Subasignación debe fallar');
check(count(KitPlan::normalize($catalog, [$compositions[0]], 10, false)['composiciones']) === 1, 'Borrador parcial permitido');
$bad = $compositions; $bad[0]['componentes'] = [$bad[0]['componentes'][1]];
rejected(fn () => KitPlan::normalize($catalog, $bad, 10, true), 'Obligatorio omitido');
$bad = $compositions; $bad[0]['componentes'][] = $bad[0]['componentes'][0];
rejected(fn () => KitPlan::normalize($catalog, $bad, 10, true), 'Duplicado omitido');
$bad = $compositions; $bad[0]['componentes'][0]['sku_generico_id'] = 99;
rejected(fn () => KitPlan::normalize($catalog, $bad, 10, true), 'SKU ajeno omitido');
$bad = $compositions; $bad[0]['componentes'][0]['grupos'] = $groups(5);
rejected(fn () => KitPersonalization::normalize($parent, ['composiciones' => $bad], true), 'Entrega directa también debe cuadrar tallas');
$bad = $compositions; $bad[0]['componentes'][0]['grupos'][0]['tallas'][0]['talla'] = 'XL';
rejected(fn () => KitPersonalization::normalize($parent, ['composiciones' => $bad], true), 'Variante inexistente omitida');
$alternative = $catalog; $alternative[1]['regla'] = $alternative[2]['regla'] = 'alternativa'; $alternative[1]['grupo_alternativa'] = $alternative[2]['grupo_alternativa'] = 'Superior';
rejected(fn () => KitPlan::normalize($alternative, $compositions, 10, true), 'Dos alternativas simultáneas');
check(count(KitPlan::normalize($alternative, array_slice($compositions, 0, 2), 9, true)['composiciones']) === 2, 'Una alternativa por composición permite camisas y polerones en el mismo pedido');
rejected(fn () => KitPlan::normalize($alternative, [['id' => 'EmptyAlternative', 'cantidad' => 10, 'componentes' => [['sku_generico_id' => 1, 'grupos' => $groups(10)]]]], 10, true), 'Cada grupo requiere una alternativa');
check(count(KitPlan::normalize($catalog, [['id' => 'SoloPantalon', 'cantidad' => 10, 'componentes' => [['sku_generico_id' => 1, 'grupos' => $groups(10)]]]], 10, true)['composiciones'][0]['componentes']) === 1, 'Opcionales pueden omitirse todos');
$historic = new DetalleCotizacion(['sku' => $parent->sku, 'configuracion' => $groups(10)]);
$historic->setRelation('personalizationGeneric', new PteSkuGenerico(['modo_kit' => true]));
check(!KitPersonalization::isKit($historic), 'Producto individual configurado anteriormente conserva su flujo');
$parent->setRelation('personalizationGeneric', new PteSkuGenerico(['modo_kit' => false, 'es_informativo_cotizacion' => false]));
check(KitPersonalization::isKit($parent), 'Snapshot permanece kit aunque el catálogo desactive el modo');
$double = $catalog; $double[1]['cantidad_por_kit'] = 2;
check(KitPlan::children(KitPlan::normalize($double, [$compositions[0]], 6, true))[1]['cantidad'] === 12, 'Dos camisas por kit');
$quote = new Cotizacion(['personalizacion_completada' => true, 'estado_personalizacion' => 'sent_to_personalization']);
$quote->setRelation('estado', new Estado(['nombre' => 'Ventas']))->setRelation('detalles', collect([$parent]));
$jobs = [];
foreach ($children as $child) if (App\Services\QuotePersonalization::validateConfig($child, $child->configuracion, false) !== 'none') $jobs[] = new Personalizacion(['cantidad' => $child->cantidad, 'estado' => 'completado', 'configuracion' => ['kit_origen' => ['item_id' => 42, 'key' => $child->kit_key]], 'bloqueado_qc' => false]);
$quote->setRelation('personalizaciones', collect($jobs));
check(PersonalizationWorkflow::dispatchBlocker($quote) === null, 'Despacho conciliado por hijo');
$jobs[0]->cantidad = 5;
check(PersonalizationWorkflow::dispatchBlocker($quote) !== null, 'Despacho bloquea hijo incompleto aunque otros sumen suficiente');
$jobs[0]->cantidad = 6; $jobs[0]->estado = 'taller_en_proceso';
check(PersonalizationWorkflow::dispatchBlocker($quote) !== null, 'Despacho espera todos los talleres');
$editableJob = new class extends Personalizacion {
    public function save(array $options = []) { return true; } // Persistencia simulada: SQL sigue prohibido.
};
$origin = ['item_id' => 42, 'key' => 'c0:2', 'kit' => 'Kit Cuartel'];
$editableJob->forceFill(['tipo' => 'bordado', 'estado' => 'pendiente_diseno', 'cantidad' => 6,
    'configuracion' => ['grupos' => $groups(6, true), 'kit_origen' => $origin]]);
PersonalizationWorkflow::transition($editableJob, ['configuracion' => ['workshop_matrix_sent' => true, 'kit_origen' => ['item_id' => 999]]]);
check($editableJob->configuracion['kit_origen'] === $origin && $editableJob->configuracion['grupos'] === $groups(6, true), 'Guardar diseño/taller mantiene tallas y origen del kit; ignora reasignación del cliente');
echo "OK: kits 10/7/4, obligatorios/opcionales/alternativas, factores, cantidades, variantes, snapshots y despacho. SQL prohibido.\n";
