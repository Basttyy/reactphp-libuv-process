# ReactPHP Libuv Child Processes [![CircleCI](https://circleci.com/gh/AndromedaGalaxy/reactphp-libuv-process.svg?style=svg)](https://circleci.com/gh/AndromedaGalaxy/reactphp-libuv-process)

This library provides libuv child processes for ReactPHP.

Due to underlying ext-uv circumstances, the PID is only available as of commit [`082fdfb`](https://github.com/bwoebi/php-uv/commit/082fdfb20387af02956a68ba77dfa78e2ae9c5b8) or v0.2.5, otherwise the PID is `0`.

# Example

This is a minimalistic example:
```php
require 'vendor/autoload.php';

use Andromeda\LibuvProcess\Process;
use React\EventLoop\ExtUvLoop;

$loop = new ExtUvLoop();
$process = new Process(\PHP_BINARY, array('-r', 'echo "Hello World";'));

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
