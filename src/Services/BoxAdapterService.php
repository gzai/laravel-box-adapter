<?php

namespace Gzai\LaravelBoxAdapter\Services;

use League\Flysystem\Config;
use Illuminate\Http\Client\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Gzai\LaravelBoxAdapter\Models\BoxToken;

use Auth;

class BoxAdapterService
{
    protected $http;
    protected $config;

    public function __construct()
    {
        $this->http = new Client([
            'http_errors' => false,
        ]);

        $this->config = config('box');
    }

    public function fileName()
    {
        return (string) strtoupper(str_replace(['-'], [''], Uuid::uuid7()));
    }

    public function getAuthorizationUrl()
    {
        return $this->config['authorize_url'] . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect'],
        ]);
    }

    public function getTokenFromCode(string $code)
    {
        $res = $this->http->post($this->config['token_url'], [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
            ],
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        $this->storeToken($body);

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    public function refreshToken()
    {
        $token = $this->getBoxToken();

        if ($token == null) {
            throw new \RuntimeException(sprintf('Failed to get token (status %d): %s', 400, 'No Box token found!'));
        }

        $res = $this->http->post($this->config['token_url'], [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
            ],
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        $this->updateToken($token, $body);

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    public function getAccessToken()
    {
        $token = $this->getBoxToken();

        if ($token == null) {
            throw new \RuntimeException(sprintf('Failed to get token (status %d): %s', 400, 'No Box token found!'));
        }

        if ($token->expires_at->isPast()) {
            $refresh = $this->refreshToken();

            return $refresh['data']['access_token'];
        }

        return $token->access_token;
    }

    public function getBoxToken()
    {
        return BoxToken::query()
                    ->when(config('box.user_enabled') && Auth::check(), function($query) {
                        $query->where('user_id', Auth::id());
                    })
                    ->when(!config('box.user_enabled'), function($query) {
                        $query->whereNull('user_id');
                    })
                    ->latest()
                    ->first();
    }

    public function showError(int $status, array $body)
    {
        if ( $status >= 400 ) {
            $error = 'unknown_error';
            $message = 'Box API error';
            
            if ( isset($body['error']) ) {
                $error = $body['error'];
            } else 
            if ( isset($body['code']) ) {
                $error = $body['code'];
            }

            if ( isset($body['error_description']) ) {
                $message = $body['error_description'];
            } else 
            if ( isset($body['message']) ) {
                $message = $body['message'];
            }

            return [
                'success' => false,
                'error' => [
                    'code' => $error,
                    'message' => $message,
                    'status' => $status,
                ]
            ];
        }

        return;
    }

    public function showErrorException(int $status, $body)
    {
        if ($status >= 400) {
            $errorBody = (string) $body;

            $errorMessage = 'Unknown error';
            if (!empty($errorBody)) {
                $decoded = json_decode($errorBody, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
                    $errorMessage = $decoded['message'];
                } else if (json_last_error() === JSON_ERROR_NONE) {
                    $errorMessage = json_encode($decoded);
                } else {
                    $errorMessage = $errorBody;
                }
            }

            throw new \RuntimeException(sprintf('Failed to get file (status %d): %s', $status, $errorMessage));
        }
    }

    public function storeToken(array $data)
    {
        $boxToken = $this->getBoxToken();

        if ($boxToken) {
            $this->updateToken($boxToken, $data);
        } else {
            return BoxToken::Create([
                'user_id' => ( config('box.user_enabled') && Auth::check() ) ? Auth::id() : null,
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_in' => $data['expires_in'],
                'expires_at'    => Carbon::now()->addSeconds($data['expires_in']),
            ]);
        }
    }

    public function updateToken(BoxToken $token, array $data)
    {
        return $token->update([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => $data['expires_in'],
            'expires_at'    => Carbon::now()->addSeconds($data['expires_in']),
        ]);
    }

    private function headers(array $extra = [])
    {
        return array_merge([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
        ], $extra);
    }

    public function getUser()
    {
        $res = $this->http->get($this->config['api_url'] . '/users/me', [
            'headers' => $this->headers()
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    public function pathInfo($path)
    {
        // explode the path
        $_path = explode('/', $path);
        $last = end($_path);
        $prev = prev($_path);

        // set parentId from config box
        $parentId = $this->config['folder']['parent'];

        // Get the parentId if there is a folder in the path
        if ( $prev ) {
            $parent = $this->getFolderIdByName($prev, $parentId);

            // set parentId
            if ($parent['success']) {
                $parentId = $parent['data']['id'];
            }
        }

        return [
            'last' => $last,
            'prev' => $prev,
            'parentId' => $parentId,
        ];
    }

    public function getFolder(int $folderId = 0)
    {
        $res = $this->http->get($this->config['api_url'] . "/folders/{$folderId}", [
            'headers' => $this->headers(),
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    public function folderExists(int $folderId = 0)
    {
        $res = $this->http->get($this->config['api_url'] . "/folders/{$folderId}", [
            'headers' => $this->headers(),
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        return [
            'success' => true,
            'exists' => ( $body ) ? true : false,
        ];
    }

    public function createFolder(string $folderName = '', int $parentId = 0, bool $statusVoid = false)
    {
        $res = $this->http->post($this->config['api_url'] . "/folders", [
            'headers' => $this->headers(),
            'json' => [
                'name' => $folderName,
                'parent' => [
                    'id' => $parentId,
                ]
            ]
        ]);

        $status = $res->getStatusCode();
        
        if ( $statusVoid ) {
            $this->showErrorException($status, $res->getBody());

            return true;
        } else {
            $body = json_decode($res->getBody(), true);

            if (is_array($body) && $err = $this->showError($status, $body)) {
                return $err;
            }

            return [
                'success' => true,
                'data' => $body,
            ];
        }
    }

    public function deleteFolder(int $folderId, bool $statusVoid = false)
    {
        if ( $folderId == 0 && $statusVoid ) {
            $status = 404;
            $errorMessage = 'Folder is not found!';
            throw new \RuntimeException(sprintf('Failed to delete folder (status %d): %s', $status, $errorMessage));
        }

        $res = $this->http->delete($this->config['api_url'] . "/folders/{$folderId}", [
            'headers' => $this->headers(),
        ]);

        $status = $res->getStatusCode();

        if ( $statusVoid ) {
            $this->showErrorException($status, $res->getBody());

            return true;
        } else {
            $body = json_decode($res->getBody()->getContents(), true);

            if (is_array($body) && $err = $this->showError($status, $body)) {
                return $err;
            }

            return [
                'success' => true,
                'data' => [
                    'message' => 'Successfully deleted the folder!',
                ],
            ];
        }
    }

    public function getFolderItems(int $folderId = 0)
    {
        $res = $this->http->get($this->config['api_url'] . "/folders/{$folderId}/items", [
            'headers' => $this->headers(),
            'query' => [
                'fields' => 'id,type,name,file_version,size,owned_by,created_by,created_at,modified_by,modified_at,parent'
            ],
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    public function getFoldersByName(string $folderName, int $parentId = 0)
    {
        $parentId = $parentId == 0 ? $this->config['folder']['parent'] : $parentId;

        $res = $this->http->get($this->config['api_url'] . "/search", [
            'headers' => $this->headers(),
            'query' => [
                'type' => 'folder',
                'ancestor_folder_ids' => $parentId,
                'content_types' => 'name,description',
                'query' => '"' . $folderName . '"',
            ]
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    public function getFolderIdByName(string $folderName, int $parentId = 0)
    {
        $result = $this->getFoldersByName($folderName, $parentId);

        $folderId = 0;
        if ( $result['success'] && $result['data']['total_count'] > 0) {
            foreach ($result['data']['entries'] as $key => $value) {
                if ( $folderName == $value['name'] ) {
                    $folderId = $value['id'];
                    break;
                }
            }
        }

        return [
            'success' => true,
            'data' => [
                'id' => $folderId,
            ],
        ];
    }

    public function getFolderExistsByName(string $folderName, int $parentId = 0)
    {
        $result = $this->getFoldersByName($folderName, $parentId);

        $exists = false;
        if ( $result['success'] && $result['data']['total_count'] > 0) {
            foreach ($result['data']['entries'] as $key => $value) {
                if ( $folderName == $value['name'] ) {
                    $exists = true;
                    break;
                }
            }
        }

        return [
            'success' => true,
            'exists' => $exists,
        ];
    }

    public function getFolderExactlyByName(string $folderName, int $parentId = 0)
    {
        $result = $this->getFoldersByName($folderName, $parentId);

        $folder = [];
        if ( $result['success'] && $result['data']['total_count'] > 0) {
            foreach ($result['data']['entries'] as $key => $value) {
                if ( $folderName == $value['name'] ) {
                    $folder = $value;
                    break;
                }
            }
        }

        return [
            'success' => true,
            'data' => $folder,
        ];
    }

    public function getFile(int $fileId = 0)
    {
        $res = $this->http->get($this->config['api_url'] . "/files/{$fileId}", [
            'headers' => $this->headers(),
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    public function fileExists(int $fileId = 0)
    {
        $res = $this->http->get($this->config['api_url'] . "/files/{$fileId}", [
            'headers' => $this->headers(),
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        return [
            'success' => true,
            'exists' => ( $body ) ? true : false,
        ];
    }

    public function renameFile(int $fileId = 0, string $newName = '', bool $statusVoid = false)
    {
        $res = $this->http->put($this->config['api_url'] . "/files/{$fileId}", [
            'headers' => $this->headers(),
            'json' => [
                'name' => $newName,
            ]
        ]);

        $status = $res->getStatusCode();

        if ( $statusVoid ) {
            $this->showErrorException($status, $res->getBody());

            return true;
        } else {
            $body = json_decode($res->getBody(), true);

            if (is_array($body) && $err = $this->showError($status, $body)) {
                return $err;
            }

            return [
                'success' => true,
                'data' => $body,
            ];
        }
    }

    public function copyFile(int $fileId = 0, int $parentId = 0, string $newName = '', bool $statusVoid = false)
    {
        $res = $this->http->post($this->config['api_url'] . "/files/{$fileId}/copy", [
            'headers' => $this->headers(),
            'json' => [
                'parent' => [
                    'id' => $parentId
                ]
            ]
        ]);

        $status = $res->getStatusCode();

        if ( $statusVoid ) {
            $this->showErrorException($status, $res->getBody());

            return true;
        } else {
            $body = json_decode($res->getBody(), true);

            if (is_array($body) && $err = $this->showError($status, $body)) {
                return $err;
            }

            if ( $newName != '' ) {
                $this->renameFile($body['id'], $newName, $statusVoid);
            }

            return [
                'success' => true,
                'data' => $body,
            ];
        }
    }

    public function moveFile(int $fileId = 0, int $parentId = 0, string $newName = '', bool $statusVoid = false)
    {
        $json = [
            'parent' => [
                'id' => $parentId
            ]
        ];

        if ( $newName != '' ) {
            $json['name'] = $newName;
        }

        $res = $this->http->put($this->config['api_url'] . "/files/{$fileId}", [
            'headers' => $this->headers(),
            'json' => $json
        ]);

        $status = $res->getStatusCode();

        if ( $statusVoid ) {
            $this->showErrorException($status, $res->getBody());

            return true;
        } else {
            $body = json_decode($res->getBody(), true);

            if (is_array($body) && $err = $this->showError($status, $body)) {
                return $err;
            }

            return [
                'success' => true,
                'data' => $body,
            ];
        }
    }

    public function deleteFile(int $fileId = 0, bool $statusVoid = false)
    {
        if ( $fileId == 0 && $statusVoid ) {
            $status = 404;
            $errorMessage = 'File is not found!';
            throw new \RuntimeException(sprintf('Failed to delete file (status %d): %s', $status, $errorMessage));
        }

        $res = $this->http->delete($this->config['api_url'] . "/files/{$fileId}", [
            'headers' => $this->headers(),
        ]);

        $status = $res->getStatusCode();

        if ( $statusVoid ) {
            $this->showErrorException($status, $res->getBody());

            return true;
        } else {
            $body = json_decode($res->getBody()->getContents(), true);

            if (is_array($body) && $err = $this->showError($status, $body)) {
                return $err;
            }

            return [
                'success' => true,
                'data' => [
                    'message' => 'Successfully deleted the file!',
                ],
            ];
        }
    }

    public function getFilesByName(string $filePath, int $parentId = 0)
    {
        $fileName = basename($filePath);
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        $parentId = $parentId == 0 ? $this->config['folder']['parent'] : $parentId;

        $res = $this->http->get($this->config['api_url'] . "/search", [
            'headers' => $this->headers(),
            'query' => [
                'type' => 'file',
                'ancestor_folder_ids' => $parentId,
                'content_types' => 'name',
                'file_extensions' => $fileExtension,
                'query' => '"' . $fileName . '"',
            ]
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    public function getFileIdByName(string $filePath, int $parentId = 0)
    {
        $result = $this->getFilesByName($filePath, $parentId);

        $fileId = 0;
        if ( $result['success'] && $result['data']['total_count'] > 0 ) {
            foreach ($result['data']['entries'] as $key => $value) {
                if ( $filePath == $value['name'] ) {
                    $fileId = $value['id'];
                    break;
                }
            }
        }

        return [
            'success' => true,
            'data' => [
                'id' => $fileId,
            ],
        ];
    }

    public function getFileExistsByName(string $filePath, int $parentId = 0)
    {
        $result = $this->getFilesByName($filePath, $parentId);

        $exists = false;
        if ( $result['success'] && $result['data']['total_count'] > 0 ) {
            foreach ($result['data']['entries'] as $key => $value) {
                if ( $filePath == $value['name'] ) {
                    $exists = true;
                    break;
                }
            }
        }

        return [
            'success' => true,
            'exists' => $exists,
        ];
    }

    public function getFileExactlyByName(string $filePath, int $parentId = 0)
    {
        $result = $this->getFilesByName($filePath, $parentId);

        $file = [];
        if ( $result['success'] && $result['data']['total_count'] > 0 ) {
            foreach ($result['data']['entries'] as $key => $value) {
                if ( $filePath == $value['name'] ) {
                    $file = $value;
                    break;
                }
            }
        }

        return [
            'success' => true,
            'data' => $file,
        ];
    }

    public function readFile(int $fileId = 0)
    {
        $res = $this->http->get($this->config['api_url'] . "/files/{$fileId}/content", [
            'headers' => $this->headers(),
            'stream' => true,
        ]);

        $status = $res->getStatusCode();

        $this->showErrorException($status, $res->getBody());

        return $res->getBody();
    }

    public function streamFile(int $fileId = 0)
    {
        $body = $this->readFile($fileId);

        $temp = fopen('php://temp', 'w+');
        if (!is_resource($temp)) {
            throw new \RuntimeException('Failed to create temp stream');
        }

        while (!$body->eof()) {
            fwrite($temp, $body->read(1024 * 8)); // read in 8KB chunks
        }

        rewind($temp);
        return $temp;
    }

    public function downloadFile(int $fileId = 0,bool $saveFile = false, string $saveDir = '')
    {
        $res = $this->http->get($this->config['api_url'] . "/files/{$fileId}/content", [
            'headers' => $this->headers(),
            'allow_redirects' => false,
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        $downloadUrl = $res->getHeaderLine('Location');
        
        if ( !$saveFile ) {
            // download file to browser
            return redirect()->away($downloadUrl);
        } else {
            // download file to storage folder
            $res = $this->http->get($downloadUrl, ['stream' => true]);
            $stream = Utils::streamFor($res->getBody());

            $info = $this->getFile($fileId);

            $fileName = $info['data']['name'] ?? ($fileId . '.tmp');
            $download_folder = $saveDir != '' ? $saveDir : config('box.download_folder');

            $savePath ??= storage_path("app/{$download_folder}/{$fileName}");

            if (!is_dir(dirname($savePath))) {
                mkdir(dirname($savePath), 0755, true);
            }

            $resource = fopen($savePath, 'w+b'); // binary safe

            while (!$stream->eof()) {
                fwrite($resource, $stream->read(1024 * 1024)); // 1 MB chunks
            }
            fclose($resource);

            return [
                'success' => true,
                'data' => [
                    'name' => $fileName,
                    'save_path' => $savePath,
                ],
            ];
        }
    }

    public function createTemporaryLink(int $fileId = 0, int $seconds = 60)
    {
        $expiresAt = Carbon::now()->addSeconds($seconds)->toIso8601String();

        $res = $this->http->put($this->config['api_url'] . "/files/{$fileId}", [
            'headers' => $this->headers(),
            'json' => [
                'shared_link' => [
                    'access' => 'open',
                    'unshared_at' => $expiresAt,
                    'permissions' => [
                        'can_download' => true,
                        'can_preview' => true
                    ],
                ]
            ],
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    public function uploadFile(string $filePath, int $parentId = 0, string $fileName = '')
    {
        $fileSize = filesize($filePath);
        $calcSize = round($fileSize / 1024);

        $result = ( $calcSize <= 50 * 1024 ) ? 
            $this->uploadSmallFile($filePath, $parentId, $fileName) 
            : $this->uploadLargeFile($filePath, $parentId, $fileName);

        return $result;
    }

    public function uploadSmallFile(string $filePath, int $parentId = 0, string $fileName = '')
    {
        // better for file size not more than 50 MB
        $fileNameOri = basename($filePath);
        $fileSizeOri = filesize($filePath);
        $fileExtensionOri = pathinfo($filePath, PATHINFO_EXTENSION);

        $fileName = ( $fileName != '' ) ? $fileName : $this->fileName() . '.' . $fileExtensionOri;

        $parentId = $parentId == 0 ? $this->config['folder']['parent'] : $parentId;

        $res = $this->http->post($this->config['upload_url'] . '/files/content', [
            'headers' => $this->headers(),
            'multipart' => [
                [
                    'name' => 'attributes',
                    'contents' => json_encode([
                        'name' => $fileName,
                        'parent' => ['id' => $parentId],
                    ]),
                ],
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => $fileName,
                ]
            ]
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    public function uploadLargeFile(string $filePath, int $parentId = 0, string $fileName = '')
    {
        // better for file size more than 50 MB
        $fileNameOri = basename($filePath);
        $fileSizeOri = filesize($filePath);
        $fileExtensionOri = pathinfo($filePath, PATHINFO_EXTENSION);
        
        $fileName = ( $fileName != '' ) ? $fileName : $this->fileName() . '.' . $fileExtensionOri;

        $parentId = $parentId == 0 ? $this->config['folder']['parent'] : $parentId;

        $res = $this->http->post($this->config['upload_url'] . '/files/upload_sessions', [
            'headers' => $this->headers(['Content-Type' => 'application/json']),
            'json' => [
                'folder_id' => (string) $parentId,
                'file_name' => $fileName,
                'file_size' => $fileSizeOri
            ]
        ]);

        $status = $res->getStatusCode();
        $body = json_decode($res->getBody(), true);

        if (is_array($body) && $err = $this->showError($status, $body)) {
            return $err;
        }

        $uploadUrl = $body['session_endpoints']['upload_part'];
        $commitUrl = $body['session_endpoints']['commit'];
        $partSize  = $body['part_size'];
        $sessionId  = $body['id'];

        $parts = [];
        $offset = 0;

        $handle = fopen($filePath, 'rb');

        while (!feof($handle)) {
            $chunk = fread($handle, $partSize);
            $digest = base64_encode(sha1($chunk, true));
            $end = $offset + strlen($chunk) - 1;

            $resp = $this->http->put($uploadUrl, [
                'headers' => $this->headers([
                    'Content-Type' => 'application/octet-stream',
                    'Digest' => 'SHA=' . $digest,
                    'Content-Range' => "bytes $offset-$end/$fileSizeOri",
                ]),
                'body' => $chunk,
            ]);

            $parts[] = json_decode($resp->getBody(), true)['part'];
            $offset += strlen($chunk);
        }
        fclose($handle);

        $fileSha1 = base64_encode(sha1_file($filePath, true));

        // Commit upload
        $commitRes = $this->http->post($commitUrl, [
            'headers' => $this->headers([
                'Content-Type' => 'application/json',
                'Digest' => 'SHA=' . $fileSha1,
            ]),
            'json' => ['parts' => $parts],
        ]);

        $commitStatus = $commitRes->getStatusCode();
        $commitBody = json_decode($commitRes->getBody(), true);

        if (is_array($body) && $err = $this->showError($commitStatus, $commitBody)) {
            return $err;
        }        

        return [
            'success' => true,
            'data' => $commitBody,
        ];
    }
}