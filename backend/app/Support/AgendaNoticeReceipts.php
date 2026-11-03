<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/** Recibos en archivos privados: no depende del esquema ni escribe en la BD. */
class AgendaNoticeReceipts
{
    private Repository $store;

    public function __construct(?Repository $store = null)
    {
        // Almacenamiento dedicado: cache:clear no borra el estado de los avisos.
        $this->store = $store ?? new Repository(new FileStore(new Filesystem, storage_path('app/agenda-notices')));
    }

    public function read(int $userId, string $day): array
    {
        return array_values($this->store->get($this->key($userId, $day), []));
    }

    public function register(int $userId, string $day, string $mode, array $keys, CarbonImmutable $now): array
    {
        $key = $this->key($userId, $day);
        return $this->store->lock($key.':lock', 10)->block(5, function () use ($key, $keys, $mode, $now) {
            $receipts = $this->store->get($key, []);
            $accepted = [];
            foreach ($keys as $eventKey) {
                $receipt = $receipts[$eventKey] ?? ['event_key' => $eventKey, 'shown_at' => null, 'read_at' => null];
                if ($mode === 'shown') {
                    if (!$receipt['shown_at'] && !$receipt['read_at']) {
                        $receipt['shown_at'] = $now->toIso8601String();
                        $accepted[] = $eventKey;
                    }
                } else {
                    $receipt['read_at'] ??= $now->toIso8601String();
                    $accepted[] = $eventKey;
                }
                $receipts[$eventKey] = $receipt;
            }
            if (!$this->store->put($key, $receipts, $now->addDays(31))) {
                throw new RuntimeException('No se pudo guardar el estado de los avisos.');
            }
            return $accepted;
        });
    }

    private function key(int $userId, string $day): string
    {
        return 'agenda-receipts:v1:'.$userId.':'.$day;
    }
}
