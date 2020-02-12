<?php
/**
 * Andromeda
 * Copyright 2020 Andromeda, All Rights Reserved
 *
 * Website: https://github.com/AndromedaGalaxy/reactphp-libuv-process
 * License: https://github.com/AndromedaGalaxy/reactphp-libuv-process/blob/master/LICENSE
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection PhpInternalEntityUsedInspection
 */

namespace Andromeda\LibuvProcess\Tests;

use Andromeda\LibuvProcess\UvReadablePipeStream;
use React\EventLoop\ExtUvLoop;
use React\Promise\Deferred;
use React\Stream\WritableResourceStream;
use function Clue\React\Block\await;
use function Clue\React\Block\sleep;

class UvReadablePipeStreamTest extends TestCase {
    /**
     * @var ExtUvLoop
     */
    protected $loop;
    
    /**
     * @var resource|\UVPipe
     */
    protected $pipe;
    
    function setUp() {
        parent::setUp();
        
        $this->loop = new ExtUvLoop();
        $this->pipe = \uv_pipe_init($this->loop->getUvLoop(), 0);
    }
    
    function tearDown() {
        parent::tearDown();
        
        if(@\uv_is_readable($this->pipe)) {
            \uv_close($this->pipe, static function () {});
        }
    }
    
    /**
     * @runInSeparateProcess
     */
    function testReadError() {
        $this->markTestSkipped('Impossible to test?');
        
        $this->spawnProcess(true);
        
        $stream = new UvReadablePipeStream($this->loop, $this->pipe);
        
        $deferred = new Deferred();
        $stream->once('error', array($deferred, 'resolve'));
        
        await($deferred->promise(), $this->loop, 10.0);
    }
    
    /**
     * @runInSeparateProcess
     */
    function testIsReadable() {
        $this->spawnProcess();
        
        $stream = new UvReadablePipeStream($this->loop, $this->pipe);
        $this->assertTrue($stream->isReadable());
        
        $deferred = new Deferred();
        $stream->once('close', array($deferred, 'resolve'));
        
        await($deferred->promise(), $this->loop, 1.0);
        $this->assertFalse($stream->isReadable());
        
    }
    
    /**
     * @runInSeparateProcess
     */
    function testResumePause() {
        $this->spawnProcess();
        
        $stream = new UvReadablePipeStream($this->loop, $this->pipe);
        $stream->pause();
        
        $data = null;
        $stream->once('data', function ($d) use (&$data) {
            $data = $d;
        });
        
        $counter = 0;
        $cfn = static function () use (&$counter) { $counter++; };
        
        $stream->once('end', $cfn);
        $stream->once('close', $cfn);
        
        sleep(0.1, $this->loop);
        $this->assertNull($data);
        
        $stream->pause();
        $stream->resume();
        $stream->resume();
        
        $deferred = new Deferred();
        $stream->once('data', array($deferred, 'resolve'));
        
        $data = await($deferred->promise(), $this->loop, 1.0);
        $this->assertSame('Hello World', $data);
        
        sleep(2.0, $this->loop);
        $this->assertSame(2, $counter);
    }
    
    /**
     * @runInSeparateProcess
     */
    function testPipe() {
        $this->spawnProcess();
        
        $stream = new UvReadablePipeStream($this->loop, $this->pipe);
        
        $memory = \fopen('php://memory', 'w+');
        $memstr = new WritableResourceStream($memory, $this->loop);
        
        $deferred = new Deferred();
        $memstr->once('pipe', array($deferred, 'resolve'));
        
        $res = $stream->pipe($memstr);
        $this->assertSame($memstr, $res);
        
        $this->assertCount(1, $stream->listeners('data'));
        
        $source = await($deferred->promise(), $this->loop, 0.001);
        $this->assertSame($stream, $source);
    }
    
    /**
     * @runInSeparateProcess
     */
    function testClose() {
        $this->spawnProcess();
        
        $stream = new UvReadablePipeStream($this->loop, $this->pipe);
        
        $deferred = new Deferred();
        $stream->once('close', array($deferred, 'resolve'));
        
        $counter = 0;
        $cfn = static function () use (&$counter) { $counter++; };
        $stream->on('close', $cfn);
        
        $stream->close();
        $stream->close();
        
        await($deferred->promise(), $this->loop, 1.0);
        sleep(0.01, $this->loop);
        
        $this->assertSame(1, $counter);
    }
    
    protected function spawnProcess(bool $error = false) {
        $std = \uv_stdio_new($this->pipe, (\UV::CREATE_PIPE | \UV::WRITABLE_PIPE | 0x40));
        
        $null = (\PHP_OS_FAMILY === 'Windows' ? 'nul' : '/dev/null');
        $fd = \fopen($null, 'r+b');
        $std2 = \uv_stdio_new($fd, \UV::INHERIT_FD);
        
        $cb = static function ($process) {
            \uv_close($process, static function () {});
        };
        
        if($error) {
            $code = 'fclose(STDOUT); sleep(5);';
        } else {
            $code = 'echo "Hello World";';
        }
        
        $cmd = \PHP_BINARY;
        $args = array('-r', $code);
        
        \uv_spawn($this->loop->getUvLoop(), $cmd, $args, array($std2, $std), \getcwd(), array(), $cb);
    }
}
