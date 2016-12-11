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
 * Description of HTTPContoller
 *
 * @author zozlak
 */
class HTTPContoller {

    static private $debug = false;
    static private $errorTemplate = <<<TEMPL
<h1>ERROR %d</h1>
<p>%s</p>
TEMPL;

    static public function HTTPCode($msg = 'Internal Server Error', $code = 500, $ex = null) {
        $splitted = explode("\n", $msg);
        header('HTTP/1.1 ' . $code . ' ' . trim($splitted[0]));
        printf(self::$errorTemplate, $code, $msg);

        if (self::$debug && $ex) {
            print_r($ex->getTrace());
        }
        exit();
    }

    static public function unauthorized($msg = 'Unauthorized') {
        self::HTTPCode($msg, 401);
    }

    static public function errorHandler($severity, $msg, $file, $line) {
        $message = 'Internal Server Error';
        if (self::$debug) {
            $message = sprintf('%s %s', $severity, $msg);
        }
        self::HTTPCode($message, 500);
    }

    static public function setDebug($v) {
        self::$debug = $v ? true : false;
    }

    private $namespace;

    /**
     *
     * @var type \zozlak\util\Config
     */
    private $config;

    /**
     *
     * @var \zozlak\rest\FormatterInterface
     */
    private $formatter;
    private $accept = array();

    public function __construct($namespace = '', $config = null) {
        $this->namespace = '\\' . $namespace;
        $this->config = $config;
    }

    public function getConfig($name) {
        if ($this->config === null) {
            return null;
        }
        return $this->config->get($name);
    }

    public function getAccept() {
        return $this->accept;
    }

    public function handleRequest($path) {
        $this->parseAccept();

        // compose class and method from the request GET path parameter
        $path = explode('/', preg_replace('|/$|', '', $path));
        $params = new \stdClass();
        $handlerClass = '';
        foreach ($path as $key => $value) {
            if ($key % 2 == 0) {
                $handlerClass = $value;
            } else {
                $name = $handlerClass . 'Id';
                $params->$name = $value;
            }
        }

        $handlerClass = $this->namespace . '\\' . mb_strtoupper(mb_substr($handlerClass, 0, 1)) . mb_substr($handlerClass, 1);
        $handler = new $handlerClass($params, $this);

        $handlerMethod = mb_strtolower(filter_input(\INPUT_SERVER, 'REQUEST_METHOD')) . (count($path) % 2 === 0 ? '' : 'Collection');
        try {
            $handler->$handlerMethod($this->formatter);
            $this->formatter->end();
        } catch (\BadMethodCallException $e) {
            self::HTTPCode($e->getMessage(), 501);
        } catch (UnauthorizedException $e) {
            self::HTTPCode($e->getMessage(), 401);
        } catch (\Exception $e) {
            $code = $e->getCode();
            $code = $code >= 400 && $code <= 418 || $code == 451 || $code >= 500 && $code <= 511 ? $code : 500;
            self::HTTPCode($e->getMessage(), $code);
        }
    }

    private function parseAccept() {
        // at the moment only JSON is suported but it shows how you can add others
        $accept = trim(filter_input(\INPUT_SERVER, 'HTTP_ACCEPT'));
        if ($accept != '') {
            $tmp = explode(',', $accept);
            $this->accept = array();
            foreach ($tmp as $i) {
                $this->accept[trim($i[0])] = count($i) > 1 ? floatval($i[1]) : 1;
            }
            arsort($this->accept);
            foreach (array_keys($this->accept) as $k) {
                switch ($k) {
                    default:
                        $this->formatter = new JSONFormatter();
                        break 2;
                }
            }
        } else {
            $this->formatter = new JSONFormatter();
        }
    }

}
