# GameAP Daemon Client

[![Build Status](https://travis-ci.com/et-nik/gameap-daemon-client.svg?branch=master)](https://travis-ci.com/et-nik/gameap-daemon-client)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/et-nik/gameap-daemon-client/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/et-nik/gameap-daemon-client/?branch=master)
[![Coverage Status](https://scrutinizer-ci.com/g/et-nik/gameap-daemon-client/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/et-nik/gameap-daemon-client/code-structure)

- [Installation](#installation)
- [Usage](#usage)
    - [Commands](#commands)
        - [Connect to server](#connect-to-server)
        - [Execute command](#execute-command)
    - [Files](#files)
        - [Connect to server](#connect-to-server-1)
        - [Listing directory](#listing-directory)
            - [Detail info about files](#detail-info-about-files)
            - [File names only](#file-names-only)
        - [Create directory](#create-directory)
        - [Remove](#remove)
        - [Move/Rename](#rename)
        - [Copy](#copy)
        - [Change permission](#change-permission)
        - [Exist checking](#exist-checking)
        - [Metadata](#metadata)
        - [Download file from server](#download-file-from-server)
        - [Upload file](#upload-file)

## Installation

```bash
composer require knik/gameap-daemon-client
```

## Usage

### Commands

#### Connect to server

```php
$gdaemonCommands = new GdaemonCommands([
    'host' => 'localhost',
    'port' => 31717,
    'serverCertificate' => '/path/to/server.crt',
    'localCertificate' => '/path/to/client.crt',
    'privateKey' => '/path/to/client.key.pem',
    'privateKeyPass' => '1234',
    'timeout' => 10,
    'workDir' => '/home/user',
]);
```

#### Execute command

```php
$result = $gdaemonCommands->exec('echo HELLO');
var_dump($result); // string(5) "HELLO"
```

Exit code:

```php
$result = $gdaemonCommands->exec('echo HELLO', $exitCode);
var_dump($result); // string(5) "HELLO"
var_dump($exitCode); // int(0)
```


### Files

#### Connect to server

```php
$gdaemonFiles = new GdaemonFiles([
    'host' => 'localhost',
    'port' => 31717,
    'serverCertificate' => '/path/to/server.crt',
    'localCertificate' => '/path/to/client.crt',
    'privateKey' => '/path/to/client.key.pem',
    'privateKeyPass' => '1234',
    'timeout' => 10,
]);
```

#### Listing directory

##### Detail info about files

```php
$result = $gdaemonFiles->directoryContents('/path/to/dir');

print_r($result);
/*
Array
(
    [0] => Array
       (
           [name] => directory
           [size] => 0
           [mtime] => 1542013640
           [type] => dir
           [permissions] => 0755
       )

    [1] => Array
       (
           [name] => file.txt
           [size] => 15654
           [mtime] => 1542013150
           [type] => file
           [permissions] => 0644
       )

)

*/
```

##### File names only

```php
$result = $gdaemonFiles->listFiles('/path/to/dir');

print_r($result);
Array
(
    [0] => directory
    [1] => file.txt
)
```

#### Create directory

```php
$gdaemonFiles->mkdir('/path/to/new_dir');
```

#### Remove

```php
$gdaemonFiles->delete('/path/to/file.txt');
```

To remove a directory that contains other files or directories:

```php
$gdaemonFiles->delete('/path/to/file.txt', true);
```

#### Rename

Rename or move files/directories

```php
$gdaemonFiles->rename('/path/to/file.txt', '/path/to/new_name.txt');
```

#### Copy

```php
$gdaemonFiles->copy('/path/to/file.txt', '/path/to/new_file.txt');
```

#### Change permission

```php
$gdaemonFiles->chmod(0755, '/path/to/file.txt');
```

#### Exist checking

 ```php
$gdaemonFiles->exist('/path/to/file.txt');
 ```

#### Metadata

```php
$result = $gdaemonFiles->directoryContents('/path/to/file.txt');

print_r($result);
/*
Array
(
    [name] => file.txt
    [size] => 43
    [type] => file
    [mtime] => 1541971363
    [atime] => 1541971363
    [ctime] => 1541971363
    [permissions] => 0644
    [mimetype] => text/plain
)
*/
```

#### Download file from server

```php
$gdaemonFiles->get('/remote/path/to/file.txt', '/local/path/to/file.txt');
```

File handle:
```php
$fileHandle = fopen('php://temp', 'w+b');
$gdaemonFiles->get('/remote/path/to/file.txt', $fileHandle);
```

#### Upload file

```php
$gdaemonFiles->put('/local/path/to/file.txt', '/remote/path/to/file.txt');
```

File handle:
```php
$fileHandle = fopen('/local/path/to/file.txt', 'r');
$gdaemonFiles->put($fileHandle, '/remote/path/to/file.txt');