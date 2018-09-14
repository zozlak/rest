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

/**
 * Description of JsonFormatterTest
 *
 * @author zozlak
 */
class JsonFormatterTest extends \PHPUnit\Framework\TestCase {

    static private $ctrl;
    static private $hf;

    static public function setUpBeforeClass() {
        self::$ctrl = new HttpController();
        self::$hf   = new HeadersFormatter();        
    }

    public function testCollectionAppend() {
        $this->expectOutputString('[{"a":1,"b":["x","y"]},{"x":1,"y":2}]');
        
        $df = new JsonFormatter(self::$hf, self::$ctrl);
        $df->initCollection();
        $df->append(['a' => 1, 'b' => ['x', 'y']]);
        $df->append((object) ['x' => 1, 'y' => 2]);
        $df->closeCollection();
        $df->end();
    }
    public function testData() {
        $this->expectOutputString('[{"a":1,"b":["x","y"]},{"x":1,"y":2}]');
        
        $df = new JsonFormatter(self::$hf, self::$ctrl);
        $df->data([
            ['a' => 1, 'b' => ['x', 'y']],
            (object) ['x' => 1, 'y' => 2],
        ]);
        $df->end();
    }
    public function testAppend() {
        $this->expectOutputString('[{"a":1,"b":["x","y"]},{"x":1,"y":2}]');
        
        $df = new JsonFormatter(self::$hf, self::$ctrl);
        $df->initCollection();
        
        $df->initObject();
        $df->append(1, 'a');
        $df->initCollection('b');
        $df->append('x');
        $df->append('y');
        $df->closeCollection();
        $df->closeObject();
        
        $df->initObject();
        $df->append(1, 'x');
        $df->append(2, 'y');
        $df->closeObject();
        
        $df->closeCollection();
        $df->end();
    }
}
