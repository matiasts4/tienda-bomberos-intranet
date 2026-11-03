<?php

namespace Tests\Feature\Comercial;

use App\Models\Cargo;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\ContactoActividad;
use App\Models\Cuerpo;
use App\Models\Oportunidad;
use Tests\TestCase;

/**
 * Un contacto ingresado a mano al crear o editar un Lead debe quedar guardado
 * como contacto del Cuerpo/Compañía, con todos sus atributos.
 */
class OportunidadLeadContactoTest extends TestCase
{
    public function test_lead_nuevo_guarda_contacto_completo_en_el_cuerpo(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $cargo = Cargo::create(['nombre' => 'Comandante']);

        $this->postJson('/api/oportunidades', [
            'estado_id' => $estados['Nueva']->id,
            'user_id' => $admin->id,
            'cuerpo_id' => $cuerpo->id,
            'nombre_contacto' => 'Ana Rojas',
            'rut_contacto' => '11.111.111-1',
            'cargo_id_contacto' => $cargo->id,
            'numero_contacto' => '+569 12345678',
            'email_contacto' => 'ana@bomberos.cl',
        ])->assertCreated();

        $contacto = Contacto::firstOrFail();
        $this->assertSame('Ana Rojas', $contacto->nombre);
        $this->assertSame('11.111.111-1', $contacto->rut);
        $this->assertSame($cargo->id, $contacto->cargo_id);
        $this->assertSame('Comandante', $contacto->cargo);
        $this->assertSame('+569 12345678', $contacto->telefono);
        $this->assertSame('ana@bomberos.cl', $contacto->email);
        $this->assertTrue($contacto->cuerpos()->whereKey($cuerpo->id)->exists());
        $this->assertSame($contacto->id, Oportunidad::first()->contacto_id);

        $this->getJson("/api/cuerpos/{$cuerpo->id}/contactos")
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Ana Rojas']);

        $this->assertTrue(ContactoActividad::where('contacto_id', $contacto->id)
            ->where('accion', ContactoActividad::ACCION_VINCULOS)->exists());
    }

    public function test_rut_invalido_se_rechaza(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $admin = $this->actingAsUser(['role' => 'superadmin']);

        $this->postJson('/api/oportunidades', [
            'estado_id' => $estados['Nueva']->id,
            'user_id' => $admin->id,
            'nombre_contacto' => 'Ana Rojas',
            'rut_contacto' => '11.111.111-2',
        ])->assertStatus(422)->assertJsonValidationErrors(['rut_contacto']);
    }

    public function test_rut_existente_reutiliza_el_contacto_y_lo_vincula(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $compania = Compania::create(['id' => '0100101', 'cuerpo_id' => $cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);
        $existente = Contacto::create(['nombre' => 'Ana', 'rut' => '11.111.111-1']);
        $existente->cuerpos()->attach($cuerpo->id);

        $this->postJson('/api/oportunidades', [
            'estado_id' => $estados['Nueva']->id,
            'user_id' => $admin->id,
            'compania_id' => $compania->id,
            'nombre_contacto' => 'Ana Rojas',
            'rut_contacto' => '11.111.111-1',
        ])->assertCreated();

        $this->assertSame(1, Contacto::count());
        $existente->refresh();
        $this->assertSame('Ana Rojas', $existente->nombre);
        $this->assertTrue($existente->companias()->whereKey($compania->id)->exists());
        $this->assertTrue($existente->cuerpos()->whereKey($cuerpo->id)->exists());
    }

    public function test_lead_de_una_compania_vincula_al_contacto_tambien_con_su_cuerpo(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $compania = Compania::create(['id' => '0100101', 'cuerpo_id' => $cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);

        $this->postJson('/api/oportunidades', [
            'estado_id' => $estados['Nueva']->id,
            'user_id' => $admin->id,
            'compania_id' => $compania->id,
            'nombre_contacto' => 'Rosa Díaz',
        ])->assertCreated();

        $contacto = Contacto::where('nombre', 'Rosa Díaz')->firstOrFail();
        $this->assertTrue($contacto->companias()->whereKey($compania->id)->exists());
        $this->assertTrue($contacto->cuerpos()->whereKey($cuerpo->id)->exists());
    }

    public function test_editar_lead_con_contacto_manual_lo_vincula_a_la_entidad_existente(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $op = Oportunidad::create([
            'estado_id' => $estados['Nueva']->id,
            'user_id' => $admin->id,
            'cuerpo_id' => $cuerpo->id,
            'fecha_ingreso' => now()->toDateString(),
        ]);

        // El request no repite cuerpo_id: debe usarse el que ya tiene el lead.
        $this->patchJson("/api/oportunidades/{$op->id}", [
            'nombre_contacto' => 'Luis Soto',
            'cargo_contacto' => 'Capitán',
            'email_contacto' => 'luis@bomberos.cl',
        ])->assertOk();

        $contacto = Contacto::where('nombre', 'Luis Soto')->firstOrFail();
        $this->assertSame('Capitán', $contacto->cargo);
        $this->assertTrue($contacto->cuerpos()->whereKey($cuerpo->id)->exists());
        $this->assertSame($contacto->id, $op->fresh()->contacto_id);
    }
}
