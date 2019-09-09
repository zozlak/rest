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

use stdClass;

/**
 * Description of Endpoint
 *
 * @author zozlak
 */
class HttpEndpoint {

    /**
     *
     * @var \stdClass
     */
    static private $args;

    /**
     * 
     * @param string $name
     * @return string
     */
    static public function toUnderscore(string $name): string {
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

    /**
     * 
     */
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
    private $controller;

    /**
     * 
     * @param \stdClass $path
     * @param \zozlak\rest\HttpController $controller
     */
    public function __construct(stdClass $path, HttpController $controller) {
        $this->controller = $controller;
        foreach ($path as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function get(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function head(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function post(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function put(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function delete(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function trace(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     */
    public function options(DataFormatter $f, HeadersFormatter $h) {
        $this->optionsGeneric(array('get', 'head', 'patch', 'post', 'put', 'trace', 'connect'));
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function connect(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function patch(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function getCollection(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function postCollection(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function putCollection(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function deleteCollection(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function traceCollection(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     */
    public function optionsCollection(DataFormatter $f, HeadersFormatter $h) {
        $this->optionsGeneric(array('getCollection', 'patchCollection', 'postCollection',
            'putCollection', 'traceCollection', 'connectCollection'));
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function connectCollection(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @param \zozlak\rest\DataFormatter $f
     * @throws \BadMethodCallException
     */
    public function patchCollection(DataFormatter $f, HeadersFormatter $h) {
        throw new HttpRequestException('Method not implemented', 501);
    }

    /**
     * 
     * @return string
     */
    protected function getUrl(): string {
        return $this->controller->getUrl();
    }


    /**
     * 
     * @param string $name
     * @return mixed
     */
    protected function filterInput(string $name) {
        $method = filter_input(\INPUT_SERVER, 'REQUEST_METHOD');
        if (in_array($method, ['GET', 'HEAD']) && count($_GET) > 0) {
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

    /**
     * 
     * @param string $name
     * @return mixed
     */
    protected function getConfig(string $name) {
        return $this->controller->getConfig($name);
    }

    /**
     * 
     * @return array
     */
    protected function getAccept(array $allowed = []): array {
        return $this->controller->getAccept($allowed);
    }


    /**
     * 
     * @return string
     */
    protected function getAuthUser(): string {
        return $this->controller->getAuthUser();
    }
    
    /**
     * 
     * @return string
     */
    protected function getAuthPswd(): string {
        return $this->controller->getAuthPswd();
    }

    /**
     * 
     * @param array $methods
     */
    private function optionsGeneric(array $methods) {
        $implemented = array('OPTIONS');
        foreach ($methods as $method) {
            if ($this->checkOverride($method)) {
                $implemented[] = strtoupper(str_replace('Collection', '', $method));
            }
        }
        if (count($implemented) === 0) {
            throw new HttpRequestException('Not Found', 404);
        }
        $implemented = implode(', ', $implemented);
        header('Allow: ' . $implemented);
        header('Access-Control-Allow-Methods: ' . $implemented);
    }

    /**
     * 
     * @param string $method
     * @return boolean
     */
    private function checkOverride(string $method): bool {
        $reflection = new \ReflectionMethod(get_class($this), $method);
        try {
            $reflection->getPrototype();
            return true;
        } catch (\ReflectionException $e) {
            
        }
        return false;
    }

}
