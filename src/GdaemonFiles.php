<?php

namespace Knik\Gameap;

use Knik\Gameap\Exception\GdaemonClientException;
use InvalidArgumentException;

class GdaemonFiles extends Gdaemon
{
    const FSERV_AUTH            = 1;
    const FSERV_FILESEND        = 3;
    const FSERV_READDIR         = 4;
    const FSERV_MKDIR           = 5;
    const FSERV_MOVE            = 6;
    const FSERV_REMOVE          = 7;
    const FSERV_FILEINFO        = 8;
    const FSERV_CHMOD           = 9;

    const FSERV_UPLOAD_TO_SERVER        = 1;
    const FSERV_DOWNLOAD_FR_SERVER      = 2;

    const FSERV_STATUS_ERROR                = 1;
    const FSERV_STATUS_UNKNOWN_COMMAND      = 3;
    const FSERV_STATUS_OK                   = 100;
    const FSERV_STATUS_FILE_TRANSFER_READY  = 101;

    const LIST_FILES_WITHOUT_DETAILS  = 0;
    const LIST_FILES_WITH_DETAILS     = 1;

    /**
     * @var int
     */
    protected $mode = self::DAEMON_SERVER_MODE_FILES;

    /**
     * Upload file to server
     *
     * @param string|resource $locFile path to local file or file stream
     * @param string $remFile path to remote file
     * @param int $permission
     *
     * @return bool|resource
     */
    public function put($locFile, string $remFile, int $permission = 0644)
    {
        if (is_string($locFile)) {
            set_error_handler(function () {});
            $fileHandle = fopen($locFile, 'r');
            restore_error_handler();
        } else if (is_resource($locFile)) {
            $fileHandle = $locFile;
        } else {
            throw new InvalidArgumentException('Invalid local file');
        }

        if ($fileHandle === false) {
            throw new GdaemonClientException('File open error');
        }

        $stat = fstat($fileHandle);
        $filesize = $stat['size'];
        unset($stat);

        $message = $this->binn->serialize([
            self::FSERV_FILESEND,
            self::FSERV_UPLOAD_TO_SERVER,
            $remFile,
            $filesize,
            true,           // Make dirs
            $permission
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] == self::FSERV_STATUS_OK) {
            throw new GdaemonClientException('Unexpected \'OK\' status. Expected \'ready to transfer\'');
        } else if ($results[0] != self::FSERV_STATUS_FILE_TRANSFER_READY) {
            throw new GdaemonClientException('Couldn\'t upload file: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        while(!feof($fileHandle)) {
            $this->writeSocket(fread($fileHandle, $this->maxBufsize));
        }

        $read = $this->readSocket();
        if (!is_string($read)) {
            throw new GdaemonClientException('Failed to read socket');
        }

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new GdaemonClientException('Couldn\'t send file: ' . ($results[1] ?? 'Unknown'));
        }

        if (is_resource($locFile)) {
            rewind($fileHandle);
            return $fileHandle;
        } else {
            fclose($fileHandle);
            return true;
        }
    }

