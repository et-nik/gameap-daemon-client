# GameAP Daemon Client

## Installation

```bash
composer require knik/gameap-daemon-client
```

## Usage

### Connect to server

```php
$gdaemonFiles = new GdaemonFiles([
    'host' => 'localhost',
    'port' => 31717,
    'username' => 'sEcreT-L0gin',
    'password' => 'seCrEt-PaSSW0rD',
    'privateKey' => '/path/to/private.pem',
    'privateKeyPass' => '1234',
    'timeout' => 10,
]);
```

### Listing directory

#### Detail info about files

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
           [permission] => 0755
       )

    [1] => Array
       (
           [name] => file.txt
           [size] => 15654
           [mtime] => 1542013150
           [type] => file
           [permission] => 0644
       )

)

*/
```

#### File names only

```php
$result = $gdaemonFiles->listFiles('/path/to/dir');

print_r($result);
Array
(
    [0] => directory
    [1] => file.txt
)
```

### Create directory

```php
$gdaemonFiles->mkdir('/path/to/new_dir');
```

### Remove

```php
$gdaemonFiles->delete('/path/to/file.txt');
```

To remove a directory that contains other files or directories:

```php
$gdaemonFiles->delete('/path/to/file.txt', true);
```

### Rename

Rename or move files/directories

```php
$gdaemonFiles->rename('/path/to/file.txt', '/path/to/new_name.txt');
```

### Copy

```php
$gdaemonFiles->copy('/path/to/file.txt', '/path/to/new_file.txt');
```

### Change permission

```php
$gdaemonFiles->chmod(0755, '/path/to/file.txt');
```

### Exist checking

 ```php
$gdaemonFiles->exist('/path/to/file.txt');
 ```

### Metadata

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

### Download file from server

```php
$gdaemonFiles->get('/remote/path/to/file.txt', '/local/path/to/file.txt');
```

File handle:
```php
$fileHandle = fopen('php://temp', 'w+b');
$gdaemonFiles->get('/remote/path/to/file.txt', $fileHandle);
```

### Upload file

```php
$gdaemonFiles->put('/local/path/to/file.txt', '/remote/path/to/file.txt');
```

File handle:
```php
$fileHandle = fopen('/local/path/to/file.txt', 'r');
$gdaemonFiles->put($fileHandle, '/remote/path/to/file.txt');