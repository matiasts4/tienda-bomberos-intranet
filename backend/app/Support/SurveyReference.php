<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

final class SurveyReference
{
    public static function encode(array $payload): string
    {
        return 'v2.'.Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function decode(string $reference): array
    {
        if (str_starts_with($reference, 'v2.')) {
            try {
                $payload = json_decode(Crypt::decryptString(substr($reference, 3)), true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($payload) || ! in_array($payload['source'] ?? null, ['sistema', 'externa'], true)
                    || ! is_int($payload['source_id'] ?? null) || $payload['source_id'] < 1) {
                    throw new \UnexpectedValueException;
                }

                return $payload;
            } catch (\Throwable) {
                self::invalid();
            }
        }

        // Los QR impresos antes de esta entrega siguen funcionando.
        $decoded = base64_decode($reference, true);
        if ($decoded !== false && ctype_digit($decoded) && (int) $decoded > 0) {
            return ['source' => 'sistema', 'source_id' => (int) $decoded, 'legacy' => true];
        }
        $payload = $decoded !== false ? json_decode($decoded, true) : null;
        if (is_array($payload) && ($payload['type'] ?? null) === 'manual') {
            return ['legacy_manual' => $payload];
        }
        self::invalid();
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['hash' => 'El enlace de evaluación no es válido. Solicita un nuevo QR.']);
    }
}
