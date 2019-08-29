<?php

namespace Knik\Gameap;

use Knik\Binn\BinnList;
use RuntimeException;
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
    public function put($locFile, $remFile, $permission = 0644)
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
            throw new RuntimeException('File open error');
        }

        $stat = fstat($fileHandle);
        $filesize = $stat['size'];
        unset($stat);

        $writeBinn = new BinnList;

        $writeBinn->addUint8(self::FSERV_FILESEND);
        $writeBinn->addUint8(self::FSERV_UPLOAD_TO_SERVER);
        $writeBinn->addStr($remFile);
        $writeBinn->addUint64($filesize);
        $writeBinn->addBool(true); // Make dirs
        $writeBinn->addUint8($permission);

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] == self::FSERV_STATUS_OK) {
            throw new RuntimeException('Unexpected \'OK\' status. Expected \'ready to transfer\'');
        } else if ($results[0] != self::FSERV_STATUS_FILE_TRANSFER_READY) {
            throw new RuntimeException('Couldn\'t upload file: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        while(!feof($fileHandle)) {
            $this->writeSocket(fread($fileHandle, $this->maxBufsize));
        }

        $read = $this->readSocket();

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new RuntimeException('Couldn\'t send file: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
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
    public function get($remFile, $locFile)
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
            throw new RuntimeException('File open error');
        }

        $writeBinn = new BinnList;

        $writeBinn->addUint8(self::FSERV_FILESEND);
        $writeBinn->addUint8(self::FSERV_DOWNLOAD_FR_SERVER);
        $writeBinn->addStr($remFile);

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] == self::FSERV_STATUS_OK) {
            throw new RuntimeException('Unexpected `OK` status. Expected `ready to transfer`');
        } else if ($results[0] != self::FSERV_STATUS_FILE_TRANSFER_READY) {
            throw new RuntimeException('Couldn\'t upload file: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        $this->writeSocket(self::SOCKET_MSG_ENDL);

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

    /**
     * List files
     *
     * @param string $directory
     *
     * @return array Files names list
     */
    public function listFiles($directory)
    {
        $writeBinn= new BinnList;

        $writeBinn->addUint8(self::FSERV_READDIR);
        $writeBinn->addStr($directory);     // Dir path
        $writeBinn->addUint8(0);       // Mode

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;

        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] != self::FSERV_STATUS_OK) {
            // Error
            throw new RuntimeException('GDaemon List files error:' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        $filesList = $results[2];
        $returnList = [];

        foreach($filesList as &$file) {

            if (in_array(basename($file[0]), ['.', '..'])) {
                continue;
            }

            $returnList[] = basename($file[0]);
        }

        return $returnList;
    }

    /**
     * @param string $directory
     * @return array
     */
    public function directoryContents($directory)
    {
        $writeBinn= new BinnList;

        $writeBinn->addUint8(self::FSERV_READDIR);
        $writeBinn->addStr($directory);     // Dir path
        $writeBinn->addUint8(1);       // Mode

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;

        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] != self::FSERV_STATUS_OK) {
            // Error
            throw new RuntimeException('GDaemon List files error:' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        $filesList = $results[2];
        $returnList = [];

        foreach($filesList as &$file) {
            if (in_array(basename($file[0]), ['.', '..'])) {
                continue;
            }

            $returnList[] = array(
                'name' => basename($file[0]),
                'size' => $file[1],
                'mtime' => $file[2],
                'type' => ($file[3] == 1) ? 'dir' : 'file',
                'permissions' => $file[4],
            );
        }

        return $returnList;
    }

    /**
     * Make directory
     *
     * @param string $path
     * @param int $permissions
     * @return bool
     */
    public function mkdir($path, $permissions = 0755)
    {
        $writeBinn = new BinnList;

        $writeBinn->addUint8(self::FSERV_MKDIR);
        $writeBinn->addStr($path);
        $writeBinn->addStr($permissions);

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new RuntimeException('Couldn\'t make directory: ' . isset($results[1]) ? $results[1] : 'Unknown');
        }

        return true;
    }

    /**
     * Rename file
     *
     * @param string $oldPath
     * @param string $newPath
     * @return bool
     */
    public function rename($oldPath, $newPath)
    {
        return $this->move($oldPath, $newPath);
    }

    /**
     * Move file
     *
     * @param string $oldPath
     * @param string $newPath
     * @return bool
     */
    public function move($oldPath, $newPath)
    {
        $writeBinn = new BinnList;

        $writeBinn->addUint8(self::FSERV_MOVE);
        $writeBinn->addStr($oldPath);
        $writeBinn->addStr($newPath);
        $writeBinn->addBool(false);

        $binn = $writeBinn->serialize();

        $read = $this->writeAndReadSocket($binn);

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new RuntimeException('Couldn\'t move file: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        return true;
    }

    /**
     * Copy file
     *
     * @param string $oldPath
     * @param string $newPath
     * @return bool
     */
    public function copy($oldPath, $newPath)
    {
        $writeBinn = new BinnList;

        $writeBinn->addUint8(self::FSERV_MOVE);
        $writeBinn->addStr($oldPath);
        $writeBinn->addStr($newPath);
        $writeBinn->addBool(true);            // Copy

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new RuntimeException('Couldn\'t copy file: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        return true;
    }

    /**
     * Delete file/directory
     *
     * @param string $path
     * @param bool $recursive
     * @return bool
     */
    public function delete($path, $recursive = false)
    {
        $writeBinn = new BinnList;

        $writeBinn->addUint8(self::FSERV_REMOVE);
        $writeBinn->addStr($path);
        $writeBinn->addBool($recursive);

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new RuntimeException('Couldn\'t delete: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        return true;
    }

    /**
     * Get file metadata
     *
     * @param string $path
     * @return array
     */
    public function metadata($path)
    {
        $writeBinn= new BinnList;

        $writeBinn->addUint8(self::FSERV_FILEINFO);
        $writeBinn->addStr($path);

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;

        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new RuntimeException('GDaemon metadata error: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
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

    /**
     * Change file mode
     *
     * @param integer $mode
     * @param string $path
     * @return bool
     */
    public function chmod($mode, $path)
    {
        $writeBinn = new BinnList;

        $writeBinn->addUint8(self::FSERV_CHMOD);
        $writeBinn->addStr($path);
        $writeBinn->addUint16($mode);

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] != self::FSERV_STATUS_OK) {
            throw new RuntimeException('Couldn\'t chmod: ' . (isset($results[1]) ? $results[1] : 'Unknown'));
        }

        return true;
    }

    /**
     * Check file exist
     *
     * @param $path
     * @return bool
     */
    public function exist($path)
    {
        return in_array(basename($path), $this->listFiles(dirname($path)));
    }
}