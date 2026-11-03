<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class StorageProxyTest extends TestCase
{
    public function test_bloquea_path_traversal()
    {
        $response = $this->get('/api/storage-proxy/../.env');
        $response->assertStatus(403);
    }

    public function test_bloquea_ruta_absoluta()
    {
        $response = $this->get('/api/storage-proxy//etc/passwd');
        $response->assertStatus(403);
    }

    public function test_permite_acceso_valido()
    {
        Storage::disk('public')->put('test.txt', 'Hola');
        $response = $this->get('/api/storage-proxy/test.txt');
        $response->assertStatus(200);
        Storage::disk('public')->delete('test.txt');
    }
}
