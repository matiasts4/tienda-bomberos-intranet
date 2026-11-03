<?php

namespace Tests;

use App\Models\Estado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Sobrescribimos refreshTestDatabase para correr ÚNICAMENTE las migraciones
     * de test (tests/database/migrations) en vez de todas las reales —
     * el historial real tiene renombres y FKs que rompen SQLite en memoria.
     */
    protected function refreshTestDatabase()
    {
        if ($this->app['config']->get('database.default') !== 'mysql' && ! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', [
                '--path' => 'tests/database/migrations',
                '--realpath' => false,
                '--drop-views' => false,
                '--drop-types' => false,
                '--force' => true,
            ]);

            $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }


    /**
     * Crea un usuario y autentica como ese usuario via Sanctum.
     */
    protected function actingAsUser(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsEjecutivo(array $attrs = []): User
    {
        return $this->actingAsUser(array_merge(['role' => 'ejecutivo'], $attrs));
    }

    protected function actingAsAdmin(array $attrs = []): User
    {
        return $this->actingAsUser(array_merge(['role' => 'admin'], $attrs));
    }

    /**
     * Inserta los estados de cotización requeridos por el controller.
     *
     * @return array<string, \App\Models\Estado>
     */
    protected function seedEstadosCotizacion(): array
    {
        $nombres = [
            'Cotización Enviada',
            'Aprobada por Cliente',
            'Rechazada',
            'Anulado',
        ];

        $estados = [];
        foreach ($nombres as $i => $nombre) {
            $estados[$nombre] = Estado::create([
                'nombre' => $nombre,
                'scope' => 'cotizacion',
                'orden' => $i + 1,
                'color' => '#000000',
            ]);
        }

        return $estados;
    }

    /**
     * @return array<string, \App\Models\Estado>
     */
    protected function seedEstadosOportunidad(): array
    {
        $nombres = [
            'Nueva',
            'En Contacto',
            'Cotización Enviada',
            'Aprobada - Cotización',
            'Perdida',
        ];

        $estados = [];
        foreach ($nombres as $i => $nombre) {
            $estados[$nombre] = Estado::create([
                'nombre' => $nombre,
                'scope' => 'oportunidad',
                'orden' => $i + 1,
                'color' => '#000000',
            ]);
        }

        return $estados;
    }
}
