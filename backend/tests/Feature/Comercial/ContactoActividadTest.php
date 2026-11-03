<?php

namespace Tests\Feature\Comercial;

use App\Models\Compania;
use App\Models\Contacto;
use App\Models\ContactoActividad;
use App\Models\Cuerpo;
use Tests\TestCase;

class ContactoActividadTest extends TestCase
{
    public function test_crear_contacto_registra_al_ejecutivo(): void
    {
        $ejecutivo = $this->actingAsEjecutivo(['name' => 'Ana Soto']);

        $this->postJson('/api/contactos', ['nombre' => 'Juan Pérez'])->assertCreated();

        $actividad = ContactoActividad::where('accion', ContactoActividad::ACCION_CREADO)->sole();
        $this->assertSame($ejecutivo->id, $actividad->user_id);
        $this->assertSame('Ana Soto', $actividad->user_nombre);
        $this->assertSame('Juan Pérez', $actividad->contacto_nombre);
    }

    public function test_editar_contacto_registra_los_campos_modificados(): void
    {
        $ejecutivo = $this->actingAsEjecutivo();
        $contacto = Contacto::factory()->create(['nombre' => 'Juan Pérez', 'telefono' => '111']);

        $this->patchJson("/api/contactos/{$contacto->id}", ['telefono' => '222'])->assertOk();

        $actividad = ContactoActividad::where('accion', ContactoActividad::ACCION_ACTUALIZADO)->sole();
        $this->assertSame($ejecutivo->id, $actividad->user_id);
        $this->assertSame($contacto->id, $actividad->contacto_id);
        $this->assertSame(['telefono' => ['antes' => '111', 'despues' => '222']], $actividad->cambios);
    }

    public function test_editar_sin_cambios_no_genera_registro(): void
    {
        $this->actingAsEjecutivo();
        $contacto = Contacto::factory()->create(['telefono' => '111']);

        $this->patchJson("/api/contactos/{$contacto->id}", ['telefono' => '111'])->assertOk();

        $this->assertSame(0, ContactoActividad::where('accion', ContactoActividad::ACCION_ACTUALIZADO)->count());
    }

    public function test_cambio_de_vinculos_queda_registrado(): void
    {
        $this->actingAsEjecutivo();
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $compania = Compania::create([
            'id' => '0100101', 'cuerpo_id' => $cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1,
        ]);
        $contacto = Contacto::factory()->create();

        $this->patchJson("/api/contactos/{$contacto->id}", [
            'cuerpo_ids' => [$cuerpo->id],
            'compania_ids' => [$compania->id],
        ])->assertOk();

        $actividad = ContactoActividad::where('accion', ContactoActividad::ACCION_VINCULOS)->sole();
        $this->assertSame([$cuerpo->id], $actividad->cambios['cuerpos']['agregados']);
        $this->assertSame([$compania->id], $actividad->cambios['companias']['agregados']);
        $this->assertSame('contactos', $actividad->origen);
    }

    public function test_eliminar_contacto_conserva_la_bitacora(): void
    {
        $ejecutivo = $this->actingAsEjecutivo();
        $contacto = Contacto::factory()->create(['nombre' => 'Juan Pérez']);

        $this->deleteJson("/api/contactos/{$contacto->id}")->assertNoContent();

        $actividad = ContactoActividad::where('accion', ContactoActividad::ACCION_ELIMINADO)->sole();
        $this->assertSame($ejecutivo->id, $actividad->user_id);
        $this->assertSame('Juan Pérez', $actividad->contacto_nombre);
        $this->assertDatabaseMissing('contactos', ['id' => $contacto->id]);
    }

    public function test_historial_del_contacto_lista_las_acciones_mas_recientes_primero(): void
    {
        $this->actingAsAdmin(['name' => 'Ana Soto']);

        $this->postJson('/api/contactos', ['nombre' => 'Juan Pérez'])->assertCreated();
        $contacto = Contacto::firstOrFail();
        $this->patchJson("/api/contactos/{$contacto->id}", ['telefono' => '222'])->assertOk();

        $this->getJson("/api/contactos/{$contacto->id}/actividades")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.accion', ContactoActividad::ACCION_ACTUALIZADO)
            ->assertJsonPath('0.user.name', 'Ana Soto')
            ->assertJsonPath('1.accion', ContactoActividad::ACCION_CREADO);
    }

    public function test_feed_global_filtra_por_ejecutivo(): void
    {
        $otro = $this->actingAsEjecutivo();
        Contacto::factory()->create(['nombre' => 'Contacto Otro']);

        $mio = $this->actingAsEjecutivo();
        Contacto::factory()->create(['nombre' => 'Contacto Mío']);

        $this->actingAsAdmin();

        $this->getJson('/api/contactos/actividades?user_id=' . $mio->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.contacto_nombre', 'Contacto Mío');

        $this->getJson('/api/contactos/actividades')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_bitacora_es_solo_para_administracion(): void
    {
        $this->actingAsEjecutivo();
        $contacto = Contacto::factory()->create();

        $this->getJson("/api/contactos/{$contacto->id}/actividades")->assertForbidden();
        $this->getJson('/api/contactos/actividades')->assertForbidden();
    }

    public function test_bitacora_requiere_autenticacion(): void
    {
        $contacto = Contacto::factory()->create();

        $this->getJson("/api/contactos/{$contacto->id}/actividades")->assertUnauthorized();
        $this->getJson('/api/contactos/actividades')->assertUnauthorized();
    }
}
