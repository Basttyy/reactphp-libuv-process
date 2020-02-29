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
use React\EventLoop\TimerInterface;
use React\Stream\WritableStreamInterface;

class WritablePipeStream implements WritableStreamInterface {
    use EventEmitterTrait;
    
    /**
     * @var ExtUvLoop
     */
    protected $loop;
    
    /**
     * @var \UVPipe
     */
    protected $pipe;
    
    /**
     * @var bool
     */
    protected $closed = false;
    
    /**
     * @var bool
     */
    protected $writable = true;
    
    /**
     * @var TimerInterface
     */
    protected $timer;
    
    /**
     * @var int
     */
    protected $timeWork = 0;
    
    /**
     * Constructor.
     * @param ExtUvLoop  $loop
     * @param \UVPipe    $pipe
     */
    function __construct(ExtUvLoop $loop, \UVPipe $pipe) {
        $this->loop = $loop;
        $this->pipe = $pipe;
    }
    
    /**
     * Destructor.
     * @codeCoverageIgnore
     */
    function __destruct() {
        $this->close();
    }
    
    /**
     * Checks whether this stream is in a writable state (not closed already).
     * @return bool
     */
    function isWritable() {
        return $this->writable;
    }
    
    /**
     * Write some data into the stream.
     * @param mixed|string $data
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    function write($data) {
        if(!$this->writable) {
            return false;
        }
        
        $data = (string) $data;
        if(\strlen($data) < 1) {
            return true;
        }
        
        if($this->timeWork++ === 0) {
            $this->timer = $this->loop->addTimer(Process::UV_MAX_TIMER_INTERVAL, static function () {});
        }
        
        \uv_write($this->pipe, $data, function (\UVPipe $pipe, int $status) {
            if(--$this->timeWork === 0) {
                $this->loop->cancelTimer($this->timer);
                $this->timer = null;
                
                if($this->closed) {
                    \uv_close($this->pipe, function () {
                        $this->emit('close');
                        $this->removeAllListeners();
                    });
                }
            }
            
            if($status < 0) {
                $e = new \RuntimeException('Unable to write to pipe: '.\uv_strerror($status));
                $this->emit('error', array($e));
                $this->close();
            }
        });
        
        return true;
    }
    
    /**
     * Successfully ends the stream (after optionally sending some final data).
     * @param mixed|string|null $data
     * @return void
     */
    function end($data = null) {
        if($this->writable && $data !== null && $data !== '') {
            $this->write($data);
        }
        
        $this->close();
    }
    
    /**
     * Closes the stream (forcefully).
     * @return void
     */
    function close() {
        if($this->closed) {
            return;
        }
        
        $this->closed = true;
        $this->writable = false;
        
        if($this->timeWork > 0) {
            return;
        } elseif(!@\uv_is_writable($this->pipe)) {
            $this->emit('close');
            $this->removeAllListeners();
            return;
        }
        
        \uv_close($this->pipe, function () {
            $this->emit('close');
            $this->removeAllListeners();
        });
    }
}
