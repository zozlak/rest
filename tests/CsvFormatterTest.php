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

use zozlak\util\Config;

/**
 * Description of CsvFormatterTest
 *
 * @author zozlak
 */
class CsvFormatterTest extends \PHPUnit\Framework\TestCase {

    static private $ctrl;
    static private $hf;

    static public function setUpBeforeClass() {
        self::$ctrl = new HttpController();
        self::$hf   = new HeadersFormatter();
    }

    public function testBasic() {
        $this->expectOutputString('"a","b"
1.1,"x\\"y;z."
2.1,"xyz","abc"
');

        $df = new CsvFormatter(self::$hf, self::$ctrl);
        $df->append(['a' => 1.1, 'b' => 'x"y;z.']);
        $df->append([2.1, 'xyz', 'abc']);
        $df->end();
    }

    public function testColumn() {
        $this->expectOutputString('"a","b"
1.1,"x\\"y;z."
2.1,"xyz","abc"
');

        $df = new CsvFormatter(self::$hf, self::$ctrl);
        $df->initCollection();

        $df->initObject();
        $df->append(1.1, 'a');
        $df->append('x"y;z.', 'b');
        $df->closeObject();

        $df->initObject();
        $df->append(2.1);
        $df->append('abc', 'c');
        $df->append('xyz', 'b');
        $df->closeObject();

        $df->closeCollection();
        $df->end();
    }

    public function testDataString() {
        $this->expectOutputString('"a","b"
1.1,"x\\"y;z."
');

        $df = new CsvFormatter(self::$hf, self::$ctrl);
        $df->data('"a","b"');
        $df->data("\n");
        $df->data('1.1,"x\\"y;z."');
        $df->data("\n");
        $df->end();
    }

    public function testDataObject() {
        $this->expectOutputString('"a","b"
1.1,"x\\"y;z."
2.1,"xyz","abc"
');

        $df = new CsvFormatter(self::$hf, self::$ctrl);
        $df->data([
            ['a' => 1.1, 'b' => 'x"y;z.'],
            (object) [2.1, 'xyz', 'abc'],
        ]);
        $df->end();
    }

    public function testDataAppend() {
        $this->expectOutputString('x,y
1.1,"x\\"y;z."
2.1,"xyz","abc"
');

        $df = new CsvFormatter(self::$hf, self::$ctrl);
        $df->data("x,y\n");
        $df->append(['a' => 1.1, 'b' => 'x"y;z.']);
        $df->data('2.1,"xyz","abc"' . "\n");
        $df->end();
    }

    public function testCustomLocale() {
        $this->expectOutputString('"a";"b"
1,1;"x\\"y;z."
2,1;"xyz";"abc"
');

        $curLocale = setlocale(\LC_ALL, 0);
        setlocale(\LC_ALL, 'pl_PL.UTF-8');

        $df = new CsvFormatter(self::$hf, self::$ctrl);
        $df->append(['a' => 1.1, 'b' => 'x"y;z.']);
        $df->append([2.1, 'xyz', 'abc']);
        $df->end();

        setlocale(\LC_ALL, $curLocale);
    }

    public function testCustomSettings() {
        $this->expectOutputString('?a?|?b?
1:1|?x@?y;z.?
2:1|?xyz?|?abc?
');

        $cfg  = new Config(__DIR__ . '/config.ini');
        $cfg->set('DataFormatterCsvEnclosure', '?');
        $cfg->set('DataFormatterCsvEscape', '@');
        $cfg->set('DataFormatterCsvDelimiter', '|');
        $cfg->set('DataFormatterCsvDecimal', ':');
        $ctrl = new HttpController();
        $ctrl->setConfig($cfg);

        $df = new CsvFormatter(self::$hf, $ctrl);
        $df->append(['a' => 1.1, 'b' => 'x?y;z.']);
        $df->append([2.1, 'xyz', 'abc']);
        $df->end();
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage CsvFormatter can handle only one collection and it must be a top-level one
     */
    public function testCollectionInitTwice() {
        $df = new CsvFormatter(self::$hf, self::$ctrl);
        $df->initCollection();
        $df->initCollection();
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage CsvFormatter does not support collection key
     */
    public function testCollectionInitWithKey() {
        $df = new CsvFormatter(self::$hf, self::$ctrl);
        $df->initCollection('key');
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage CsvFormatter can handle only one collection and it must be a top-level one
     */
    public function testCollectionInitAfterAppend() {
        $df = new CsvFormatter(self::$hf, self::$ctrl);
        $df->append([1]);
        $df->initCollection();
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage CsvFormatter does not handle nested objects
     */
    public function testCollectionInitObjectTwice() {
        $df = new CsvFormatter(self::$hf, self::$ctrl);
        $df->initObject();
        $df->append(1, 'a');
        $df->initObject();
    }

}
