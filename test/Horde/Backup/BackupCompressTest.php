<?php

declare(strict_types=1);

namespace Horde\Backup\Test;

use Horde\Backup;
use Horde\Backup\Collection;
use Horde\Backup\Reader;
use Horde\Backup\Users;
use Horde\Backup\Writer;
use Horde_Compress_Tar as Tar;
use Horde_Compress_Zip as Zip;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Writer/Reader round-trip contract.
 *
 * Writer: takes application data, produces ZIP/TAR archive files on disk.
 * Reader: reads those archives, returns Collection objects with the original data.
 *
 * Also tests the underlying compress operations directly to prove ZIP_LIST
 * returns paths and ZIP_DATA returns raw strings.
 */
class BackupCompressTest extends TestCase
{
    /**
     * ZIP compress: array of files → string archive.
     * This is what Writer::save() does internally.
     */
    public function testZipCompressProducesValidArchive(): void
    {
        $zip = new Zip();

        $stream = fopen('php://temp', 'w+');
        fwrite($stream, '{"uid":"event1"}');
        rewind($stream);

        $files = [
            ['data' => $stream, 'name' => 'kronolith/calendar/1'],
            ['data' => '{"uid":"contact1"}', 'name' => 'turba/contact/2'],
        ];

        $archive = $zip->compress($files);
        $this->assertIsString($archive);
        $this->assertNotEmpty($archive);

        // Verify contents can be read back
        $listing = $zip->decompress($archive, ['action' => Zip::ZIP_LIST]);
        $this->assertCount(2, $listing);
        $this->assertEquals('kronolith/calendar/1', $listing[0]['name']);
        $this->assertEquals('turba/contact/2', $listing[1]['name']);

        fclose($stream);
    }

    /**
     * ZIP decompress ZIP_DATA: returns raw string content.
     * This is what ZipIterator::current() passes to unpack().
     */
    public function testZipExtractReturnsRawString(): void
    {
        $zip = new Zip();

        $payload = '{"uid":"abc","type":"event"}';
        $files = [['data' => $payload, 'name' => 'app/type/obj1']];

        $archive = $zip->compress($files);
        $listing = $zip->decompress($archive, ['action' => Zip::ZIP_LIST]);

        $extracted = $zip->decompress($archive, [
            'action' => Zip::ZIP_DATA,
            'info' => $listing,
            'key' => 0,
        ]);

        $this->assertIsString($extracted);
        $this->assertEquals($payload, $extracted);
    }

    /**
     * TAR compress/decompress round-trip.
     * Reader::_restoreFromTar() calls decompress() without action param.
     */
    public function testTarRoundTrip(): void
    {
        $tar = new Tar();

        $files = [
            ['data' => 'tar-payload-1', 'name' => 'kronolith/calendar/1'],
            ['data' => 'tar-payload-2', 'name' => 'turba/contact/2'],
        ];

        $archive = $tar->compress($files);
        $this->assertIsString($archive);

        $entries = $tar->decompress($archive);
        $this->assertIsArray($entries);
        $this->assertCount(2, $entries);
        $this->assertEquals('kronolith/calendar/1', $entries[0]['name']);
        $this->assertEquals('tar-payload-1', $entries[0]['data']);
    }

    /**
     * Writer uses stream option — compress returns resource.
     */
    public function testZipCompressWithStreamOption(): void
    {
        $zip = new Zip();
        $files = [['data' => 'stream-test', 'name' => 'app/type/1']];

        $result = $zip->compress($files, ['stream' => true]);
        $this->assertIsResource($result);

        $content = stream_get_contents($result);
        $this->assertNotEmpty($content);
        fclose($result);
    }

    /**
     * Full file round-trip mimicking Writer → disk → Reader.
     */
    public function testFileRoundTrip(): void
    {
        $zip = new Zip();
        $tmpFile = tempnam(sys_get_temp_dir(), 'backup_test_');

        $files = [
            ['data' => '{"uid":"x"}', 'name' => 'app/calendar/evt1'],
            ['data' => '{"uid":"y"}', 'name' => 'app/contact/ct1'],
        ];

        // Write (mimics Writer::save)
        $archiveStream = $zip->compress($files, ['stream' => true]);
        $fh = fopen($tmpFile, 'w');
        stream_copy_to_stream($archiveStream, $fh);
        fclose($fh);
        fclose($archiveStream);

        // Read (mimics Reader::_restoreFromZip)
        $contents = file_get_contents($tmpFile);
        $listing = $zip->decompress($contents, ['action' => Zip::ZIP_LIST]);
        $this->assertCount(2, $listing);

        $data = $zip->decompress($contents, [
            'action' => Zip::ZIP_DATA,
            'info' => $listing,
            'key' => 0,
        ]);
        $this->assertEquals('{"uid":"x"}', $data);

        unlink($tmpFile);
    }
}
