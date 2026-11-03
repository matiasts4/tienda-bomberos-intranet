<?php
namespace Tests\Feature\Comercial;

use App\Models\{Cotizacion, CotizacionArchivo, Estado, Evento, User, Contacto, Oportunidad, OportunidadGestion, ProspeccionContacto};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgendaSaleTest extends TestCase
{
    private function quote(User $user, string $estado, array $attrs = []): Cotizacion
    {
        $e = Estado::firstOrCreate(['scope' => 'cotizacion', 'nombre' => $estado], ['orden' => 1]);
        return Cotizacion::factory()->forUser($user)->withEstado($e)->create(array_merge([
            'fecha_vencimiento' => '2026-10-10', 'fecha_venta' => '2026-10-06', 'plazo_produccion' => '20 dias',
        ], $attrs));
    }
    private function agenda(string $start = '2026-10-01', string $end = '2026-11-01'): array
    {
        return $this->getJson('/api/eventos?' . http_build_query(compact('start', 'end')))->assertOk()->json();
    }
    public function test_sale_replaces_red_event_with_orange_delivery(): void
    {
        $this->travelTo('2026-10-06');
        $user = $this->actingAsEjecutivo();
        $q = $this->quote($user, 'Cotización Enviada');
        $this->assertSame('quote-'.$q->id, $this->agenda()[0]['id']);
        CotizacionArchivo::factory()->tipo('orden_venta')->create(['cotizacion_id' => $q->id]);
        $ventas = Estado::factory()->cotizacion('Ventas')->create();
        $this->patchJson('/api/cotizaciones/'.$q->id.'/estado', ['estado_id' => $ventas->id])->assertOk();
        $events = $this->agenda();
        $this->assertCount(1, $events);
        $this->assertSame('sale-'.$q->id, $events[0]['id']);
        $this->assertSame('#fb923c', $events[0]['backgroundColor']);
        $this->assertSame('entrega_venta', $events[0]['extendedProps']['tipo']);
        $this->assertSame('2026-10-26', $events[0]['extendedProps']['fecha_entrega']);
        $this->assertSame('2026-10-10', $q->fresh()->fecha_vencimiento->toDateString());
    }
    public function test_delivery_range_uses_delivery_not_old_expiry_and_end_is_exclusive(): void
    {
        $q = $this->quote($this->actingAsEjecutivo(), 'Ventas');
        $this->assertCount(0, $this->agenda('2026-10-01', '2026-10-26'));
        $this->assertCount(1, $this->agenda('2026-10-26', '2026-10-27'));
    }
    public function test_weekdays_stock_and_pending_have_no_invented_deadline(): void
    {
        $user = $this->actingAsEjecutivo();
        $this->quote($user, 'Ventas', ['plazo_produccion' => '15 dias habiles']);
        $this->quote($user, 'Aprobada por Cliente', ['plazo_produccion' => 'Stock Disponible']);
        $pending = $this->quote($user, 'Ventas', ['fecha_venta' => null]);
        $unreadable = $this->quote($user, 'Ventas', ['plazo_produccion' => 'a convenir']);
        $events = collect($this->agenda())->keyBy('extendedProps.cotizacion_id');
        $this->assertSame(['2026-10-27', '2026-10-06', null, null], $events->pluck('extendedProps.fecha_entrega')->all());
        foreach ([$pending, $unreadable] as $q) {
            $this->assertNull($events[$q->id]['start']);
            $this->assertTrue($events[$q->id]['extendedProps']['entrega_pendiente']);
        }
    }
    public function test_cancelled_or_rejected_sales_are_absent(): void
    {
        $user = $this->actingAsEjecutivo();
        $this->quote($user, 'Anulado');
        $this->quote($user, 'Rechazado');
        $this->assertSame([], $this->agenda());
    }
    public function test_sales_and_payments_are_private_for_executive(): void
    {
        $other = User::factory()->create(['role' => 'ejecutivo']);
        $own = $this->quote($this->actingAsEjecutivo(), 'Ventas');
        $foreign = $this->quote($other, 'Ventas');
        foreach ([$own, $foreign] as $q) DB::table('pagos')->insert(['cotizacion_id' => $q->id, 'fecha_pago' => '2026-10-20']);
        $this->assertCount(2, $this->agenda());
        $this->actingAsUser(['role' => 'superadmin']);
        $this->assertCount(4, $this->agenda());
    }
    public function test_mail_uses_sale_delivery_and_excludes_previous_expiry(): void
    {
        $this->travelTo('2026-10-23');
        DB::table('alerta_configs')->insert(['clave' => 'alerta_vencimientos', 'valor' => 'true']);
        $user = User::factory()->create(['email' => 'sale@example.test']);
        $sale = $this->quote($user, 'Ventas');
        $this->quote($user, 'Ventas', ['fecha_vencimiento' => '2026-10-26', 'fecha_venta' => '2026-09-01']);
        $this->artisan('alerts:vencimientos')->assertSuccessful();
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $html = $messages->first()->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('Entrega comprometida #'.$sale->numero, $html);
        $this->assertStringNotContainsString('Vence CT', $html);
    }
    public function test_payment_reminder_formats_date_string(): void
    {
        $this->travelTo('2026-10-17');
        DB::table('alerta_configs')->insert(['clave' => 'alerta_vencimientos', 'valor' => 'true']);
        $user = User::factory()->create(['email' => 'pay@example.test']);
        $quote = $this->quote($user, 'Ventas');
        DB::table('pagos')->insert(['cotizacion_id' => $quote->id, 'fecha_pago' => '2026-10-20', 'factura' => 'FACT-TEST', 'monto' => 100]);
        $this->artisan('alerts:vencimientos')->assertSuccessful();
        $html = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('FACT-TEST', $html);
        $this->assertStringContainsString('20/10/2026', $html);
    }

    public function test_latest_call_replaces_previous_even_if_new_call_is_outside_range(): void
    {
        $user = $this->actingAsEjecutivo();
        $contact = Contacto::factory()->create();
        $tipo = DB::table('prospeccion_tipos')->where('slug', 'llamar_luego')->value('id');
        foreach (['2026-10-20', '2026-12-20'] as $date) ProspeccionContacto::create(['contacto_id' => $contact->id, 'user_id' => $user->id, 'tipo_id' => $tipo, 'proxima_llamada' => $date]);
        $this->assertSame([], $this->agenda());
        $this->assertCount(1, $this->agenda('2026-12-01', '2027-01-01'));
    }
    public function test_latest_followup_and_closed_opportunities(): void
    {
        $user = $this->actingAsEjecutivo();
        $estado = Estado::factory()->create(['scope' => 'oportunidad', 'nombre' => 'Nueva']);
        $op = Oportunidad::factory()->withEstado($estado)->create(['user_id' => $user->id]);
        foreach (['2026-10-10', '2026-10-20'] as $date) OportunidadGestion::create(['oportunidad_id' => $op->id, 'user_id' => $user->id, 'tipo_contacto' => 'llamada', 'glosa' => 'seguimiento', 'fecha_gestion' => $date, 'fecha_vencimiento_nueva' => $date]);
        $this->assertCount(1, $this->agenda());
        $op->update(['estado_id' => Estado::factory()->create(['scope' => 'oportunidad', 'nombre' => 'Cerrada perdida'])->id]);
        $this->assertSame([], $this->agenda());
    }
    public function test_guest_can_read_but_cannot_change_manual_event(): void
    {
        $owner = User::factory()->create();
        $guest = $this->actingAsEjecutivo();
        $event = Evento::create(['user_id' => $owner->id, 'titulo' => 'Reunión', 'inicio' => '2026-10-20 09:00', 'nivel' => 'usuario']);
        $event->invitados()->attach($guest->id);
        $this->assertFalse($this->agenda()[0]['extendedProps']['puede_editar']);
        $this->putJson('/api/eventos/'.$event->id, ['inicio' => '2026-10-21 09:00'])->assertNotFound();
        $this->deleteJson('/api/eventos/'.$event->id)->assertForbidden();
    }
    public function test_invalid_recurrence_rejected_and_moving_series_updates_anchor(): void
    {
        $user = $this->actingAsEjecutivo();
        $payload = ['titulo' => 'Serie', 'inicio' => '2026-10-20 09:00', 'rrule' => 'FREQ=DAILY;COUNT=0'];
        $this->postJson('/api/eventos', $payload)->assertUnprocessable();
        $payload['rrule'] = 'FREQ=DAILY;UNTIL=20261010T090000Z';
        $this->postJson('/api/eventos', $payload)->assertUnprocessable();
        $payload['rrule'] = "DTSTART:20261020T090000\nRRULE:FREQ=DAILY;COUNT=3";
        $id = $this->postJson('/api/eventos', $payload)->assertCreated()->json('id');
        $this->putJson('/api/eventos/'.$id, ['inicio' => '2026-10-21 09:00'])->assertOk();
        $this->assertStringContainsString('DTSTART:20261021T090000', Evento::find($id)->rrule);
        $this->assertStringContainsString('COUNT=3', Evento::find($id)->rrule);
    }

    public function test_no_end_old_manual_events_do_not_repeat_forever_and_dates_validate(): void
    {
        $user = $this->actingAsEjecutivo();
        Evento::create(['user_id' => $user->id, 'titulo' => 'Pasada', 'inicio' => '2026-09-01 09:00']);
        $this->assertSame([], $this->agenda());
        $this->getJson('/api/eventos?start=invalid&end=2026-11-01')->assertUnprocessable();
    }
}
