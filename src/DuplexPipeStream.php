<?php
/**
 * Andromeda
 * Copyright 2020 Andromeda, All Rights Reserved
 *
 * Website: https://github.com/AndromedaGalaxy/reactphp-libuv-process
 * License: https://github.com/AndromedaGalaxy/reactphp-libuv-process/blob/master/LICENSE
 */

namespace Andromeda\LibuvProcess;

use Evenement\EventEmitterTrait;
use React\EventLoop\ExtUvLoop;
use React\Stream\DuplexStreamInterface;
use React\Stream\WritableStreamInterface;

class DuplexPipeStream implements DuplexStreamInterface {
    use EventEmitterTrait;
    
    /**
     * @var \UVPipe
     */
    protected $pipe;
    
    /**
     * @var ReadablePipeStream
     */
    protected $read;
    
    /**
     * @var WritablePipeStream
     */
    protected $write;
    
    /**
     * Constructor.
     * @param ExtUvLoop                $loop
     * @param \UVPipe                  $pipe
     * @param ReadablePipeStream|null  $read
     * @param WritablePipeStream|null  $write
     */
    function __construct(
        ExtUvLoop $loop,
        \UVPipe $pipe,
        ?ReadablePipeStream $read = null,
        ?WritablePipeStream $write = null
    ) {
        $this->pipe = $pipe;
        $this->read = $read ?? (new ReadablePipeStream($loop, $pipe));
        $this->write = $write ?? (new WritablePipeStream($loop, $pipe));
        
        $this->read->on('data', function ($data) {
            $this->emit('data', array($data));
        });
        
        $this->read->on('error', function ($error) {
            $this->emit('error', array($error));
        });
        
        $this->read->on('end', function () {
            $this->emit('end');
        });
        
        $this->read->on('close', function () {
            $this->emit('close');
            $this->removeAllListeners();
        });
        
        $this->write->on('pipe', function () {
            $this->emit('pipe');
        });
        
        $this->write->on('drain', function () {
            $this->emit('drain');
        });
        
        $this->write->on('error', function ($error) {
            $this->emit('error', array($error));
        });
        
        $this->write->on('close', function () {
            $this->emit('close');
            $this->removeAllListeners();
        });
    }
    
    /**
     * Destructor.
     * @codeCoverageIgnore
     */
    function __destruct() {
        $this->close();
    }
    
    /**
     * Checks whether this stream is in a readable state (not closed already).
     * @return bool
     */
    function isReadable() {
        return $this->read->isReadable();
    }
    
    /**
     * Pauses reading incoming data events.
     * @return void
     */
    function pause() {
        $this->read->pause();
    }
    
    /**
     * Resumes reading incoming data events.
     * @return void
     */
    function resume() {
        $this->read->resume();
    }
    
    /**
     * Pipes all the data from this readable source into the given writable destination.
     * @param WritableStreamInterface  $dest
     * @param array                    $options
     * @return WritableStreamInterface  `$dest` as is.
     */
    function pipe(WritableStreamInterface $dest, array $options = array()) {
        return $this->read->pipe($dest, $options);
    }
    
    /**
     * Checks whether this stream is in a writable state (not closed already).
     * @return bool
     */
    function isWritable() {
        return $this->write->isWritable();
    }
    
    /**
     * Write some data into the stream.
     * @param mixed|string $data
     * @return bool
     */
    function write($data) {
        return $this->write->write($data);
    }
    
    /**
     * Successfully ends the stream (after optionally sending some final data).
     * @param mixed|string|null $data
     * @return void
     */
    function end($data = null) {
        $this->write->end($data);
    }
    
    /**
     * Closes the stream (forcefully).
     * @return void
     */
    function close() {
        $this->read->close();
        $this->write->close();
    }
}