    /**
     * Download file
     *
     * @param string $remFile path to remote file
     * @param string|resource $locFile path to local file or file stream
     *
     * @return boolean|resource
     */
    public function get(string $remFile, $locFile)
    {
        if (is_string($locFile)) {
            set_error_handler(function () {});
            $fileHandle = fopen($locFile, 'w+b');
            restore_error_handler();
        } else if (is_resource($locFile)) {
            $fileHandle = $locFile;
        } else {
            throw new InvalidArgumentException('Invalid local file');
        }

        if ($fileHandle === false) {
            throw new GdaemonClientException('File open error');
        }

        $message = $this->binn->serialize([
            self::FSERV_FILESEND,
            self::FSERV_DOWNLOAD_FR_SERVER,
            $remFile,
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] == self::FSERV_STATUS_OK) {
            throw new GdaemonClientException('Unexpected `OK` status. Expected `ready to transfer`');
        } else if ($results[0] != self::FSERV_STATUS_FILE_TRANSFER_READY) {
            throw new GdaemonClientException('Couldn\'t upload file: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        $filesize = $results[2];
        $writed = 0;

        while($writed < $filesize) {
            if ($filesize - $writed > $this->maxBufsize) {
                $readlen = $this->maxBufsize;
            }
            else {
                $readlen = $filesize - $writed;
            }

            $socketRead = $this->readSocket($readlen, true);

            $writed += fwrite($fileHandle, $socketRead, $readlen);
        }

        if (is_resource($locFile)) {
            rewind($fileHandle);
            return $fileHandle;
        } else {
            fclose($fileHandle);
            return true;
        }
    }

    public function listFiles(string $directory): array
    {
        $message = $this->binn->serialize([
            self::FSERV_READDIR,
            $directory,
            self::LIST_FILES_WITHOUT_DETAILS,
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new GdaemonClientException('GDaemon List files error:' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        $filesList = $results[2] ?? [];
        $returnList = [];

        foreach($filesList as $file) {

            if (in_array(basename($file[0]), ['.', '..'])) {
                continue;
            }

            $returnList[] = basename($file[0]);
        }

        return $returnList;
    }

    public function directoryContents(string $directory): array
    {
        $message = $this->binn->serialize([
            self::FSERV_READDIR,
            $directory,
            self::LIST_FILES_WITH_DETAILS,
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::FSERV_STATUS_OK) {
            // Error
            throw new GdaemonClientException('GDaemon List files error:' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        $filesList = $results[2];
        $returnList = [];

        foreach($filesList as $file) {
            if (in_array(basename($file[0]), ['.', '..'])) {
                continue;
            }

            $returnList[] = [
                'name' => basename($file[0]),
                'size' => $file[1],
                'mtime' => $file[2],
                'type' => ($file[3] == 1) ? 'dir' : 'file',
                'permissions' => $file[4],
            ];
        }

        return $returnList;
    }

    public function mkdir(string $path, int $permissions = 0755): bool
    {
        $message = $this->binn->serialize([
            self::FSERV_MKDIR,
            $path,
            $permissions,
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new GdaemonClientException(
                'Couldn\'t make directory: ' . isset($results[1]) ? $results[1] : 'Unknown'
            );
        }

        return true;
    }

    public function rename(string $oldPath, string $newPath): bool
    {
        return $this->move($oldPath, $newPath);
    }

    public function move(string $oldPath, string $newPath): bool
    {
        $message = $this->binn->serialize([
            self::FSERV_MOVE,
            $oldPath,
            $newPath,
            false           // Copy file
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new GdaemonClientException('Couldn\'t move file: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        return true;
    }

    public function copy(string $oldPath, string $newPath): bool
    {
        $message = $this->binn->serialize([
            self::FSERV_MOVE,
            $oldPath,
            $newPath,
            true           // Copy file
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new GdaemonClientException('Couldn\'t copy file: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        return true;
    }

    public function delete(string $path, bool $recursive = false): bool
    {
        $message = $this->binn->serialize([
            self::FSERV_REMOVE,
            $path,
            $recursive,
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new GdaemonClientException('Couldn\'t delete: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        return true;
    }

    public function metadata(string $path): array
    {
        $message = $this->binn->serialize([
            self::FSERV_FILEINFO,
            $path,
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new GdaemonClientException('GDaemon metadata error: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        $fileInfo = $results[2];

        return [
            'name' => basename($fileInfo[0]),
            'size' => $fileInfo[1],
            'type' => ($fileInfo[2] == 1) ? 'dir' : 'file',
            'mtime' => $fileInfo[3],
            'atime' => $fileInfo[4],
            'ctime' => $fileInfo[5],
            'permissions' => $fileInfo[6],
            'mimetype' => $fileInfo[7],
        ];
    }

    public function chmod(int $mode, string $path): bool
    {
        $message = $this->binn->serialize([
            self::FSERV_CHMOD,
            $path,
            $mode,
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new GdaemonClientException('Couldn\'t chmod: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        return true;
    }

    public function exist(string $path): bool
    {
        return in_array(basename($path), $this->listFiles(dirname($path)));
    }
}
