# ReactPHP Libuv Child Processes [![CircleCI](https://circleci.com/gh/AndromedaGalaxy/reactphp-libuv-process.svg?style=svg)](https://circleci.com/gh/AndromedaGalaxy/reactphp-libuv-process)

This library provides libuv child processes for ReactPHP.

Due to [ext-uv limitations](https://github.com/bwoebi/php-uv/issues/71), it is currently impossible to get the process identifier.

# Example

This is a minimalistic example:
```php
require 'vendor/autoload.php';

use Andromeda\LibuvProcess\UvProcess;
use React\EventLoop\ExtUvLoop;

$loop = new ExtUvLoop();
$process = new UvProcess(\PHP_BINARY, array('-r', 'echo "Hello World";'));

$process->on('exit', static function (int $exitCode, int $termSignal) {
    echo PHP_EOL.PHP_EOL.'Child Process exited with exit code '.$exitCode.
        ' and term signal '.$termSignal.PHP_EOL;
});

$process->start($loop);

$process->stdin->close();

$process->stdout->on('data', static function ($data) {
    echo $data;
});

$process->stderr->on('data', static function ($data) {
    echo $data;
});

$loop->run();
```

# Install

Install this library through composer using
```
composer require andromeda/react-libuv-process
```
