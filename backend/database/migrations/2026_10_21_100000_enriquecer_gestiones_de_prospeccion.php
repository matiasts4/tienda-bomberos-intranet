<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resultados de llamada como los vive un ejecutivo comercial:
 *
 * - Una gestión guarda hora y objetivo del seguimiento, motivo de un "no",
 *   el número de intento sin respuesta (cadencia), a quién se derivó y los
 *   datos de calificación.
 * - Tipos nuevos: `derivado` ("habla con el capitán") y `numero_equivocado`
 *   (dato malo, no un intento fallido). `agendar_seguimiento` pasa a ser el
 *   seguimiento acordado en una conversación; `llamar_luego`, el reagendado
 *   sin contacto.
 * - `prospeccion_pausas`: un "no" de la compañía pausa a todos sus contactos
 *   hasta una fecha, para no volver a llamarlos al día siguiente.
 *
 * Todo es aditivo; down() lo deshace.
 */
return new class extends Migration
{
    private const TIPOS = [
        ['slug' => 'no_contesta', 'nombre' => 'No contesta', 'color' => 'slate', 'icono' => 'phone-off', 'es_final' => false, 'score' => 0],
        ['slug' => 'buzon', 'nombre' => 'Dejé mensaje', 'color' => 'amber', 'icono' => 'phone-missed', 'es_final' => false, 'score' => 0],
        ['slug' => 'llamar_luego', 'nombre' => 'Reagendado sin contacto', 'color' => 'blue', 'icono' => 'clock', 'es_final' => false, 'score' => 5],
        ['slug' => 'agendar_seguimiento', 'nombre' => 'Seguimiento acordado', 'color' => 'orange', 'icono' => 'calendar', 'es_final' => false, 'score' => 20],
        ['slug' => 'derivado', 'nombre' => 'Derivado a otro contacto', 'color' => 'violet', 'icono' => 'forward', 'es_final' => true, 'score' => 10],
        ['slug' => 'numero_equivocado', 'nombre' => 'Número equivocado', 'color' => 'zinc', 'icono' => 'phone-off', 'es_final' => true, 'score' => 0],
        ['slug' => 'no_califica', 'nombre' => 'No interesado', 'color' => 'red', 'icono' => 'x-circle', 'es_final' => true, 'score' => -1],
        ['slug' => 'calificado', 'nombre' => 'Calificado', 'color' => 'emerald', 'icono' => 'star', 'es_final' => true, 'score' => 100],
    ];

    /** Nombres previos, para que down() deje la tabla como estaba. */
    private const NOMBRES_PREVIOS = [
        'buzon' => 'Buzón de voz',
        'llamar_luego' => 'Llamar luego',
        'agendar_seguimiento' => 'Agendar seguimiento',
        'no_califica' => 'No califica',
    ];

    private const TIPOS_NUEVOS = ['derivado', 'numero_equivocado'];

    public function up(): void
    {
        Schema::table('prospeccion_contactos', function (Blueprint $table) {
            $table->time('hora')->nullable()->after('proxima_llamada');
            $table->string('objetivo', 120)->nullable()->after('hora');
            $table->string('motivo', 40)->nullable()->after('objetivo');
            $table->unsignedSmallInteger('intento')->nullable()->after('motivo');
            $table->foreignId('derivado_a_id')->nullable()->after('intento')
                ->constrained('contactos')->nullOnDelete();
            $table->json('datos')->nullable()->after('derivado_a_id');
        });

        Schema::create('prospeccion_pausas', function (Blueprint $table) {
            $table->id();
            $table->string('compania_id', 15)->nullable();
            $table->foreign('compania_id')->references('id')->on('companias')->cascadeOnDelete();
            $table->string('cuerpo_id', 15)->nullable();
            $table->foreign('cuerpo_id')->references('id')->on('cuerpos')->cascadeOnDelete();
            $table->date('hasta');
            $table->string('motivo', 40)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gestion_id')->nullable()->constrained('prospeccion_contactos')->nullOnDelete();
            $table->timestamp('levantada_at')->nullable();
            $table->timestamps();

            $table->index(['compania_id', 'hasta']);
            $table->index(['cuerpo_id', 'hasta']);
        });

        $ahora = now();
        foreach (self::TIPOS as $tipo) {
            $existe = DB::table('prospeccion_tipos')->where('slug', $tipo['slug'])->exists();
            DB::table('prospeccion_tipos')->updateOrInsert(
                ['slug' => $tipo['slug']],
                array_merge($tipo, ['updated_at' => $ahora], $existe ? [] : ['created_at' => $ahora])
            );
        }
    }

    public function down(): void
    {
        foreach (self::NOMBRES_PREVIOS as $slug => $nombre) {
            DB::table('prospeccion_tipos')->where('slug', $slug)->update(['nombre' => $nombre]);
        }

        // Los tipos nuevos solo se borran si nadie los usa: una gestión registrada
        // es historia comercial y no debe perderse por un rollback.
        foreach (self::TIPOS_NUEVOS as $slug) {
            $id = DB::table('prospeccion_tipos')->where('slug', $slug)->value('id');
            if ($id && !DB::table('prospeccion_contactos')->where('tipo_id', $id)->exists()) {
                DB::table('prospeccion_tipos')->where('id', $id)->delete();
            }
        }

        Schema::dropIfExists('prospeccion_pausas');

        Schema::table('prospeccion_contactos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('derivado_a_id');
            $table->dropColumn(['hora', 'objetivo', 'motivo', 'intento', 'datos']);
        });
    }
};
