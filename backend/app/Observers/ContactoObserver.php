<?php

namespace App\Observers;

use App\Models\Contacto;
use App\Models\ContactoActividad;

/**
 * Registra automáticamente en la bitácora el alta, edición y baja de contactos,
 * sin importar desde qué controlador se hagan.
 */
class ContactoObserver
{
    public function created(Contacto $contacto): void
    {
        ContactoActividad::registrar(
            $contacto,
            ContactoActividad::ACCION_CREADO,
            'Creó el contacto ' . $contacto->nombre
        );
    }

    public function updated(Contacto $contacto): void
    {
        $cambios = ContactoActividad::diffDe($contacto);

        if (empty($cambios)) {
            return;
        }

        ContactoActividad::registrar(
            $contacto,
            ContactoActividad::ACCION_ACTUALIZADO,
            'Editó ' . implode(', ', array_keys($cambios)),
            $cambios
        );
    }

    public function deleted(Contacto $contacto): void
    {
        ContactoActividad::registrar(
            $contacto,
            ContactoActividad::ACCION_ELIMINADO,
            'Eliminó el contacto ' . $contacto->nombre
        );
    }
}
