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

use Exception;
use Throwable;
use zozlak\util\Config;

/**
 * Description of HTTPController
 *
 * @author zozlak
 */
class HttpController {

    const ERR_HIDE  = 0;
    const ERR_THROW = 1;
    const ERR_SHOW  = 2;

    /**
     *
     * @var bool
     */
    static private $errorReported = false;

    /**
     * 
     * @param Throwable $ex
     * @param int $verbosity
     * @throws Throwable
     */
    static public function reportError(Throwable $ex,
                                       int $verbosity = self::ERR_HIDE) {
        if (!headers_sent() && !self::$errorReported) {
            $code                = $ex->getCode();
            $code                = $code < 300 || $code >= 600 ? 500 : $code;
            $msg                 = explode("\n", $ex->getMessage())[0];
            $msg                 = empty($msg) ? 'Internal Server Error' : $msg;
            header('HTTP/1.1 ' . $code . ' ' . $msg);
            self::$errorReported = true;
        }
        switch ($verbosity) {
            case self::ERR_THROW:
                throw $ex;
            case self::ERR_SHOW:
                if ($ex instanceof HttpRequestException && $ex->getPrevious()) {
                    print_r($ex->getPrevious());
                } else {
                    print_r($ex);
                }
                break;
        }
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
        $message = sprintf("Internal Server Error\n%d %s %d\n%s", $severity, $file, $line, $msg);
        throw new Exception($message, 500);
    }

    /**
     * Parses values with priorities list like the Accept or Language headers
     * @param string $value
     */
    static private function parsePriorityList(string $value): array {
        $tmp    = explode(',', trim($value));
        $parsed = [];
        foreach ($tmp as $i) {
            $i    = explode(';', $i);
            $i[0] = trim($i[0]);
            if (count($i) >= 2) {
                $parsed[$i[0]] = floatval(preg_replace('|[^.0-9]|', '', $i[1]));
            } else {
                $parsed[$i[0]] = 1;
            }
        }
        arsort($parsed);
        return array_keys($parsed);
    }

    /**
     *
     * @var string
     */
    private $namespace;

    /**
     *
     * @var string
     */
    private $baseUrl;

    /**
     *
     * @var string
     */
    private $urlSource;

    /**
     *
     * @var type \zozlak\util\Config
     */
    private $config;

    /**
     *
     * @var \zozlak\rest\DataFormatter
     */
    private $formatter;

    /**
     *
     * @var array
     */
    private $formattersMap = [
        'default'          => '\\zozlak\\rest\\JsonFormatter',
        'application/json' => '\\zozlak\\rest\\JsonFormatter',
        'text/csv'         => '\\zozlak\\rest\\CsvFormatter',
    ];

    /**
     *
     * @var array
     */
    private $accept = [];

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
    private $authRealm = 'auth realm';

    /**
     *
     * @var string
     */
    private $authMsg = 'Wrong username or password';

    /**
     *
     * @var \zozlak\rest\HeadersFormatter
     */
    private $headersFormatter;

    /**
     * 
     * @var array
     */
    private $routes = [];

    /**
     * 
     * @param string $namespace
     * @param string $baseUrl
     * @param string $urlSource
     */
    public function __construct(string $namespace = '', string $baseUrl = '',
                                string $urlSource = 'REDIRECT_URL') {
        $this->namespace        = '\\' . $namespace;
        $this->baseUrl          = parse_url($baseUrl);
        $this->urlSource        = $urlSource;
        $this->headersFormatter = new HeadersFormatter();

        $this->accept = self::parsePriorityList(filter_input(\INPUT_SERVER, 'HTTP_ACCEPT') ?? '');
    }

    /**
     * 
     * @param string $name
     * @return mixed
     */
    public function getConfig(string $name) {
        if ($this->config === null) {
            return null;
        }
        return $this->config->get($name, Config::NULL);
    }

    /**
     * 
     * @param Config $cfg
     */
    public function setConfig(Config $cfg): HttpController {
        $this->config = $cfg;
        return $this;
    }

    /**
     * 
     * @param array $routes associative array with keys being a request path
     *   regex and values being classes handling them
     */
    public function setStaticRoutes(array $routes): HttpController {
        $this->routes = $routes;
        return $this;
    }

