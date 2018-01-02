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

use BadMethodCallException;
use Throwable;
use zozlak\util\Config;

/**
 * Description of HTTPController
 *
 * @author zozlak
 */
class HTTPController {

    /**
     *
     * @var bool 
     */
    static private $debug = false;

    /**
     *
     * @var string
     */
    static private $errorTemplate = <<<TEMPL
<h1>ERROR %d</h1>
<p>%s</p>
TEMPL;

    /**
     * 
     * @param string $msg
     * @param int $code
     * @param \Throwable $ex
     * @throws HTTPRequestException
     */
    static public function HTTPCode(string $msg = 'Internal Server Error',
                                    int $code = 500, Throwable $ex = null) {
        $splitted = explode("\n", $msg);
        header('HTTP/1.1 ' . $code . ' ' . trim($splitted[0]));
        printf(self::$errorTemplate, $code, $msg);

        if (self::$debug && $ex) {
            print_r($ex->getTrace());
        }
        throw new HTTPRequestException($msg, $code, $ex);
    }

    /**
     * 
     * @param int $severity
     * @param string $msg
     * @param string $file
     * @param int $line
     */
    static public function errorHandler(int $severity, string $msg,
                                        string $file, int $line) {
        $message = 'Internal Server Error';
        if (self::$debug) {
            $message = sprintf('%s %s %s %d', $severity, $msg, $file, $line);
        }
        self::HTTPCode($message, 500);
    }

    /**
     * 
     * @param bool $v
     */
    static public function setDebug($v) {
        self::$debug = $v ? true : false;
    }

    /**
     *
     * @var string
     */
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

    /**
     *
     * @var array
     */
    private $accept = array();

    /**
     *
     * @var string
     */
    private $authUser;

    /**
     *
     * @var string
     */
    private $authPswd;

    /**
     *
     * @var string
     */
    private $authRealm = 'Default realm';

    /**
     *
     * @var string
     */
    private $authMessage = 'This API requires authentication.';

    /**
     * 
     * @param string $namespace
     * @param \zozlak\util\Config $config
     */
    public function __construct(string $namespace = '', Config $config = null) {
        $this->namespace = '\\' . $namespace;
        $this->config    = $config;
    }

    /**
     * 
     * @param string $name
     * @return mixed
     */
    public function getConfig($name) {
        if ($this->config === null) {
            return null;
        }
        return $this->config->get($name);
    }

    /**
     * 
     * @return type
     */
    public function getAccept() {
        return $this->accept;
    }

    /**
     * 
     * @return string
     */
    public function getAuthUser(): string {
        if ($this->authUser === null) {
            $this->parseHttpBasic();
        }
        if ($this->authUser === null) {
            throw new UnauthorizedException();
        }
        return $this->authUser;
    }

    /**
     * 
     * @return string
     */
    public function getAuthPswd(): string {
        $this->getAuthUser(); // take care of initialization
        return $this->authPswd;
    }

    /**
     * 
     * @param string $realm
     * @param string $message
     */
    public function initAuth(string $realm, string $message): HTTPController {
        $this->authRealm   = $realm;
        $this->authMessage = $message;
        return $this;
    }

    /**
     * 
     * @param string $path
     */
    public function handleRequest(string $path): HTTPController {
        $this->parseAccept();

        // compose class and method from the request GET path parameter
        $path         = explode('/', preg_replace('|/$|', '', $path));
        $params       = new \stdClass();
        $handlerClass = '';
        foreach ($path as $key => $value) {
            if ($key % 2 == 0) {
                $handlerClass = $value;
            } else {
                $name          = $handlerClass . 'Id';
                $params->$name = $value;
            }
        }

        $handlerClass  = $this->namespace . '\\' . mb_strtoupper(mb_substr($handlerClass, 0, 1)) . mb_substr($handlerClass, 1);
        $handlerMethod = mb_strtolower(filter_input(\INPUT_SERVER, 'REQUEST_METHOD')) . (count($path) % 2 === 0 ? '' : 'Collection');

        try {
            $handler = new $handlerClass($params, $this);
            $handler->$handlerMethod($this->formatter);

            $this->formatter->end();
        } catch (BadMethodCallException $e) {
            self::HTTPCode($e->getMessage(), 501);
        } catch (UnauthorizedException $e) {
            header('WWW-Authenticate: Basic realm="' . $this->authRealm . '"');
            header('HTTP/1.0 401 Unauthorized');
            echo $this->authMessage;
        } catch (Throwable $e) {
            $code = $e->getCode();
            $code = $code >= 400 && $code <= 418 || $code == 451 || $code >= 500 && $code <= 511 ? $code : 500;
            self::HTTPCode($e->getMessage(), $code, $e);
        }
        return $this;
    }

    /**
     * 
     */
    private function parseAccept() {
        // at the moment only JSON is suported but it shows how you can add others
        $accept = trim(filter_input(\INPUT_SERVER, 'HTTP_ACCEPT'));
        if ($accept != '') {
            $tmp          = explode(',', $accept);
            $this->accept = array();
            foreach ($tmp as $i) {
                $i    = explode(';', $i);
                $i[0] = trim($i[0]);
                if (count($i) >= 2) {
                    $this->accept[$i[0]] = floatval(preg_replace('|[^.0-9]|', '', $i[1]));
                } else {
                    $this->accept[$i[0]] = 1;
                }
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

    /**
     * 
     * @param string $base
     * @param string $source
     */
    static public function parsePath(string $base,
                                     string $source = 'REDIRECT_URL'): string {
        $path = filter_input(INPUT_SERVER, $source);
        $path = mb_substr($path, mb_strlen($base) + (int) (substr($base, -1) != '/'));
        return $path;
    }

    /**
     * 
     */
    private function parseHttpBasic() {
        $this->authUser = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
        $this->authPswd = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : null;
    }

}
