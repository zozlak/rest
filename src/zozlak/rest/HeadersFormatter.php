<?php

/*
 * The MIT License
 *
 * Copyright 2018 zozlak.
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
 * Description of HeadersFormatter
 *
 * @author zozlak
 */
class HeadersFormatter {

    static private $codes = [
        '200' => 'OK',
        '201' => 'Created',
        '202' => 'Accepted',
        '203' => 'Non-Authoritative Information',
        '204' => 'No Content',
        '205' => 'Reset Content',
        '206' => 'Partial Content',
        '207' => 'Multi-Status',
        '208' => 'Already Reported',
        '226' => 'IM Used',
        '300' => 'Multiple Choices',
        '301' => 'Moved Permanently',
        '302' => 'Found',
        '303' => 'See Other',
        '304' => 'Not Modified',
        '305' => 'Use Proxy',
        '306' => 'Switch Proxy',
        '307' => 'Temporary Redirect',
        '308' => 'Permanent Redirect',
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '402' => 'Payment Required',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '407' => 'Proxy Authentication Required',
        '408' => 'Request Timeout',
        '409' => 'Conflict',
        '410' => 'Gone',
        '411' => 'Length Required',
        '412' => 'Precondition Failed',
        '413' => 'Payload Too Large',
        '414' => 'URI Too Long',
        '415' => 'Unsupported Media Type',
        '416' => 'Range Not Satisfiable',
        '417' => 'Expectation Failed',
        '418' => "I'm a teapot",
        '421' => 'Misdirected Request',
        '422' => 'Unprocessable Entity ',
        '423' => 'Locked',
        '424' => 'Failed Dependency',
        '426' => 'Upgrade Required',
        '428' => 'Precondition Required',
        '429' => 'Too Many Requests',
        '431' => 'Request Header Fields Too Large',
        '451' => 'Unavailable For Legal Reasons',
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '502' => 'Bad Gateway',
        '503' => 'Service Unavailable',
        '504' => 'Gateway Timeout',
        '505' => 'HTTP Version Not Supported',
        '506' => 'Variant Also Negotiates',
        '507' => 'Insufficient Storage',
        '508' => 'Loop Detected',
        '510' => 'Not Extended',
        '511' => 'Network Authentication Required',
    ];

    /**
     *
     * @var int
     */
    private $code = 200;

    /**
     *
     * @var string
     */
    private $msg = 'OK';

    /**
     *
     * @var array
     */
    private $headers = [];

    public function setStatus(int $code, string $msg = ''): HeadersFormatter {
        if (!$msg && isset(self::$codes[(string) $code])) {
            $msg = self::$codes[(string) $code];
        }
        $this->code = $code;
        $this->msg  = $msg;
        return $this;
    }

    /**
     * 
     * @param string $header
     * @param type $value
     * @return \zozlak\rest\HeadersFormatter
     */
    public function addHeader(string $header, $value): HeadersFormatter {
        $header = $this->sanitizeHeaderName($header);
        if (!isset($this->headers[$header])) {
            $this->headers[$header] = [];
        }
        if (!is_array($value)) {
            $value = [$value];
        }
        $this->headers[$header] += $value;
        return $this;
    }

    /**
     * 
     * @param array $headers
     * @return \zozlak\rest\HeadersFormatter
     */
    public function addHeaders(array $headers): HeadersFormatter {
        foreach ($headers as $k => $v) {
            $this->addHeader($k, $v);
        }
        return $this;
    }

    /**
     * 
     * @param array $headers
     * @return \zozlak\rest\HeadersFormatter
     */
    public function setHeaders(array $headers): HeadersFormatter {
        $this->headers = [];
        $this->addHeaders($headers);
        return $this;
    }

    /**
     * 
     * @return \zozlak\rest\HeadersFormatter
     */
    public function send(bool $throwEx = false): HeadersFormatter {
        return $this->sendStatus($throwEx)->sendHeaders($throwEx);
    }

    /**
     * 
     * @return \zozlak\rest\HeadersFormatter
     */
    public function sendStatus(bool $throwEx = false): HeadersFormatter {
        if ($this->checkHeadersSent($throwEx)) {
            header('HTTP/1.1 ' . $this->code . ' ' . $this->msg);
        }
        return $this;
    }

    /**
     * 
     * @return \zozlak\rest\HeadersFormatter
     */
    public function sendHeaders(bool $throwEx = false): HeadersFormatter {
        if ($this->checkHeadersSent($throwEx)) {
            foreach ($this->headers as $header => $values) {
                foreach ($values as $value) {
                    header($header . ': ' . $value);
                }
            }
        }
        return $this;
    }

    /**
     * 
     * @param string $location
     * @param int $code
     * @return \zozlak\rest\HeaderFormatter
     */
    public function setRedirect(string $location, int $code = 302): HeadersFormatter {
        $this->setStatus($code);
        $this->addHeader('location', $location);
        return $this;
    }

    /**
     * 
     * @param string $realm
     */
    public function setAuthBasic(string $realm): HeadersFormatter {
        $this->setStatus(401);
        $this->addHeader('www-authenticate', 'Basic realm="' . $realm . '"');
        return $this;
    }

    /**
     * 
     * @param bool $throwEx
     * @return bool
     * @throws RuntimeException
     */
    private function checkHeadersSent(bool $throwEx): bool {
        $file = $line = '';
        if (headers_sent($file, $line)) {
            if ($throwEx) {
                throw new RuntimeException("Headers already sent by $file $line", 500);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * 
     * @param string $name
     * @return string
     */
    private function sanitizeHeaderName(string $name): string {
        $name = explode('-', $name);
        foreach ($name as &$i) {
            $i = strtoupper(substr($i, 0, 1)) . substr($i, 1);
        }
        unset($i);
        return implode('-', $name);
    }

}
