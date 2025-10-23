<?php

namespace Gzai\LaravelBoxAdapter\Filament;

use Filament\Forms\Components\FileUpload;
use Gzai\LaravelBoxAdapter\Facades\Box;
use Gzai\LaravelBoxAdapter\Filesystem\BoxAdapter;

use Storage;

class BoxUploadMacro
{
    public static function register(): void
    {
        FileUpload::macro('fileUploadBox', function () {
            // Since Box doesn't have a public asset link, we set previewable option to false, and getUploadedFileUsing function is commented out.
            return $this
                ->previewable(false)
                ->getUploadedFileNameForStorageUsing(function ($file, $state, $livewire, $set, $component) {
                    return Box::fileName() . '.' . $file->getClientOriginalExtension();
                })
                ->getUploadedFileUsing(function ($file, $livewire, $component) {
                    $pathInfo = Box::pathInfo($file);

                    $fileBox = Box::getFileIdByName($pathInfo['last'], $pathInfo['parentId']);

                    $fileId = $fileBox['success'] ? $fileBox['data']['id'] : 0;

                    $fileUrl = Box::getFile($fileId);

                    $urlBox = Box::createTemporaryLink($fileId, 10 * 60);

                    $temporaryLink = $urlBox['success'] ? $urlBox['data']['shared_link']['url'] : '';
                    
                    $boxAdapter = new BoxAdapter();
                    $fileInfo = $boxAdapter->getFile($file);

                    return [
                        'url' => $temporaryLink,
                        'name' => basename($file),
                        'size' => $fileInfo->fileSize(),
                        'type' => $fileInfo->mimeType(),
                    ];
                })
                ->saveUploadedFileUsing(function ($file, $state, $livewire, $set, $component) {
                    $directory = $component->getDirectory();
                    $fileName = $component->getUploadedFileNameForStorage($file);
                    $path = trim($directory . '/' . $fileName, '/');

                    $parent = Box::getFolderIdByName($directory);

                    $parentId = $parent['success'] ? $parent['data']['id'] : 0;

                    $result = Box::uploadFile($file->getPathname(), $parentId, $fileName);
                    
                    if ( $result['success'] ) {
                        session(['parentIdBox' => $result['data']['entries'][0]['parent']['id']]);
                        session(['fileIdBox' => $result['data']['entries'][0]['id']]);
                    }

                    return $path;
                })
                ->deleteUploadedFileUsing(function ($file, $record, $livewire, $component) {
                    // return Storage::disk($component->getDiskName())->delete($file);
                    return true;
                });
        });

        FileUpload::macro('disk', function ($disk) {
            $this->setDisk($disk);

            if ( $disk === 'box' ) {
                $this->fileUploadBox();
            }

            return $this;
        });
    }
}