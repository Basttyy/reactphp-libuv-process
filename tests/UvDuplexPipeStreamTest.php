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

use Andromeda\LibuvProcess\UvDuplexPipeStream;
use Andromeda\LibuvProcess\UvReadablePipeStream;
use Andromeda\LibuvProcess\UvWritablePipeStream;
use PHPUnit\Framework\MockObject\MockObject;
use React\EventLoop\ExtUvLoop;
use React\Promise\Deferred;
use React\Stream\WritableStreamInterface;
use function Clue\React\Block\await;

class UvDuplexPipeStreamTest extends TestCase {
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
        
        if(@\uv_is_readable($this->pipe) || @\uv_is_writable($this->pipe)) {
            \uv_close($this->pipe, static function () {});
        }
    }
    
    /**
     * @runInSeparateProcess
     */
    function testEvents() {
        [ $read, $write ] = $this->createMocks();
        $stream = new UvDuplexPipeStream($this->loop, $this->pipe, $read, $write);
        
        $def1 = new Deferred();
        $stream->once('data', array($def1, 'resolve'));
        
        $read->emit('data', array('test'));
        $data1 = await($def1->promise(), $this->loop, 0.001);
        $this->assertSame('test', $data1);
        
        $def2 = new Deferred();
        $stream->once('error', array($def2, 'resolve'));
        
        $read->emit('error', array('test'));
        $data2 = await($def2->promise(), $this->loop, 0.001);
        $this->assertSame('test', $data2);
        
        $stream->once('end', $this->getCallableMock(1));
        $read->emit('end');
        
        $stream->on('close', $this->getCallableMock(1));
        $this->assertCount(1, $stream->listeners());
        
        $read->emit('close');
        $this->assertCount(0, $stream->listeners());
        
        $stream->once('pipe', $this->getCallableMock(1));
        $write->emit('pipe');
        
        $stream->once('drain', $this->getCallableMock(1));
        $write->emit('drain');
        
        $def3 = new Deferred();
        $stream->once('error', array($def3, 'resolve'));
        
        $write->emit('error', array('test'));
        $data3 = await($def3->promise(), $this->loop, 0.001);
        $this->assertSame('test', $data3);
        
        $stream->on('close', $this->getCallableMock(1));
        $this->assertCount(1, $stream->listeners());
        
        $write->emit('close');
        $this->assertCount(0, $stream->listeners());
    }
    
    /**
     * @runInSeparateProcess
     */
    function testIsReadable() {
        [ $read, $write ] = $this->createMocks();
        
        $read
            ->expects($this->at(0))
            ->method('isReadable')
            ->willReturn(true);
        
        $read
            ->expects($this->at(1))
            ->method('isReadable')
            ->willReturn(false);
        
        $stream = new UvDuplexPipeStream($this->loop, $this->pipe, $read, $write);
        $this->assertTrue($stream->isReadable());
        $this->assertFalse($stream->isReadable());
    }
    
    /**
     * @runInSeparateProcess
     */
    function testPause() {
        [ $read, $write ] = $this->createMocks();
        
        $read
            ->expects($this->at(0))
            ->method('pause');
        
        $stream = new UvDuplexPipeStream($this->loop, $this->pipe, $read, $write);
        $stream->pause();
    }
    
    /**
     * @runInSeparateProcess
     */
    function testResume() {
        [ $read, $write ] = $this->createMocks();
        
        $read
            ->expects($this->at(0))
            ->method('resume');
        
        $stream = new UvDuplexPipeStream($this->loop, $this->pipe, $read, $write);
        $stream->resume();
    }
    
    /**
     * @runInSeparateProcess
     */
    function testPipe() {
        [ $read, $write ] = $this->createMocks();
        
        /** @var WritableStreamInterface  $dest */
        $dest = $this->getMockBuilder(WritableStreamInterface::class)
            ->getMock();
        
        $read
            ->expects($this->at(0))
            ->method('pipe')
            ->with($dest)
            ->willReturn($dest);
        
        $stream = new UvDuplexPipeStream($this->loop, $this->pipe, $read, $write);
        $dest2 = $stream->pipe($dest);
        $this->assertSame($dest2, $dest);
    }
    
    /**
     * @runInSeparateProcess
     */
    function testWrite() {
        [ $read, $write ] = $this->createMocks();
        
        $write
            ->expects($this->at(0))
            ->method('write')
            ->with('test')
            ->willReturn(true);
        
        $stream = new UvDuplexPipeStream($this->loop, $this->pipe, $read, $write);
        $this->assertTrue($stream->write('test'));
    }
    
    /**
     * @runInSeparateProcess
     */
    function testEnd() {
        [ $read, $write ] = $this->createMocks();
        
        $write
            ->expects($this->at(0))
            ->method('end')
            ->with('test');
        
        $stream = new UvDuplexPipeStream($this->loop, $this->pipe, $read, $write);
        $stream->end('test');
    }
    
    /**
     * @runInSeparateProcess
     */
    function testIsWritable() {
        [ $read, $write ] = $this->createMocks();
        
        $write
            ->expects($this->at(0))
            ->method('isWritable')
            ->willReturn(true);
        
        $write
            ->expects($this->at(1))
            ->method('isWritable')
            ->willReturn(false);
        
        $stream = new UvDuplexPipeStream($this->loop, $this->pipe, $read, $write);
        $this->assertTrue($stream->isWritable());
        $this->assertFalse($stream->isWritable());
    }
    
    /**
     * @runInSeparateProcess
     */
    function testClose() {
        [ $read, $write ] = $this->createMocks();
        
        $read
            ->expects($this->at(0))
            ->method('close');
        
        $write
            ->expects($this->at(0))
            ->method('close');
        
        $stream = new UvDuplexPipeStream($this->loop, $this->pipe, $read, $write);
        $stream->close();
    }
    
    /**
     * @return MockObject[]
     */
    protected function createMocks(): array {
        $read = $this->getMockBuilder(UvReadablePipeStream::class)
            ->setMethods(array(
                'isReadable',
                'pause',
                'resume',
                'pipe',
                'close'
            ))
            ->disableOriginalConstructor()
            ->getMock();
        
        $write = $this->getMockBuilder(UvWritablePipeStream::class)
            ->setMethods(array(
                'isWritable',
                'write',
                'end',
                'close'
            ))
            ->disableOriginalConstructor()
            ->getMock();
        
        return array($read, $write);
    }
}
