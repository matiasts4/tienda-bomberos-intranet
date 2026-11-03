<?php

namespace Tests\Unit;

use App\Support\AgendaNoticeReceipts;
use Carbon\CarbonImmutable;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

/** Estas pruebas solo usan archivos temporales; no arrancan Laravel ni una BD. */
class AgendaNoticeReceiptsTest extends TestCase
{
    private string $path;
    private AgendaNoticeReceipts $receipts;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/tb-agenda-unit-'.bin2hex(random_bytes(8));
        $this->receipts = new AgendaNoticeReceipts(new Repository(new FileStore(new Filesystem, $this->path)));
        $this->now = CarbonImmutable::now('America/Santiago');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->path);
    }

    public function test_shown_is_idempotent_and_persists_across_instances(): void
    {
        $day = $this->now->toDateString();
        $this->assertSame(['event'], $this->receipts->register(1, $day, 'shown', ['event'], $this->now));
        $other = new AgendaNoticeReceipts(new Repository(new FileStore(new Filesystem, $this->path)));
        $this->assertSame([], $other->register(1, $day, 'shown', ['event'], $this->now));
        $this->assertCount(1, $other->read(1, $day));
        $this->assertNull($other->read(1, $day)[0]['read_at']);
    }

    public function test_read_prevents_shown_without_completing_the_event(): void
    {
        $day = $this->now->toDateString();
        $this->receipts->register(1, $day, 'read', ['event'], $this->now);
        $this->assertSame([], $this->receipts->register(1, $day, 'shown', ['event'], $this->now));
        $this->assertCount(1, $this->receipts->read(1, $day));
        $this->assertNotNull($this->receipts->read(1, $day)[0]['read_at']);
    }

    public function test_users_and_days_have_isolated_receipts(): void
    {
        $day = $this->now->toDateString();
        $this->receipts->register(1, $day, 'shown', ['event'], $this->now);
        $this->assertSame([], $this->receipts->read(2, $day));
        $tomorrow = $this->now->addDay();
        $this->assertSame(['event'], $this->receipts->register(1, $tomorrow->toDateString(), 'shown', ['event'], $tomorrow));
    }

    public function test_a_second_owner_cannot_enter_an_active_lock(): void
    {
        $day = $this->now->toDateString();
        $store = new Repository(new FileStore(new Filesystem, $this->path));
        $first = $store->lock('agenda-receipts:v1:1:'.$day.':lock', 10);
        $second = $store->lock('agenda-receipts:v1:1:'.$day.':lock', 10);
        $this->assertTrue($first->get());
        $this->assertFalse($second->get());
        $first->release();
        $this->assertTrue($second->get());
        $second->release();
    }
}
