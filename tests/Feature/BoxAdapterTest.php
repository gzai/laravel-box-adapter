<?php

namespace Gzai\LaravelBoxAdapter\Tests\Feature;

use Gzai\LaravelBoxAdapter\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Gzai\LaravelBoxAdapter\BoxAdapterServiceProvider;
use Gzai\LaravelBoxAdapter\Facades\Box;
use Gzai\LaravelBoxAdapter\Filesystem\BoxAdapter;
use Illuminate\Support\Facades\DB;

class BoxAdapterTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            BoxAdapterServiceProvider::class
        ];
    }

    public function test_check_token()
    {
        $token = Box::getAccessToken();

        $this->assertNotNull($token, 'Error token');

        echo "\n";
        echo "Check token Box ✅";
    }

    public function test_result_table()
    {
        $rows = DB::table('box_tokens')->get();
        // echo "\n";
        // dump($rows->toArray());

        $this->assertNotEmpty($rows, 'No result in box_tokens table.');
        
        echo "\n";
        echo "Get box_tokens rows ✅";
    }

    public function test_user_box()
    {
        $user = Box::getUser();
        // echo "\n";
        // dump($user);

        $this->assertIsArray($user);

        echo "\n";
        echo "Get User Box ✅";
    }

    // public function test_create_folder_box()
    // {
    //     $folderName = 'test_folder';

    //     $folder = Box::createFolder($folderName, env('BOX_FOLDER_PARENT_ID'));
    //     // echo "\n";
    //     // dump($folder);

    //     $this->assertIsArray($folder);

    //     echo "\n";
    //     echo "Create Folder Box ✅";
    // }

    public function test_get_folder_box()
    {
        $path = 'test_folder';

        $folderId = $this->getFolderId($path);

        $folderInfo = Box::getFolder($folderId);
        // echo "\n";
        // dump($folderInfo);

        $this->assertIsArray($folderInfo);

        echo "\n";
        echo "Get Folder Box ✅";
    }

    // public function test_delete_folder_box()
    // {
    //     $path = 'test_folder';

    //     $folderId = $this->getFolderId($path);

    //     Box::deleteFolder($folderId);

    //     $this->assertIsTrue(true);

    //     echo "\n";
    //     echo "Delete Folder Box ✅";
    // }

    public function test_upload_file_box()
    {
        $filePath = __DIR__ . '/../../resources/assets/files/sample.jpg';

        $path = 'test_folder';
        $parentId = $this->getFolderId($path);

        $uploadFile = Box::uploadFile($filePath, $parentId);
        // echo "\n";
        // dump($uploadFile);

        $this->assertIsArray($uploadFile);

        echo "\n";
        echo "Upload File Box ✅";
    }

    public function pathInfo($path)
    {
        return Box::pathInfo($path);
    }

    public function getFolderId($path)
    {
        $pathInfo = Box::pathInfo($path);     

        $folder = Box::getFolderIdByName($pathInfo['last'], env('BOX_FOLDER_PARENT_ID'));

        return $folder['success'] ? $folder['data']['id'] : 0;
    }
}