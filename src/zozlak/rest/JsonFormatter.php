<?php

/*
 * The MIT License
 *
 * Copyright 2016 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace zozlak\rest;

use RuntimeException;

/**
 * Description of JSONFormatter
 *
 * @author zozlak
 */
class JsonFormatter extends DataFormatter {

    const INVALID    = 1;
    const OBJECT     = 2;
    const COLLECTION = 3;
    const ENDED      = 4;

    /**
     *
     * @var string
     */
    private $buffer = '';

    /**
     *
     * @var int
     */
    private $state = self::INVALID;

    /**
     *
     * @var bool
     */
    private $firstEl = true;

    /**
     *
     * @var array
     */
    private $stack = [];

    /**
     * 
     * @return array
     */
    protected function getHeaders(): array {
        return ['Content-Type' => 'application/json; charset=utf-8'];
    }

    protected function sendBuffer(): int {
        echo $this->buffer;
        $this->buffer = '';
        return 0;
    }
    
    /**
     * 
     */
    public function end(): DataFormatter {
        $tmp          = implode('', $this->stack);
        $this->stack  = [];
        $this->buffer .= $tmp;
        $this->incBufferSize(strlen($tmp));
        $this->state  = self::ENDED;
        parent::end();
        return $this;
    }

    /**
     * 
     * @param string $key
     */
    public function initCollection(string $key = null): DataFormatter {
        $this->checkState($key);
        $size          = 1;
        $size          += $this->appendComa();
        $size          += $this->appendKey($key);
        $this->incBufferSize($size);
        $this->buffer  .= '[';
        $this->stack[] = ']';
        $this->state   = self::COLLECTION;
        $this->firstEl = true;
        return $this;
    }

    /**
     * 
     * @throws RuntimeException
     */
    public function closeCollection(): DataFormatter {
        if (count($this->stack) === 0) {
            throw new RuntimeException('can not close object - not in object');
        }
        $last = $this->stack[count($this->stack) - 1];
        if ($last !== ']') {
            throw new RuntimeException('can not close object - not in object');
        }
        $this->buffer .= $last;
        array_pop($this->stack);
        $this->state  = $this->getStateFromStack();
        $this->incBufferSize(1);
        return $this;
    }

    /**
     * 
     * @param string $key
     */
    public function initObject(string $key = null): DataFormatter {
        $this->checkState($key);
        $size          = 1;
        $size          += $this->appendComa();
        $size          += $this->appendKey($key);
        $this->buffer  .= '{';
        $this->stack[] = '}';
        $this->state   = self::OBJECT;
        $this->firstEl = true;
        $this->incBufferSize($size);
        return $this;
    }

    /**
     * 
     * @throws RuntimeException
     */
    public function closeObject(): DataFormatter {
        if (count($this->stack) === 0) {
            throw new RuntimeException('can not close object - not in object');
        }
        $last = $this->stack[count($this->stack) - 1];
        if ($last !== '}') {
            throw new RuntimeException('can not close object - not in object');
        }
        $this->buffer .= $last;
        array_pop($this->stack);
        $this->state  = $this->getStateFromStack();
        $this->incBufferSize(1);
        return $this;
    }

    /**
     * 
     * @param type $d
     * @param string $key
     */
    public function append($d, string $key = null): DataFormatter {
        $this->checkState($key);
        $size          = $this->appendComa();
        $size          += $this->appendKey($key);
        $d             = json_encode($d, \JSON_NUMERIC_CHECK);
        $this->buffer  .= $d;
        $size          += strlen($d);
        $this->firstEl = false;
        $this->incBufferSize($size);
        return $this;
    }

    /**
     * 
     * @param mixed $d
     */
    public function data($d): DataFormatter {
        $this->buffer = json_encode($d, \JSON_NUMERIC_CHECK);
        $this->state = self::ENDED;
        $this->incBufferSize(strlen($this->buffer));
        return $this;
    }

    /**
     * 
     * @return int
     */
    private function appendComa(): int {
        if (!$this->firstEl) {
            $this->buffer .= ',';
            return 1;
        }
        return 0;
    }

    /**
     * 
     * @param string $key
     * @return int
     */
    private function appendKey(string $key): int {
        if ($key != '') {
            $key          = sprintf('"%s":', str_replace('"', '\\"', $key));
            $this->buffer .= $key;
            return strlen($key);
        }
        return 0;
    }

    /**
     * 
     * @param string $key
     * @throws RuntimeException
     */
    private function checkState(string $key) {
        if ($this->state === self::ENDED) {
            throw new RuntimeException('no more data can be send');
        }
        if ($key != '' && $this->state !== self::OBJECT) {
            throw new RuntimeException('key allowed only within an object');
        }
        if ($key == '' && $this->state === self::OBJECT) {
            throw new RuntimeException('key is required within an object');
        }
    }

    /**
     * 
     * @return int
     * @throws RuntimeException
     */
    private function getStateFromStack(): int {
        if (count($this->stack) === 0) {
            return $this->getBufferSize() > 0 || $this->headerSend ? self::ENDED : self::INVALID;
        }
        $last = $this->stack[count($this->stack) - 1];
        switch ($last) {
            case '}':
                return self::OBJECT;
            case ']':
                return self::COLLECTION;
            default:
                throw new RuntimeException('unknown item on the stack');
        }
    }

}
