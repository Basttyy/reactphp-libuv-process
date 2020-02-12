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

use Andromeda\LibuvProcess\UvWritablePipeStream;
use React\EventLoop\ExtUvLoop;
use React\Promise\Deferred;
use function Clue\React\Block\await;
use function Clue\React\Block\sleep;

class UvWritablePipeStreamTest extends TestCase {
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
        
        if(@\uv_is_writable($this->pipe)) {
            \uv_close($this->pipe, static function () {});
        }
    }
    
    /**
     * @runInSeparateProcess
     */
    function testWriteError() {
        $this->markTestSkipped('Impossible to test?');
        
        $this->spawnProcess();
        
        $stream = new UvWritablePipeStream($this->loop, $this->pipe);
        $this->assertTrue($stream->isWritable());
        
        $deferred = new Deferred();
        $stream->once('error', array($deferred, 'resolve'));
        
        $stream->write('<?php echo "Hello World";');
        
        sleep(0.001, $this->loop);
        \uv_close($this->pipe, static function () {});
        
        await($deferred->promise(), $this->loop, 10.0);
    }
    
    /**
     * @runInSeparateProcess
     */
    function testIsWritable() {
        $this->spawnProcess(array('-r', 'exit;'));
        
        $stream = new UvWritablePipeStream($this->loop, $this->pipe);
        $this->assertTrue($stream->isWritable());
        
        $stream->close();
        $this->assertFalse($stream->isWritable());
    }
    
    /**
     * @runInSeparateProcess
     */
    function testWrite() {
        $stdout = \uv_pipe_init($this->loop->getUvLoop(), 0);
        $this->spawnProcess(array(), array(array($stdout, \UV::WRITABLE_PIPE)));
        
        $read = new Deferred();
        $code = null;
        $data = null;
        
        \uv_read_start($stdout, static function ($pipe, $nread, $buffer) use ($read, &$code, &$data) {
            $code = $nread;
            $data = $buffer;
            
            \uv_close($pipe, static function () {});
            $read->resolve();
        });
        
        $stream = new UvWritablePipeStream($this->loop, $this->pipe);
        $this->assertTrue($stream->isWritable());
        
        $stream->write('<?php echo "Hello World";');
        
        $deferred = new Deferred();
        $stream->once('close', array($deferred, 'resolve'));
        
        $stream->close();
        
        await($deferred->promise(), $this->loop, 10.0);
        await($read->promise(), $this->loop, 10.0);
        
        $this->assertSame('Hello World', $data);
    }
    
    /**
     * @runInSeparateProcess
     */
    function testWriteZero() {
        $this->spawnProcess();
        
        $stream = new UvWritablePipeStream($this->loop, $this->pipe);
        $this->assertTrue($stream->isWritable());
        
        $this->assertTrue($stream->write(''));
        
        \uv_write($this->pipe, '<?php exit;', static function ($pipe) {
            @\uv_close($pipe, static function () {});
        });
    }
    
    /**
     * @runInSeparateProcess
     */
    function testEnd() {
        $stdout = \uv_pipe_init($this->loop->getUvLoop(), 0);
        $this->spawnProcess(array(), array(array($stdout, \UV::WRITABLE_PIPE)));
        
        $read = new Deferred();
        $code = null;
        $data = null;
        
        \uv_read_start($stdout, static function ($pipe, $nread, $buffer) use ($read, &$code, &$data) {
            $code = $nread;
            $data = $buffer;
            
            \uv_close($pipe, static function () {});
            $read->resolve();
        });
        
        $stream = new UvWritablePipeStream($this->loop, $this->pipe);
        $this->assertTrue($stream->isWritable());
        
        $deferred = new Deferred();
        $stream->once('close', array($deferred, 'resolve'));
        
        $stream->end('<?php echo "Hello World";');
        $stream->close();
        
        $this->assertFalse($stream->write(''));
        
        await($deferred->promise(), $this->loop, 10.0);
        await($read->promise(), $this->loop, 10.0);
        
        $this->assertSame('Hello World', $data);
    }
    
    /**
     * @runInSeparateProcess
     */
    function testClose() {
        $this->spawnProcess(array('-r', 'exit;'));
        
        $stream = new UvWritablePipeStream($this->loop, $this->pipe);
        $this->assertTrue($stream->isWritable());
        
        $deferred = new Deferred();
        $stream->once('close', array($deferred, 'resolve'));
        
        $stream->close();
        
        await($deferred->promise(), $this->loop, 10.0);
        $this->assertFalse($stream->isWritable());
    }
    
    protected function spawnProcess(array $args = array(), array $addPipes = array()) {
        $std = array(
            \uv_stdio_new($this->pipe, (\UV::CREATE_PIPE | \UV::READABLE_PIPE | 0x40))
        );
        
        foreach($addPipes as $pipe) {
            $std[] = \uv_stdio_new($pipe[0], (\UV::CREATE_PIPE | $pipe[1] | 0x40));
        }
        
        $cb = static function ($process) {
            \uv_close($process, static function () {});
        };
        
        \uv_spawn($this->loop->getUvLoop(), \PHP_BINARY, $args, $std, \getcwd(), array(), $cb);
    }
}
