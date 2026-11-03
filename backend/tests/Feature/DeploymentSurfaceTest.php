<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentSurfaceTest extends TestCase
{
    public function test_stock_diagnostic_is_not_exposed(): void
    {
        $this->getJson('/api/test-stock')->assertNotFound();
    }

    public function test_experimental_whatsapp_webhook_is_not_exposed(): void
    {
        $this->postJson('/api/webhooks/twilio/whatsapp', [])->assertNotFound();
    }
}
