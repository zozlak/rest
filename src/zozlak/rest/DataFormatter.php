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
use zozlak\util\Config;

/**
 *
 * @author zozlak
 */
abstract class DataFormatter {

    /**
     *
     * @var \zozlak\rest\HeadersFormatter
     */
    private $headersFormatter;

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
     * @var bool
     */
    private $headersSend = false;

    /**
     * 
     * @param \zozlak\rest\HeadersFormatter $hf
     * @param \zozlak\util\Config $config
     */
    public function __construct(HeadersFormatter $hf, HttpController $ctrl) {
        $this->headersFormatter = $hf;
        $this->bufferSizeLimit  = $ctrl->getConfig('DataFormatterBufferSize') ?? 10000000;
    }

    /**
     * 
     * @return int
     */
    protected function getBufferSize(): int {
        return $this->bufferSize;
    }

    /**
     * 
     * @param int $size
     * @return int
     */
    protected function incBufferSize(int $size): int {
        $this->bufferSize += $size;
        if ($this->bufferSize > $this->bufferSizeLimit) {
            if (!$this->headersSend) {
                $this->headersFormatter->
                    addHeaders($this->getHeaders())->
                    sendStatus()->
                    sendHeaders();
                $this->headersSend = true;
            }
            $this->bufferSize = $this->sendBuffer();
        }
        return $this->bufferSize;
    }

    abstract protected function sendBuffer(): int;

    abstract protected function getHeaders(): array;

    abstract public function data($d): DataFormatter;

    abstract public function initCollection(string $key): DataFormatter;

    abstract public function closeCollection(): DataFormatter;
    
    abstract public function initObject(string $key = ''): DataFormatter;
    
    abstract public function closeObject(): DataFormatter;

    abstract public function append($d, string $key = ''): DataFormatter;
    
    /**
     * 
     */
    public function end(): DataFormatter {
        if ($this->bufferSize > 0) {
            $this->headersFormatter->addHeaders($this->getHeaders());
        }
        $this->headersFormatter->send();
        $this->bufferSize = $this->sendBuffer();
        if ($this->bufferSize > 0) {
            throw new RuntimeException('Not all data sent');
        }
        return $this;
    }

    /**
     * 
     * @param string $data
     * @param string $contentType
     * @param string $filename
     * @return \zozlak\rest\DataFormatter
     */
    public function raw(string $data, string $contentType,
                        string $filename = null): DataFormatter {
        $this->headersFormatter->addHeader('content-type', $contentType);
        if ($filename) {
            $this->headersFormatter->addHeader('content-disposition', 'attachment; filename="' . $filename . '"');
        }
        $this->headersFormatter->
            sendStatus()->
            sendHeaders();
        echo $data;
        return $this;
    }

    /**
     * 
     * @param string $path
     * @param string $contentType
     * @param string $filename
     * @return \zozlak\rest\DataFormatter
     */
    public function file(string $path, string $contentType = null,
                         string $filename = null): DataFormatter {
        if (!$contentType) {
            $contentType = mime_content_type($path);
        }
        if ($filename) {
            $this->headersFormatter->addHeader('content-disposition', 'attachment; filename="' . basename($filename) . '"');
        }
        $this->headersFormatter->
            addHeader('content-type', $contentType)->
            send();
        readfile($path);

        return $this;
    }

}
