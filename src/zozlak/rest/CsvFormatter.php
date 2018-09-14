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

use BadMethodCallException;

/**
 * Provides CSV output.
 * 
 * As CSV is a simple format doesn't support nested collections nor objects.
 * 
 * Calling initCollection() is optional as CSV is always a one top-level collection.
 * 
 * Header is generated automatically from the first row of data (but can be also
 * set manually).
 * 
 *
 * @author zozlak
 */
class CsvFormatter extends DataFormatter {

    const ROW    = 1;
    const COLUMN = 2;

    private $countInitCollection = 0;
    private $countAppendRow      = 0;
    private $mode                = self::ROW;
    private $header              = [];
    private $row                 = [];
    private $colNotNull;
    private $buffer;
    private $delimiter;
    private $enclosure;
    private $escape;
    private $decimal;

    public function __construct(HeadersFormatter $hf, HttpController $ctrl) {
        parent::__construct($hf, $ctrl);

        $this->enclosure = $ctrl->getConfig('DataFormatterCsvEnclosure') ?? '"';
        $this->escape    = $ctrl->getConfig('DataFormatterCsvEscape') ?? '\\';

        // set delimiter and decimal point based on locale settings
        $settings  = localeconv();
        $reqLocale = locale_accept_from_http(filter_input(\INPUT_SERVER, 'HTTP_ACCEPT_LANGUAGE'));
        if ($reqLocale) {
            $curLocale = setlocale(\LC_ALL, 0);
            if(setlocale(\LC_ALL, $reqLocale . '.UTF-8') === false) {
                setlocale(\LC_ALL, $reqLocale);
            }
            $settings  = localeconv();
            setlocale(\LC_ALL, $curLocale);
        }
        $this->delimiter = $settings['decimal_point'] === ',' ? ';' : ',';

        // settings from config.ini overwrite ones from locale settings
        if ($ctrl->getConfig('DataFormatterCsvDelimiter')) {
            $this->delimiter = $ctrl->getConfig('DataFormatterCsvDelimiter');
        }
        if ($ctrl->getConfig('DataFormatterCsvDecimal')) {
            $this->decimal = $ctrl->getConfig('DataFormatterCsvDecimal');
        }
    }

    protected function getHeaders(): array {
        return ['Content-Type' => 'text/csv; charset=utf-8'];
    }

    protected function sendBuffer(): int {
        echo $this->buffer;
        $this->buffer = '';
        return 0;
    }

    public function data($d): DataFormatter {
        if (is_scalar($d)) {
            $d            = (string) $d;
            $this->buffer .= $d;
            if ($this->countInitCollection === 0) {
                $this->countInitCollection = 1;
            }
            if ($this->countAppendRow === 0) {
                $this->countAppendRow = 1;
            }
            $this->incBufferSize(strlen($d));
        } else {
            $d = (array) $d;
            foreach ($d as $row) {
                $this->append($row);
            }
        }
        return $this;
    }

    public function append($d, string $key = ''): DataFormatter {
        if ($this->mode === self::COLUMN) {
            $this->appendColumn($d, $key);
        } else {
            $this->appendRow($d, $key);
        }
        return $this;
    }

    public function closeCollection(): DataFormatter {
        if ($this->countInitCollection !== 1) {
            throw new BadMethodCallException('Collection is not opened');
        }
        $this->countInitCollection = 2;
        return $this;
    }

    public function closeObject(): DataFormatter {
        if ($this->mode !== self::COLUMN) {
            throw new BadMethodCallException('Object not initialized');
        }
        $this->mode = self::ROW;
        $this->append($this->row);
        $this->row  = [];
        return $this;
    }

    public function initCollection(string $key = ''): DataFormatter {
        if ($this->countInitCollection > 0 || $this->countAppendRow > 0) {
            throw new BadMethodCallException('CsvFormatter can handle only one collection and it must be a top-level one');
        }
        if ($key !== '') {
            throw new BadMethodCallException('CsvFormatter does not support collection key');
        }
        $this->countInitCollection++;
        return $this;
    }

    public function initObject(string $key = ''): DataFormatter {
        if ($this->mode !== self::ROW) {
            throw new BadMethodCallException('CsvFormatter does not handle nested objects');
        }
        $this->mode       = self::COLUMN;
        $this->colNotNull = 0;
        $this->row        = array_fill(0, count($this->header), null);
        if ($key !== '') {
            $this->row[0] = $key;
        }
        return $this;
    }

    public function setHeader(array $header, bool $sanitize = false): DataFormatter {
        if ($this->countAppendRow > 0) {
            throw new BadMethodCallException('can not set header - rows already added');
        }

        if ($sanitize) {
            $this->header = [];
            foreach ($header as $i) {
                $this->header[] = !is_int($i) ? (string) $i : '';
            }
        } else {
            $this->header = $header;
        }

        return $this;
    }

    /**
     * Formats row of data as CSV
     * @param array $row
     * @return string
     */
    private function asCsv(array $row): string {
        $out = '';
        $n   = 0;
        foreach ($row as $i) {
            $numeric = is_numeric($i);
            $i       = (string) $i;
            if (!$numeric) {
                $i = $this->enclosure . str_replace($this->enclosure, $this->escape . $this->enclosure, $i) . $this->enclosure;
            } else if ($this->decimal !== null) {
                $i = str_replace('.', $this->decimal, $i);
            }
            $out .= ($n > 0 ? $this->delimiter : '') . $i;
            $n++;
        }
        $out .= "\n";
        return $out;
    }

    private function appendRow($d, string $key = '') {
        $d = (array) $d;
        if ($this->countAppendRow === 0) {
            if (count($this->header) === 0) {
                $this->setHeader(array_keys($d), true, true);
                if ($key !== '') {
                    array_unshift($this->header, 'key');
                }
            }
            $this->appendRowReal($this->header);
        }

        $this->countAppendRow ++;
        $this->appendRowReal($d, $key);
    }

    private function appendRowReal($d, string $key = '') {
        $d = (array) $d;
        if ($key !== '') {
            array_unshift($d, $key);
        }
        $row          = $this->asCsv($d);
        $this->buffer .= $row;
        $this->incBufferSize(strlen($row));
    }

    private function appendColumn($d, string $key = '') {
        while (($this->row[$this->colNotNull] ?? null) !== null) {
            $this->colNotNull++;
        }
        $pos = $this->colNotNull;
        if ($key !== '') {
            $pos = array_search($key, $this->header);
            if ($pos === false) {
                $this->header[] = $key;
                $pos            = count($this->header) - 1;
            }
        }
        $this->row[$pos] = $d;
    }

}
