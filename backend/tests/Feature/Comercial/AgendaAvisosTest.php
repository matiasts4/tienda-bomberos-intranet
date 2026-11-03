<?php

namespace Tests\Feature\Comercial;

use App\Models\{User, Evento, Cotizacion, Estado, Despacho};
use App\Support\AgendaNoticeReceipts;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgendaAvisosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['path.storage'] = sys_get_temp_dir().'/tb-agenda-feature-'.bin2hex(random_bytes(8));
        $this->travelTo(\Carbon\Carbon::parse('2026-10-11 12:00:00', 'America/Santiago'));
    }

    private function quote(User $owner, string $state, array $attributes = []): Cotizacion
    {
        $estado = Estado::firstOrCreate(['scope' => 'cotizacion', 'nombre' => $state], ['orden' => 1]);
        return Cotizacion::factory()->forUser($owner)->withEstado($estado)->create(array_merge([
            'fecha_vencimiento' => '2026-10-11', 'fecha_venta' => '2026-10-01', 'plazo_produccion' => '20 dias',
        ], $attributes));
    }

    private function key(): string { return 'manual-12:2026-10-11:'.str_repeat('a', 64); }
    private function receipt(string $mode, ?string $day = null): array
    {
        return ['mode' => $mode, 'day' => $day ?? '2026-10-11', 'keys' => [$this->key()]];
    }

    public function test_authentication_is_required(): void
    {
        $this->getJson('/api/agenda/avisos')->assertUnauthorized();
        $this->postJson('/api/agenda/avisos/registrar', $this->receipt('shown'))->assertUnauthorized();
    }

    public function test_window_today_through_three_days_and_manual_past_is_not_pending(): void
    {
        $user = $this->actingAsEjecutivo();
        foreach (['2026-10-10', '2026-10-11', '2026-10-14', '2026-10-15'] as $day) {
            Evento::create(['user_id' => $user->id, 'titulo' => $day, 'inicio' => $day.' 09:00']);
        }
        $response = $this->getJson('/api/agenda/avisos')->assertOk()->assertJsonPath('day', '2026-10-11')->assertJsonPath('end', '2026-10-15');
        $this->assertSame(['2026-10-11', '2026-10-14'], array_column($response->json('events'), 'title'));
    }

    public function test_superadmin_receives_own_agenda_and_invitations_without_global_private_events(): void
    {
        $user = $this->actingAsUser(['role' => 'superadmin']);
        $other = User::factory()->create();
        $private = Evento::create(['user_id' => $other->id, 'titulo' => 'Invitación', 'inicio' => '2026-10-12 09:00']);
        $this->quote($other, 'Cotización Enviada');
        $this->assertSame([], $this->getJson('/api/agenda/avisos?ejecutivo_id='.$other->id)->assertOk()->json('events'));
        $private->invitados()->attach($user->id);
        $this->assertCount(1, $this->getJson('/api/agenda/avisos')->json('events'));
        // El calendario administrativo conserva su vista global anterior.
        $this->assertCount(2, $this->getJson('/api/eventos?start=2026-10-11&end=2026-10-15')->json());
    }

    public function test_quote_conversion_replaces_alert_and_delivered_sales_disappear(): void
    {
        $user = $this->actingAsEjecutivo();
        $quote = $this->quote($user, 'Cotización Enviada', ['fecha_vencimiento' => '2026-10-09']);
        $this->assertSame('quote-'.$quote->id, $this->getJson('/api/agenda/avisos')->json('events.0.id'));
        $sale = Estado::create(['scope' => 'cotizacion', 'nombre' => 'Ventas', 'orden' => 2]);
        $quote->update(['estado_id' => $sale->id, 'fecha_venta' => '2026-10-11', 'plazo_produccion' => 'Stock disponible']);
        $this->assertSame('sale-'.$quote->id, $this->getJson('/api/agenda/avisos')->json('events.0.id'));
        $delivered = Estado::create(['scope' => 'despacho', 'nombre' => 'Entregado', 'orden' => 4]);
        Despacho::create(['cotizacion_id' => $quote->id, 'estado_id' => $delivered->id]);
        $this->assertSame([], $this->getJson('/api/agenda/avisos')->json('events'));
    }

    public function test_series_are_available_for_expansion_and_fingerprint_changes_on_rescheduling(): void
    {
        $user = $this->actingAsEjecutivo();
        $event = Evento::create(['user_id' => $user->id, 'titulo' => 'Serie', 'inicio' => '2026-10-01 09:00', 'rrule' => 'FREQ=DAILY;COUNT=20']);
        $before = $this->getJson('/api/agenda/avisos')->json('events.0.fingerprint');
        $this->assertSame(64, strlen($before));
        $event->update(['inicio' => '2026-10-12 09:00']);
        $this->assertNotSame($before, $this->getJson('/api/agenda/avisos')->json('events.0.fingerprint'));
    }

    public function test_claim_is_idempotent_and_read_is_separate_from_shown(): void
    {
        $user = $this->actingAsEjecutivo();
        $this->postJson('/api/agenda/avisos/registrar', $this->receipt('shown'))->assertOk()->assertJsonPath('accepted.0', $this->key());
        $this->postJson('/api/agenda/avisos/registrar', $this->receipt('shown'))->assertOk()->assertExactJson(['accepted' => []]);
        $this->assertNull($this->getJson('/api/agenda/avisos')->json('receipts.0.read_at'));
        $this->postJson('/api/agenda/avisos/registrar', $this->receipt('read'))->assertOk();
        $this->assertNotNull($this->getJson('/api/agenda/avisos')->json('receipts.0.read_at'));
        $this->assertCount(1, (new AgendaNoticeReceipts)->read($user->id, '2026-10-11'));
    }

    public function test_claims_are_private_per_user_and_daily_and_read_prevents_toast(): void
    {
        $first = $this->actingAsEjecutivo();
        $this->postJson('/api/agenda/avisos/registrar', $this->receipt('read'))->assertOk();
        $this->postJson('/api/agenda/avisos/registrar', $this->receipt('shown'))->assertExactJson(['accepted' => []]);
        $second = $this->actingAsEjecutivo();
        $this->assertSame([], $this->getJson('/api/agenda/avisos')->json('receipts'));
        $this->postJson('/api/agenda/avisos/registrar', $this->receipt('shown'))->assertJsonCount(1, 'accepted');
        Sanctum::actingAs($first);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-12 00:01', 'America/Santiago'));
        $this->postJson('/api/agenda/avisos/registrar', $this->receipt('shown', '2026-10-12'))->assertJsonCount(1, 'accepted');
        $this->assertNotEquals($first->id, $second->id);
    }

    public function test_stale_day_and_invalid_keys_are_rejected(): void
    {
        $this->actingAsEjecutivo();
        $this->postJson('/api/agenda/avisos/registrar', $this->receipt('shown', '2026-10-10'))->assertStatus(409);
        $this->postJson('/api/agenda/avisos/registrar', ['day' => '2026-10-11', 'mode' => 'shown', 'keys' => ['bad']])->assertUnprocessable();
    }
}
