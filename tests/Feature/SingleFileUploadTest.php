<?php

namespace Sopamo\LaravelFilepond\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sopamo\LaravelFilepond\Filepond;
use Sopamo\LaravelFilepond\Http\Controllers\FilepondController;
use Sopamo\LaravelFilepond\Tests\TestCase;

class SingleFileUploadTest extends TestCase
{
    /** @test */
    public function test_normal_file_upload()
    {
        $tmpPath = config('filepond.temporary_files_path', 'filepond');
        $diskName = config('filepond.temporary_files_disk', 'local');

        Storage::fake($diskName);

        $response = $this->postJson('/filepond/api/process', [
            'file' => UploadedFile::fake()->create('test.txt', 1),
        ]);

        $response->assertStatus(200);
        $serverId = $response->content();
        $this->assertGreaterThan(50, strlen($serverId));

        /** @var Filepond $filepond */
        $filepond = app(Filepond::class);
        $pathFromServerId = $filepond->getPathFromServerId($serverId);

        $this->assertStringStartsWith($tmpPath, $pathFromServerId, 'tmp file was not created in the temporary_files_path directory');

        Storage::disk($diskName)->assertExists($pathFromServerId);

        /**
         * test removing the file
         */
        $response = $this->deleteJson('/filepond/api/process', [
            $serverId,
        ]);

        $response->assertStatus(200);
        Storage::disk($diskName)->assertMissing($pathFromServerId);
    }

    public function test_garbage_collection()
    {
        //TODO: Implement test_garbage_collection method
        // Configuration for temporary file path and disk name
        $tmpPath = config('filepond.temporary_files_path', 'filepond');
        $diskName = config('filepond.temporary_files_disk', 'local');

        // Create a fake disk for testing
        Storage::fake($diskName);

        // Create two fake files
        $firstFilePath = $tmpPath . '/old_test.txt';
        $secondFilePath = $tmpPath . '/new_test.txt';

        Storage::disk($diskName)->putFile($firstFilePath, UploadedFile::fake()->create('old_test.txt', 1));
        Storage::disk($diskName)->putFile($secondFilePath, UploadedFile::fake()->create('new_test.txt', 1));

//        dd($firstFilePath, $tmpPath);
        // Change created at time of the first file to 5 hours ago
        $firstFileFullPath = storage_path('app/' . $firstFilePath);
//        if (!is_dir(dirname($firstFileFullPath))) {
//            mkdir(dirname($firstFileFullPath), 0777, true);
//        }
//        dd($firstFileFullPath);
        touch($firstFileFullPath, time() - 5 * 60 * 60);

        //  dump file info to check the created time
//        dd(file($firstFileFullPath));

        // Call the garbage collector
        $filepond = app(Filepond::class);
        $filepondController = new FilepondController($filepond);
        $filepondController->doGarbageCollector();

        // Assert that the first file is removed by the garbage collector
        Storage::disk($diskName)->assertMissing($firstFilePath);

        // Assert that the second file still exists
        Storage::disk($diskName)->assertExists($secondFilePath);
    }


}
