<?php

namespace Gzai\LaravelBoxAdapter\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\Visibility;
use League\Flysystem\FileAttributes;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use Illuminate\Http\Testing\MimeType;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Gzai\LaravelBoxAdapter\Facades\Box;

class BoxAdapter implements FilesystemAdapter 
{
    public function __construct()
    {
        // 
    }

    public function directoryExists(string $path): bool
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        // check the existance of a folder in Box by folder name
        $result = Box::getFolderExistsByName($pathInfo['last'], $pathInfo['parentId']);

        return $result['success'] ? $result['exists'] : false;
    }

    public function createDirectory(string $path, Config $config): void
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        // create folder in Box
        Box::createFolder($pathInfo['last'], $pathInfo['parentId'], true);
    }

    public function deleteDirectory(string $path): void
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        // get a folder id in Box by folder name
        $folder = Box::getFolderIdByName($pathInfo['last'], $pathInfo['parentId']);

        // set the folderId
        $folderId = $folder['success'] ? $folder['data']['id'] : 0;

        // delete the folder in Box
        Box::deleteFolder($folderId, true);
    }

    public function fileExists(string $path): bool
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        // check the existance of file in Box by file name
        $result = Box::getFileExistsByName($pathInfo['last'], $pathInfo['parentId']);

        return $result['success'] ? $result['exists'] : false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        // set temp
        $tempPath = storage_path('app/box_temp/' . $pathInfo['last']);

        // check the path
        if ( !file_exists(dirname($tempPath)) ) {
            mkdir(dirname($tempPath), 0755, true);
        }

        // save contents to a temp file
        file_put_contents($tempPath, $contents);

        try {
            // upload to Box
            Box::uploadFile($tempPath, $pathInfo['parentId']);
        } finally {
            // always delete the temp file even if upload fails
            if ( file_exists($tempPath) ) {
                @unlink($tempPath);
            }
        }
    }

    public function writeStream(string $path, $resource, Config $config): void
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        // set temp
        $tempPath = storage_path('app/box_temp/' . $pathInfo['last']);

        // check the path
        if ( !file_exists(dirname($tempPath)) ) {
            mkdir(dirname($tempPath), 0755, true);
        }

        // Save stream to a temp file
        $temp = fopen($tempPath, 'w+');
        stream_copy_to_stream($resource, $temp);
        fclose($temp);

        try {
            // upload to Box
            Box::uploadFile($tempPath, $pathInfo['parentId']);
        } finally {
            // always delete the temp file even if upload fails
            if ( file_exists($tempPath) ) {
                @unlink($tempPath);
            }
        }
    }

    public function read(string $path): string
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        $file = Box::getFileIdByName($pathInfo['last'], $pathInfo['parentId']);

        $fileId = $file['success'] ? $file['data']['id'] : 0;

        return (string) Box::readFile($fileId);
    }

    public function readStream(string $path)
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        $file = Box::getFileIdByName($pathInfo['last'], $pathInfo['parentId']);

        $fileId = $file['success'] ? $file['data']['id'] : 0;

        return Box::streamFile($fileId);
    }

    public function delete(string $path): void
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        // get a file id in Box by file name
        $file = Box::getFileIdByName($pathInfo['last'], $pathInfo['parentId']);

        // set the fileId
        $fileId = $file['success'] ? $file['data']['id'] : 0;

        Box::deleteFile($fileId, true);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // no API to set visibility in BOX
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->getFile($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->getFile($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->getFile($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->getFile($path);
    }

    public function getFile(string $path)
    {
        try {
            $result = $this->getFileInfo($path);

            if ( $result['success'] ) {
                return $this->getFileAttributes($result['data']);
            } else {
                return false;
            }
        } catch (\Exception $e) {
            throw new UnableToRetrieveMetadata($e->getMessage());
        }
    }

    public function getFileInfo(string $path)
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        // get a file id in Box by file name
        $result = Box::getFileIdByName($pathInfo['last'], $pathInfo['parentId']);

        $fileId = $result['success'] ? $result['data']['id'] : 0;

        return Box::getFile($fileId);
    }

    public function getFileAttributes($data)
    {
        $detector = new ExtensionMimeTypeDetector();
        $mimeType = $detector->detectMimeTypeFromPath($data['name']);

        return new FileAttributes(
            path: $data['name'],
            fileSize: $data['size'],
            visibility: Visibility::PRIVATE,
            lastModified: strtotime($data['modified_at']),
            mimeType: $mimeType,
            extraMetadata: [],
        );
    }

    public function listContents(string $path, bool $deep): iterable
    {
        // call path info
        $pathInfo = Box::pathInfo($path);

        $folderInfo = Box::getFolderIdByName($pathInfo['last'], $pathInfo['parentId']);

        $items = [];
        $seen = [];

        $this->listContentsBox($folderInfo['data']['id'], $pathInfo['parentId'], $items, $seen, $path, $deep);

        return $items;
    }

    public function listContentsBox(int $folderid, int $parentId, array &$items = [], array &$seen = [], string $path, bool $deep)
    {
        $folderItems = Box::getFolderItems($folderid, $parentId);

        if ( $folderItems['success'] && $folderItems['data']['total_count'] > 0 ) {
            foreach ($folderItems['data']['entries'] as $key => $value) {
                if ( $value['type'] == 'folder' ) {
                    $finalPath = trim($path . '/' . $value['name'], '/');

                    if ( !in_array($finalPath, $seen, true) ) {
                        $items[] = new DirectoryAttributes(
                            $finalPath,
                            Visibility::PRIVATE
                        );

                        $seen[] = $finalPath;
                    }

                    // If recursive listing requested, list deeper
                    if ($deep) {
                        $this->listContentsBox($value['id'], $folderid, $items, $seen, $path . '/' . $value['name'], true);
                    }
                } else 
                if ( $value['type'] == 'file' ) {
                    $finalPath = trim($path . '/' . $value['name']);
                    
                    if ( !in_array($finalPath, $seen)) {
                        $detector = new ExtensionMimeTypeDetector();
                        $mimeType = $detector->detectMimeTypeFromPath($value['name']);

                        $items[] = new FileAttributes(
                            $finalPath,
                            $value['size'] ?? null,
                            Visibility::PRIVATE,
                            isset($value['modified_at']) ? strtotime($value['modified_at']) : null,
                            $mimeType ?? null
                        );

                        $seen[] = $finalPath;
                    }
                }
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        // call source info
        $sourceInfo = Box::pathInfo($source);

        // call destination info
        $destinationInfo = Box::pathInfo($destination);

        $file = Box::getFileIdByName($sourceInfo['last'], $sourceInfo['parentId']);

        $fileId = $file['success'] ? $file['data']['id'] : 0;
        $parentId = $destinationInfo['parentId'];

        $newFileName = '';
        if ( $sourceInfo['last'] != $destinationInfo['last'] ) {
            $newFileName = $destinationInfo['last'];
        }

        Box::moveFile($fileId, $parentId, $newFileName);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        // call source info
        $sourceInfo = Box::pathInfo($source);

        // call destination info
        $destinationInfo = Box::pathInfo($destination);

        $file = Box::getFileIdByName($sourceInfo['last'], $sourceInfo['parentId']);

        $fileId = $file['success'] ? $file['data']['id'] : 0;
        $parentId = $destinationInfo['parentId'];

        $newFileName = '';
        if ( $sourceInfo['last'] != $destinationInfo['last'] ) {
            $newFileName = $destinationInfo['last'];
        }

        Box::copyFile($fileId, $parentId, $newFileName);
    }
    
    public function getUrl(string $path): string
    {
        // Because the Box API doesn’t offer a public-access URL, we use a temporary link instead.

        // call path info
        $pathInfo = Box::pathInfo($path);
        $expires = 60; // seconds

        $file = Box::getFileExactlyByName($pathInfo['last'], $pathInfo['parentId']);

        $fileId = $file['success'] ? $file['data']['id'] : 0;

        $fileDetail = Box::createTemporaryLink($fileId, $expires);

        return $fileDetail['success'] && $fileDetail['data']['shared_link'] ? $fileDetail['data']['shared_link']['url'] : null;
    }
}