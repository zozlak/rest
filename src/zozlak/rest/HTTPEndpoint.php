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
 * Description of Endpoint
 *
 * @author zozlak
 */
class HTTPEndpoint {

    static private $args;

    static public function toUnderscore($name) {
        $parts = preg_split('/[A-Z]/', $name);
        $n     = mb_strlen($parts[0]);
        $res   = $parts[0];
        foreach ($parts as $k => $i) {
            if ($k !== 0) {
                $res .= '_' . strtolower(mb_substr($name, $n, 1)) . $i;
                $n   += mb_strlen($i) + 1;
            }
        }
        return $res;
    }

    static private function parseInput() {
        $type = strtolower(filter_input(\INPUT_SERVER, 'CONTENT_TYPE'));
        $data = file_get_contents("php://input");
        switch ($type) {
            case 'application/json':
                self::$args = (array) json_decode($data);
                break;
            default:
                parse_str($data, self::$args);
        }
    }

    /**
     *
     * @var type \zozlak\rest\HTTPController
     */
    protected $controller;

    public function __construct(\stdClass $path, HTTPController $controller) {
        $this->controller = $controller;
        foreach ($path as $key => $value) {
            $this->$key = $value;
        }
    }

    public function get(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function head(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function post(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function put(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function delete(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function trace(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function options(FormatterInterface $f) {
        $this->optionsGeneric(array('get', 'head', 'patch', 'post', 'put', 'trace'));
    }

    public function connect(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function patch(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function getCollection(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function postCollection(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function putCollection(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function deleteCollection(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function traceCollection(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function optionsCollection(FormatterInterface $f) {
        $this->optionsGeneric(array('getCollection', 'patchCollection', 'postCollection',
            'putCollection', 'traceCollection'));
    }

    public function connectCollection(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function patchCollection(FormatterInterface $f) {
        throw new \BadMethodCallException('Method not implemented');
    }

    protected function filterInput($name) {
        $method = filter_input(\INPUT_SERVER, 'REQUEST_METHOD');
        if ($method === 'GET' && count($_GET) > 0) {
            return filter_input(\INPUT_GET, $name);
        } else if ($method === 'POST' && count($_POST) > 0) {
            return filter_input(\INPUT_POST, $name);
        } else {
            if (self::$args === null) {
                self::parseInput();
            }
            if (!array_key_exists($name, self::$args)) {
                return null;
            }
            return self::$args[$name];
        }
    }

    protected function getConfig($name) {
        return $this->controller->getConfig($name);
    }

    private function optionsGeneric($methods) {
        $implemented = array('OPTIONS');
        foreach ($methods as $method) {
            if ($this->checkOverride($method)) {
                $implemented[] = strtoupper(str_replace('Collection', '', $method));
            }
        }
        $implemented = implode(', ', $implemented);
        header('Allow: ' . $implemented);
        header('Access-Control-Allow-Methods: ' . $implemented);
    }

    private function checkOverride($method) {
        $reflection = new \ReflectionMethod(get_class($this), $method);
        try {
            $reflection->getPrototype();
            return true;
        } catch (\ReflectionException $e) {
            
        }
        return false;
    }

}
