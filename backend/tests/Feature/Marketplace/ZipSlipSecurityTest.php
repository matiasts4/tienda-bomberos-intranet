<?php

namespace Tests\Feature\Marketplace;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use ZipArchive;
use Illuminate\Support\Facades\File;

class ZipSlipSecurityTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = storage_path('app/test_zips_' . uniqid());
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        // Clean up any marketplace temp folders created during tests
        $marketplaceTemp = storage_path('app/public/marketplace/temp');
        if (file_exists($marketplaceTemp)) {
            File::deleteDirectory($marketplaceTemp);
        }
        parent::tearDown();
    }

    /**
     * Create a ZIP file with specified entries.
     * Each entry is ['name' => 'filename', 'content' => 'data']
     */
    private function createZipWithEntries(array $entries): string
    {
        $zipPath = $this->tempDir . '/test.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entry) {
            $zip->addFromString($entry['name'], $entry['content'] ?? 'fake-image-data');
        }

        $zip->close();
        return $zipPath;
    }

    private function uploadZip(string $zipPath)
    {
        $this->withoutMiddleware();
        $uploadedFile = new UploadedFile(
            $zipPath,
            'test.zip',
            'application/zip',
            null,
            true
        );

        return $this->postJson('/api/admin/marketplace/products/upload-images-zip', [
            'zip_file' => $uploadedFile,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 1: Valid ZIP with .webp images
    // ──────────────────────────────────────────────────────────────
    public function test_valid_zip_with_webp_images(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => 'image1.webp', 'content' => 'fake-webp-1'],
            ['name' => 'image2.webp', 'content' => 'fake-webp-2'],
        ]);

        $response = $this->uploadZip($zipPath);

        $response->assertOk()
            ->assertJsonStructure(['batch_id', 'files'])
            ->assertJsonCount(2, 'files');
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 2: ZIP with ../evil.webp
    // ──────────────────────────────────────────────────────────────
    public function test_zip_with_single_traversal_is_blocked(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => '../evil.webp', 'content' => 'MALICIOUS'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();

        // Verify the malicious file was NOT extracted anywhere
        $batchId = $response->json('batch_id');
        $parentDir = storage_path('app/public/marketplace/temp');
        $evilPath = $parentDir . '/evil.webp';
        $this->assertFileDoesNotExist($evilPath, 'Single traversal ../evil.webp must not be written');
        $this->assertCount(0, $response->json('files'));
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 3: ZIP with ../../evil.webp
    // ──────────────────────────────────────────────────────────────
    public function test_zip_with_double_traversal_is_blocked(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => '../../evil.webp', 'content' => 'MALICIOUS'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();

        $evilPath = storage_path('app/public/marketplace/evil.webp');
        $this->assertFileDoesNotExist($evilPath);
        $this->assertCount(0, $response->json('files'));
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 4: ZIP with ..\..\evil.webp (backslash traversal)
    // ──────────────────────────────────────────────────────────────
    public function test_zip_with_backslash_traversal_is_blocked(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => "..\\..\\evil.webp", 'content' => 'MALICIOUS'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();
        $this->assertCount(0, $response->json('files'));
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 5: ZIP with /etc/passwd
    // ──────────────────────────────────────────────────────────────
    public function test_zip_with_absolute_unix_path_is_blocked(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => '/etc/passwd', 'content' => 'root:x:0:0'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();
        $this->assertCount(0, $response->json('files'));
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 6: ZIP with C:\Windows\... (absolute Windows path)
    // ──────────────────────────────────────────────────────────────
    public function test_zip_with_absolute_windows_path_is_blocked(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => 'C:\\Windows\\System32\\evil.webp', 'content' => 'MALICIOUS'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();
        $this->assertCount(0, $response->json('files'));
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 7: ZIP with .php file
    // ──────────────────────────────────────────────────────────────
    public function test_zip_with_php_file_is_blocked(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => 'shell.php', 'content' => '<?php echo "Innocuous PHP test content"; ?>'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();
        $this->assertCount(0, $response->json('files'));

        // Verify .php file was NOT written to disk
        $batchId = $response->json('batch_id');
        $extractDir = storage_path("app/public/marketplace/temp/{$batchId}");
        if (file_exists($extractDir)) {
            $phpFiles = glob($extractDir . '/*.php');
            $this->assertEmpty($phpFiles, 'PHP files must not be extracted');
        }
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 8: ZIP attempting to write .env
    // ──────────────────────────────────────────────────────────────
    public function test_zip_with_env_file_is_blocked(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => '.env', 'content' => 'APP_KEY=base64:MALICIOUS'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();
        $this->assertCount(0, $response->json('files'));
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 9: ZIP with mix of valid + malicious files
    // ──────────────────────────────────────────────────────────────
    public function test_zip_with_mixed_valid_and_malicious_files(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => 'good1.webp', 'content' => 'valid-image-1'],
            ['name' => '../evil.webp', 'content' => 'MALICIOUS'],
            ['name' => 'good2.png', 'content' => 'valid-image-2'],
            ['name' => 'shell.php', 'content' => '<?php evil(); ?>'],
            ['name' => 'good3.jpg', 'content' => 'valid-image-3'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();

        // Only the 3 valid images should be extracted
        $this->assertCount(3, $response->json('files'));

        // Verify skipped entries are reported
        $this->assertTrue($response->json('skipped') !== null);
        $this->assertCount(2, $response->json('skipped'));
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 10: No file ends up outside the temp directory
    // ──────────────────────────────────────────────────────────────
    public function test_no_file_escapes_temp_directory(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => '../../../../../../../tmp/escape.webp', 'content' => 'ESCAPE'],
            ['name' => "..\\..\\..\\..\\escape2.webp", 'content' => 'ESCAPE2'],
            ['name' => 'legit.webp', 'content' => 'valid'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();

        $batchId = $response->json('batch_id');
        $extractDir = storage_path("app/public/marketplace/temp/{$batchId}");

        // Only 1 legit file should exist
        $this->assertCount(1, $response->json('files'));

        // Verify no files leaked outside
        $this->assertFileDoesNotExist(storage_path('app/public/marketplace/temp/escape.webp'));
        $this->assertFileDoesNotExist(storage_path('app/public/marketplace/escape.webp'));
        $this->assertFileDoesNotExist(storage_path('app/public/escape.webp'));

        // Verify legit file IS in the correct directory
        if (file_exists($extractDir)) {
            $files = File::allFiles($extractDir);
            $this->assertCount(1, $files);
            $this->assertEquals('legit.webp', $files[0]->getFilename());
        }
    }

    // ──────────────────────────────────────────────────────────────
    // TEST 11: Legitimate flow — images with subdirectories are flattened
    // ──────────────────────────────────────────────────────────────
    public function test_legitimate_zip_with_subdirectories_works(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => 'folder/sub/image1.webp', 'content' => 'nested-image'],
            ['name' => 'image2.webp', 'content' => 'root-image'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();

        // Both images should be extracted (flattened)
        $this->assertCount(2, $response->json('files'));
        $this->assertNotNull($response->json('batch_id'));
    }

    // ──────────────────────────────────────────────────────────────
    // TEST EXTRA: Dangerous extensions (.phar, .phtml, .html, .js)
    // ──────────────────────────────────────────────────────────────
    public function test_zip_with_dangerous_extensions_are_blocked(): void
    {
        $zipPath = $this->createZipWithEntries([
            ['name' => 'exploit.phar', 'content' => 'PHAR'],
            ['name' => 'exploit.phtml', 'content' => 'PHTML'],
            ['name' => 'exploit.html', 'content' => '<script>alert(1)</script>'],
            ['name' => 'exploit.js', 'content' => 'alert(1)'],
            ['name' => 'exploit.svg', 'content' => '<svg onload="alert(1)">'],
        ]);

        $response = $this->uploadZip($zipPath);
        $response->assertOk();
        $this->assertCount(0, $response->json('files'));
    }
}
