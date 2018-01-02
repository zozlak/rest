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

/**
 * Description of JSONFormatter
 *
 * @author zozlak
 */
class JSONFormatter implements FormatterInterface {

    const INVALID    = 1;
    const OBJECT     = 2;
    const COLLECTION = 3;
    const ENDED      = 4;

    /**
     *
     * @var string
     */
    private $buffer     = '';

    /**
     *
     * @var int
     */
    private $bufferSize = 0;

    /**
     *
     * @var int
     */
    private $bufferSizeLimit;

    /**
     *
     * @var int
     */
    private $state      = self::INVALID;

    /**
     *
     * @var bool
     */
    private $firstEl    = true;

    /**
     *
     * @var bool
     */
    private $headerSend = false;

    /**
     *
     * @var array
     */
    private $stack      = array();

    /**
     * 
     * @param int $bufferSize
     */
    public function __construct(int $bufferSize = 10000000) {
        $this->bufferSizeLimit = $bufferSize;
    }

    /**
     * 
     */
    public function end() {
        if (!$this->headerSend && (strlen($this->buffer) > 0 || count($this->stack) > 0)) {
            $this->echoContentType();
        }
        echo $this->buffer;
        echo implode('', $this->stack);
        $this->state = self::ENDED;
    }

    /**
     * 
     * @param string $key
     */
    public function initCollection(string $key = null) {
        $this->checkState($key);
        $this->bufferSize += 1;
        $this->bufferSize += $this->appendComa();
        $this->bufferSize += $this->appendKey($key);
        $this->buffer     .= '[';
        $this->stack[]    = ']';
        $this->state      = self::COLLECTION;
        $this->firstEl    = true;
    }

    /**
     * 
     * @throws \RuntimeException
     */
    public function closeCollection() {
        if (count($this->stack) === 0) {
            throw new \RuntimeException('can not close object - not in object');
        }
        $last = $this->stack[count($this->stack) - 1];
        if ($last !== ']') {
            throw new \RuntimeException('can not close object - not in object');
        }
        $this->bufferSize += 1;
        $this->buffer     .= $last;
        array_pop($this->stack);
        $this->state      = $this->getStateFromStack();
    }

    /**
     * 
     * @param string $key
     */
    public function initObject(string $key = null) {
        $this->checkState($key);
        $this->bufferSize += 1;
        $this->bufferSize += $this->appendComa();
        $this->bufferSize += $this->appendKey($key);
        $this->buffer     .= '{';
        $this->stack[]    = '}';
        $this->state      = self::OBJECT;
        $this->firstEl    = true;
    }

    /**
     * 
     * @throws \RuntimeException
     */
    public function closeObject() {
        if (count($this->stack) === 0) {
            throw new \RuntimeException('can not close object - not in object');
        }
        $last = $this->stack[count($this->stack) - 1];
        if ($last !== '}') {
            throw new \RuntimeException('can not close object - not in object');
        }
        $this->bufferSize += 1;
        $this->buffer     .= $last;
        array_pop($this->stack);
        $this->state      = $this->getStateFromStack();
    }

    /**
     * 
     * @param type $d
     * @param string $key
     */
    public function append($d, string $key = null) {
        $this->checkState($key);
        $this->bufferSize += $this->appendComa();
        $this->bufferSize += $this->appendKey($key);
        $d                = json_encode($d, \JSON_NUMERIC_CHECK);
        $this->buffer     .= $d;
        $this->bufferSize += mb_strlen($d);
        $this->firstEl    = false;
        $this->checkBufferSize();
    }

    /**
     * 
     * @param type $d
     */
    public function rawData($d) {
        $this->headerSend = true;
        echo($d);
        $this->state      = self::ENDED;
    }

    /**
     * 
     * @param mixed $d
     */
    public function data($d) {
        $this->echoContentType();
        echo json_encode($d, \JSON_NUMERIC_CHECK);
        $this->state = self::ENDED;
    }

    /**
     * 
     */
    private function echoContentType() {
        $this->headerSend = true;
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * 
     */
    private function checkBufferSize() {
        if ($this->bufferSize > $this->bufferSizeLimit) {
            if (!$this->headerSend) {
                $this->echoContentType();
            }
            echo $this->buffer;
            $this->buffer     = '';
            $this->bufferSize = 0;
        }
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
            return mb_strlen($key);
        }
        return 0;
    }

    /**
     * 
     * @param string $key
     * @throws \RuntimeException
     */
    private function checkState(string $key) {
        if ($this->state === self::ENDED) {
            throw new \RuntimeException('no more data can be send');
        }
        if ($key != '' && $this->state !== self::OBJECT) {
            throw new \RuntimeException('key allowed only within an object');
        }
        if ($key == '' && $this->state === self::OBJECT) {
            throw new \RuntimeException('key is required within an object');
        }
    }

    /**
     * 
     * @return int
     * @throws \RuntimeException
     */
    private function getStateFromStack(): int {
        if (count($this->stack) === 0) {
            return $this->bufferSize > 0 || $this->headerSend ? self::ENDED : self::INVALID;
        }
        $last = $this->stack[count($this->stack) - 1];
        switch ($last) {
            case '}':
                return self::OBJECT;
            case ']':
                return self::COLLECTION;
            default:
                throw new \RuntimeException('unknown item on the stack');
        }
    }

}