    /**
     * 
     * @return array
     */
    public function getAccept(array $allowed = []): array {
        if (count($allowed) === 0) {
            return $this->accept;
        }

        $matching = [];
        foreach ($allowed as $t) {
            $tt = explode('/', $t);
            foreach ($this->accept as $n => $ac) {
                if ($t === $ac || $tt[0] . '/*' === $ac || $ac === '*/*') {
                    $matching[$t] = $n;
                    break;
                }
            }
        }
        asort($matching);

        return array_keys($matching);
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
     * @return \zozlak\rest\HttpController
     */
    public function setAuth(string $realm,
                            string $message = 'Wrong username or password'): HttpController {
        $this->authRealm = $realm;
        $this->authMsg   = $message;
        return $this;
    }

    /**
     * 
     * @param string $mime MIME type to be handled by the formatter (or 'default'
     *   to set a default formatter)
     * @param string $class formatter class
     * @return \zozlak\rest\HttpController
     */
    public function setFormatter(string $mime, string $class): HttpController {
        $this->formattersMap[$mime] = $class;
        return $this;
    }

    /**
     * 
     * @param array $map array with MIME types to formatters mapping (MIME type
     *   as keys, formatter class names as values; use 'default' key do denote
     *   a default formatter)
     * @return \zozlak\rest\HttpController
     */
    public function setFormatters(array $map): HttpController {
        $this->formattersMap = $map;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function handleRequest(): bool {
        $this->parseAccept();

        // compose class and method from the request GET path parameter
        $path         = $this->parsePath();
        $params       = new \stdClass();
        $handlerClass = '';
        foreach ($path as $key => $value) {
            if ($key % 2 == 0) {
                $handlerClass = $value;
            } else {
                $name          = mb_strtolower(mb_substr($handlerClass, 0, 1)) . mb_substr($handlerClass, 1) . 'Id';
                $params->$name = $value;
            }
        }

        $handlerClass  = $this->namespace . '\\' . mb_strtoupper(mb_substr($handlerClass, 0, 1)) . mb_substr($handlerClass, 1);
        $handlerMethod = mb_strtolower(filter_input(\INPUT_SERVER, 'REQUEST_METHOD')) . (count($path) % 2 === 0 ? '' : 'Collection');

        $pathStr = implode('/', $path);
        foreach ($this->routes as $route => $class) {
            if (preg_match($route, $pathStr)) {
                $handlerClass = $class;
                $handlerMethod = mb_strtolower(filter_input(\INPUT_SERVER, 'REQUEST_METHOD'));
                break;
            }
        }

        try {
            $handler = new $handlerClass($params, $this);
            $handler->$handlerMethod($this->formatter, $this->headersFormatter);

            $this->formatter->end();
        } catch (Throwable $ex) {
            $this->handleException($ex);
            return false;
        }
        return true;
    }

    /**
     * 
     * @return array
     */
    private function parsePath(): array {
        $path = $_SERVER[$this->urlSource];
        $skip = $this->baseUrl['path'] ?? '';
        $path = mb_substr($path, mb_strlen($skip));
        $path = preg_replace('|^/|', '', preg_replace('|/$|', '', $path));
        return explode('/', $path);
    }

    /**
     * 
     * @return string
     */
    public function getUrl(): string {
        $scheme = strtolower($this->baseUrl['scheme'] ?? filter_input(\INPUT_SERVER, 'REQUEST_SCHEME'));

        $host = $this->baseUrl['host'] ?? filter_input(\INPUT_SERVER, 'SERVER_NAME');

        $port = $this->baseUrl['port'] ?? filter_input(\INPUT_SERVER, 'SERVER_PORT');
        if ($port == 80 && $scheme === 'http' || $port == 443 && $scheme === 'https') {
            $port = '';
        } else {
            $port = ':' . $port;
        }

        $path = filter_input(\INPUT_SERVER, $this->urlSource);

        return $scheme . '://' . $host . $port . $path;
    }

    /**
     * 
     * @param Throwable $ex
     */
    private function handleException(Throwable $ex) {
        $code = (int) $ex->getCode();
        if (preg_match('/^Class .* not found$/', $ex->getMessage())) {
            $code = 404;
        }

        if ($ex instanceof UnauthorizedException) {
            $this->headersFormatter->
                setAuthBasic($this->authRealm)->
                sendStatus()->
                sendHeaders();
            echo $this->authMsg;
        } else {
            $httpCode            = $code < 400 || $code >= 600 ? 500 : $code;
            $this->headersFormatter->
                setStatus($httpCode, explode("\n", $ex->getMessage())[0])->
                sendStatus();
            self::$errorReported = true;
            throw new HttpRequestException('', 0, $ex);
        }
    }

    /**
     * 
     */
    private function parseAccept() {
        $format = $this->getAccept(array_keys($this->formattersMap));
        if (count($format) === 0) {
            $class = $this->formattersMap['default'];
        } else {
            $class = $this->formattersMap[$format[0]];
        }
        $this->formatter = new $class($this->headersFormatter, $this);
    }

    /**
     * 
     */
    private function parseHttpBasic() {
        $this->authUser = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
        $this->authPswd = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : null;
    }
}
