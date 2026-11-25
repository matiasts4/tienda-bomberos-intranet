<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Toda vista Blade que el código pide por nombre debe existir en resources/views.
 * Si falta, el PDF o el correo correspondiente responde 500 en un clon limpio.
 */
class VistasReferenciadasTest extends TestCase
{
    public function test_toda_vista_referenciada_en_el_codigo_existe(): void
    {
        $patron = "/(?:view|loadView|markdown)\\(\\s*['\"]([a-zA-Z0-9_.-]+)['\"]|view:\\s*['\"]([a-zA-Z0-9_.-]+)['\"]/";
        $faltantes = [];

        foreach (File::allFiles(app_path()) as $archivo) {
            preg_match_all($patron, $archivo->getContents(), $coincidencias, PREG_SET_ORDER);
            foreach ($coincidencias as $c) {
                $nombre = ($c[2] ?? '') !== '' ? $c[2] : $c[1];
                if (! View::exists($nombre)) {
                    $faltantes[$nombre][] = $archivo->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $faltantes, 'Vistas referenciadas que no existen: '.json_encode($faltantes, JSON_UNESCAPED_SLASHES));
    }
}
