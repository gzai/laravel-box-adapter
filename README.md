# Laravel Box Adapter

## Introduction

Laravel Box Adapter is a Laravel package for interacting with the Box API. 

## Features

- Easily access to the Box API for managing files and folders.
- Supports Filament for file upload.

# Register Box developer client

Box API Reference:

https://developer.box.com/reference/

Registering a developer client for the Box API:

https://app.box.com/developers/console/newapp

Steps: 

- Select Custom App
- Step 1 of 2, fill in the app name and purpose input.
- Step 2 of 2, select an authentication method: ``User Authentication (OAuth 2.0)``
- After create custom app, go to ``Configuration`` tab in your platform app.
- Copy ``Client ID`` & ``Client Secret`` in ``OAuth 2.0 Credentials`` section to your ``.env`` file.
- Add redirect URI for your callback url in ``OAuth 2.0 Redirect URIs`` section, you can add your callback localhost url.

# Requirements

- PHP: ``^8.2``
- Laravel: ``^11.0``
- League flysystem: ``^3.0``
- Ramsey uuid: ``^4.0``

This package can also be used with: 

- Filament: ``^3.0|^4.0``

# Installation

Require this package with [Composer](https://getcomposer.org), in the root directory of your project.

```bash
composer require gzai/laravel-box-adapter
```

## Configuration

To publish the config, run the vendor publish command:

```bash
php artisan vendor:publish --provider="Gzai\LaravelBoxAdapter\BoxAdapterServiceProvider" --tag=laravel-box-adapter-config
```

This will create a new file named ``config/box.php``

The ``config/box.php`` config file contains:

```php
return [
    'client_id' => env('BOX_CLIENT_ID'),
    'client_secret' => env('BOX_CLIENT_SECRET'),
    'redirect' => env('BOX_REDIRECT_URI'),
    'authorize_url' => 'https://account.box.com/api/oauth2/authorize',
    'token_url' => 'https://api.box.com/oauth2/token',
    'api_url' => 'https://api.box.com/2.0',
    'upload_url' => 'https://upload.box.com/api/2.0',
    'routes_enabled' => true,
    'user_enabled' => true,
    'redirect_callback' => false,
    'redirect_callback_url' => '',
    'download_folder' => 'box_download',
    'folder' => [
        'parent' => 0,
    ],
];
```

Breaking it down:

- ``routes_enabled`` to accommodate Authentication Box and get Callback from Box. Set it to ``false`` if you want to create your own logic.

- ``user_enabled`` is indicates whether the ``token`` belongs to a specific user or can be used globally. 
Set it to ``true`` for a user-specific token, or ``false`` for a globally usable token.

- ``redirect_callback`` to accommodate redirect after the API callback Box, set it to ``true`` for redirect to a specific url in ``redirect_callback_url``.

- ``redirect_callback_url`` a specific url when you want to redirect after login to Box.

- ``download_folder`` a specific folder when you want to download a file to your project path. 

- ``folder`` add folders ID of your Box folder. The parent folder is used for the root folder.

## Migration

To publish the migration, run the vendor publish command:

```bash
php artisan vendor:publish --provider="Gzai\LaravelBoxAdapter\BoxAdapterServiceProvider" --tag=laravel-box-adapter-migrations
```

This will create a new file named ``database/migrations/xxxx_xx_xx_xxxxxx_create_box_tokens_table.php``

Run the migration:

```bash
php artisan migrate
```

## .ENV Configuration

Add the environment variables to your ``.env`` file:

```php
BOX_CLIENT_ID=[Client ID]
BOX_CLIENT_SECRET=[Client Secret]
BOX_REDIRECT_URI=http://localhost:9000/box/callback
```

Please adjust with your client id, client secret and redirect url.

# Usage

If your config box ``routes_enabled`` is ``true``, you will get 3 additional routes: 

```bash
$ php artisan route:list
```

Result:

```
GET|HEAD 	box/login
GET|HEAD 	box/callback
GET|HEAD 	box/me
```

You can access Http://localhost:9000/box/login to login with your box account credential, and then you will be redirected to Http://localhost:9000/box/callback.



## Folder Examples

Add Box service

```php
use Gzai\LaravelBoxAdapter\Facades\Box;
```

### Get Folder Information

```php
Route::get('box/folder/get', function() {
	$folderId = 000000000000; // you can change to specific folder id box

	// get folder information in box
	return Box::getFolder($folderId);
});
```

### Check Folder Exists

```php
Route::get('box/folder/exists', function() {
	$folderId = 000000000000; // you can change to specific folder id box

	// get status folder exists in box
	return Box::folderExists($folderId);
});
```

### Create Folder

```php
Route::get('box/folder/create', function() {
	$folderName = 'new_folder';
	$folderId = config('box.folder.parent'); // you can change to specific folder id box

	// create folder in box
	return Box::createFolder($folderName, $folderId);
});
```

### Delete Folder

```php
Route::get('box/folder/delete', function() {
	$folderId = 000000000000; // you can change to specific folder id box

	// delete folder in box
	return Box::deleteFolder($folderId);
});
```

### Get Folder Items

```php
Route::get('box/folder/items', function() {
	$folderId = 000000000000; // you can change to specific folder id box

	// get folder items in box
	return Box::getFolderItems($folderId);
});
```

### Search Folders By Folder Name

```php
Route::get('box/folder/search', function() {
	$folderName = 'new_folder';
	$folderParentId = config('box.folder.parent'); // you can change to specific folder id box

	// note:
	// Box may take some time to complete its indexing process for a new folder. 
	// During this time, the folder you’re searching for through the Search API might not be found until the indexing is finished.

	// search folders by folder name in box
	return Box::getFoldersByName($folderName, $folderParentId);
});
```

### Search & Get Specific Folder ID By Folder Name

```php
Route::get('box/folder/search/id', function() {
	$folderName = 'new_folder';
	$folderParentId = config('box.folder.parent'); // you can change to specific folder id box

	// note:
	// Box may take some time to complete its indexing process for a new folder. 
	// During this time, the folder you’re searching for through the Search API might not be found until the indexing is finished.

	// search specific folder and get the folder id by folder name in box
	return Box::getFolderIdByName($folderName, $folderParentId);
});
```

### Search & Check Folder Exists By Folder Name

```php
Route::get('box/folder/search/exists', function() {
	$folderName = 'new_folder';
	$folderParentId = config('box.folder.parent'); // you can change to specific folder id box

	// note:
	// Box may take some time to complete its indexing process for a new folder. 
	// During this time, the folder you’re searching for through the Search API might not be found until the indexing is finished.

	// search specific folder and get status folder exists by folder name in box
	return Box::getFolderExistsByName($folderName, $folderParentId);
});
```

### Search & Get Specific Folder By Folder Name

```php
Route::get('box/folder/search/exactly', function() {
	$folderName = 'new_folder';
	$folderParentId = config('box.folder.parent'); // you can change to specific folder id box

	// note:
	// Box may take some time to complete its indexing process for a new folder. 
	// During this time, the folder you’re searching for through the Search API might not be found until the indexing is finished.

	// search specific folder and get status folder exists by folder name in box
	return Box::getFolderExactlyByName($folderName, $folderParentId);
});
```

Box Search Indexing Reference:

https://developer.box.com/guides/search/indexing/


## File Examples

```php
use Gzai\LaravelBoxAdapter\Facades\Box;
```

### Get File Information

```php
Route::get('box/file/get', function() {
	$fileId = 000000000000; // you can change to specific file id box

	// get file information in box
	return Box::getFile($fileId);
});
```

### Check File Exists

```php
Route::get('box/file/exists', function() {
	$fileId = 000000000000; // you can change to specific file id box

	// get status file exists in box
	return Box::fileExists($fileId);
});
```

### Move File

```php
Route::get('box/file/move', function() {
	$fileId = 000000000000; // you can change to specific file id box
	$parentId = 000000000000; // you can change to specific folder id box

	// move file in box
	return Box::moveFile($fileId, $parentId);

	// or 

	$newName = 'new_file_name.jpg'; // you can change to new file name with original file extension

	return Box::moveFile($fileId, $parentId, $newName);
});
```

### Copy File

```php
Route::get('box/file/copy', function() {
	$fileId = 000000000000; // you can change to specific file id box
	$parentId = 000000000000; // you can change to specific folder id box

	// copy file in box
	return Box::copyFile($fileId, $parentId);

	// or 

	$newName = 'new_file_name.jpg'; // you can change to new file name with original file extension

	return Box::copyFile($fileId, $parentId, $newName);
});
```

### Delete File

```php
Route::get('box/file/delete', function() {
	$fileId = 000000000000; // you can change to specific file id box

	// delete file in box
	return Box::deleteFile($fileId);
});
```

### Create Temporary Link

```php
Route::get('box/file/temporary_url', function() {
	$fileId = 000000000000; // you can change to specific file id box
	$timeSeconds = 30;

	// create temporary link file in box
	return Box::createTemporaryLink($fileId, $timeSeconds);
});
```

### Search Files By File Name

```php
Route::get('box/file/search', function() {
	$fileName = '0199B8F2D13E6930B317C121AB41C.pdf'; 
	$folderParentId = config('box.folder.parent'); // you can change to specific folder id box

	// note:
	// Box may take some time to complete its indexing process for a new file. 
	// During this time, the file you’re searching for through the Search API might not be found until the indexing is finished.

	// search files by name in box
	return Box::getFilesByName($fileName, $folderParentId);
});
```

### Search & Get Specific File ID By File Name

```php
Route::get('box/file/search/id', function() {
	$fileName = '0199B8F2D13E6930B317C121AB41C.pdf'; 
	$folderParentId = config('box.folder.parent'); // you can change to specific folder id box

	// note:
	// Box may take some time to complete its indexing process for a new file. 
	// During this time, the file you’re searching for through the Search API might not be found until the indexing is finished.

	// seach specific file and get the file id by name in box
	return Box::getFileIdByName($fileName, $folderParentId);
});
```

### Search & Check File Exists By File Name

```php
Route::get('box/file/search/exists', function() {
	$fileName = '0199B8F2D13E6930B317C121AB41C.pdf'; 
	$folderParentId = config('box.folder.parent'); // you can change to specific folder id box

	// note:
	// Box may take some time to complete its indexing process for a new file. 
	// During this time, the file you’re searching for through the Search API might not be found until the indexing is finished.

	// seach specific file and get status file exists by name in box
	return Box::getFileExistsByName($fileName, $folderParentId);
});
```

### Search & Get Specific File Information By File Name

```php
Route::get('box/file/search/exactly', function() {
	$fileName = '0199B8F2D13E6930B317C121AB41C.pdf'; 
	$folderParentId = config('box.folder.parent'); // you can change to specific folder id box

	// note:
	// Box may take some time to complete its indexing process for a new file. 
	// During this time, the file you’re searching for through the Search API might not be found until the indexing is finished.

	// seach specific file and get specific file by name in box
	return Box::getFileExactlyByName($fileName, $folderParentId);
});
```

### Upload File

```php
Route::get('box/file/upload', function() {
	$file = 'app/public/logo.png';
	$filePath = storage_path($file);
	$folderParentId = 000000000000; // you can change to specific file id box

	return Box::uploadFile($filePath, $folderParentId);
});

```

### Force Download File

```php
Route::get('box/file/download/force', function() {
	$fileId = 000000000000; // you can change to specific file id box

	// download file in box
	return Box::downloadFile($fileId);
});
```

### Save Download File In Directory

```php
Route::get('box/file/download/directory', function() {
	$fileId = 000000000000; // you can change to specific file id box

	// download file in box
	return Box::downloadFile($fileId);
});
```

## Box Filesystem

Box Adapter can also be used in Laravel Storage

### Check Directory Exists

```php
$path = 'folder_a';

return Storage::disk('box')->directoryExists($path);
```

### Get Files In Root Folder

```php
$path = 'folder_a';

return Storage::disk('box')->files($path);
```

### Get Files In Root Folder & Under SubFolder

```php
$path = 'folder_a';

return Storage::disk('box')->allFiles($path);
```

### Get Folder In Root Folder

```php
$path = 'folder_a';

return Storage::disk('box')->directories($path);
```

### Get Folder In Root Folder & Under SubFolder

```php
$path = 'folder_a';

return Storage::disk('box')->allDirectories($path);
```

### Create Folder

```php
$path = 'test_folder';

return Storage::disk('box')->makeDirectory($path);
```

### Delete Folder

```php
$path = 'test_folder';

return Storage::disk('box')->deleteDirectory($path);
```

### Check File Exists

```php
$path = 'folder_a/01K5B9BGPGDMR5KCS883NM39K3.png';

return Storage::disk('box')->exists($path);
```

### Get File URL

```php
$path = 'folder_c/0199F048051772AD8AF7154E2A33D23A.jpg';

return Storage::disk('box')->url($path);
```

### Create Temporary URL File

```php
$path = 'folder_c/0199F048051772AD8AF7154E2A33D23A.jpg';
$expiration = now()->addMinutes(5);

return Storage::disk('box')->temporaryUrl($path, $expiration);
```

### Move File

```php
$path_a = 'folder_c/0199F048051772AD8AF7154E2A33D23A.jpg';
$path_b = 'folder_b/0199F048051772AD8AF7154E2A33D23A.jpg';

return Storage::disk('box')->move($path_a, $path_b);
```

### Copy File

```php
$path_a = 'folder_b/0199F048051772AD8AF7154E2A33D23A.jpg';
$path_b = 'folder_c/0199F048051772AD8AF7154E2A33D23A.jpg';

return Storage::disk('box')->copy($path_a, $path_b);
```

### Upload File

```php
$fileName = Box::fileName() . '.jpg';
$path = 'folder_c/'. $fileName;

$content = Storage::get('download/0199BDC0C7C9729B8C7850A96CDEAE21.jpg');
Storage::disk('box')->put($path, $content);

// or

$resource = fopen(storage_path('app/download/0199BDC0C7C9729B8C7850A96CDEAE21.jpg'), 'r');
Storage::disk('box')->put($path, $resource);
```

### Delete File

```php
$path = 'folder_c/0199F0453FCC72DAA0A19FBD63C2B520.jpg';

return Storage::disk('box')->delete($path);
```

### Get File Information

```php
$path = 'folder_c/0199F048051772AD8AF7154E2A33D23A.jpg';

return Storage::disk('box')->get($path);
```

### Download File

```php
$path = 'folder_c/0199F048051772AD8AF7154E2A33D23A.jpg';
$pathExplode = explode('/', $path);
$fileName = last($pathExplode);

return Storage::disk('box')->download($path, $fileName);
```

## Filament FileUpload

If you are using Laravel Filament, you can easily integrate the Laravel Box Adapter with the ``FileUpload`` component.

You can add ``disk('box')`` and use ``fileUploadBox()`` method in your Filament FileUpload.

Because Box doesn’t have a public asset URL, we set ``previewable(false)`` method in ``fileUploadBox()`` to disable file preview.

```php
Forms\Components\FileUpload::make('attachment')
    ->disk('box')
    ->directory('folder')
    ->required()
    ->fileUploadBox(),
```

When a file is successfully uploaded to Box, you will receive the ``file ID`` and ``folder ID`` Box data in ``session``.

You can store these IDs before saving your data to the database.


```php

protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['parent_id'] = session()->has('parentIdBox') && !empty(session('parentIdBox')) ? session('parentIdBox') : null;
    $data['file_id'] = session()->has('fileIdBox') && !empty(session('fileIdBox')) ? session('fileIdBox') : null;

    return $data;
}
```

or you can update these IDs before saving your data to the database.

```php
protected function handleRecordUpdate(Model $record, array $data): Model
{
	$data['parent_id'] = session()->has('parentIdBox') && !empty(session('parentIdBox')) ? session('parentIdBox') : null;
    $data['file_id'] = session()->has('fileIdBox') && !empty(session('fileIdBox')) ? session('fileIdBox') : null;

    return $data;
}
```

Since Filament v4 has a different implementation of ``saveUploadedFileUsing()`` compared to Filament v3, you can pass the ``file ID`` and ``folder ID`` Box data using the Laravel ``session``.

# Change Logs

Please see the [changelog](CHANGELOG)

# Contributing

Contributing are welcome.