<?php

namespace Tests\Feature\Prospeccion;

use App\Models\Contacto;
use App\Models\ContactoActividad;
use Tests\TestCase;

class ProspeccionBitacoraContactoTest extends TestCase
{
    public function test_interaccion_queda_en_la_bitacora_del_contacto(): void
    {
        $ejecutivo = $this->actingAsEjecutivo(['name' => 'Ana Soto']);
        $contacto = Contacto::factory()->create();

        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/interaccion", [
            'tipo' => 'no_contesta',
            'canal' => 'Teléfono',
            'notas' => 'Sin respuesta',
        ])->assertCreated();

        $actividad = ContactoActividad::where('accion', ContactoActividad::ACCION_GESTION)->sole();
        $this->assertSame($ejecutivo->id, $actividad->user_id);
        $this->assertSame($contacto->id, $actividad->contacto_id);
        $this->assertSame('prospeccion', $actividad->origen);
        $this->assertSame('no_contesta', $actividad->cambios['tipo']);
    }

    public function test_calificar_queda_en_la_bitacora_del_contacto(): void
    {
        $this->seedEstadosOportunidad();
        $ejecutivo = $this->actingAsEjecutivo();
        $contacto = Contacto::factory()->create();

        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/calificar", [
            'notas' => 'Cliente interesado en trajes.',
            'user_id' => $ejecutivo->id,
        ])->assertCreated();

        $actividad = ContactoActividad::where('accion', ContactoActividad::ACCION_CALIFICADO)->sole();
        $this->assertSame($ejecutivo->id, $actividad->user_id);
        $this->assertNotNull($actividad->cambios['oportunidad_id']);
    }
}
