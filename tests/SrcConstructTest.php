<?php
/******************************************************************************
 * php-src - A library that handles creation of objects and management of 
 * global services to help you refactor your legacy code base.
 *
 * Copyright (c) 2014, 2015 Richard Klees <richard.klees@rwth-aachen.de>
 *
 * This software is licensed under The MIT License. You should have received 
 * a copy of the along with the code.
 */

class Foo {
    public $a = null;
    public $b = null;
    public function __construct($a, $b) {
        $this->a = $a;
        $this->b = $b;
    }
}

class SrcBuildTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        $src = new Lechimp\Src\Src();
        $this->src = $src
        ->constructorFor("Foo", function($src, $a, $b) {
            return new Foo($a, $b);
        });
    }

    public function testConstruct() {
        $inst = $this->src->construct("Foo", "a", "b");
        $this->assertInstanceOf("Foo", $inst);
    }

    public function testParams() {
        $inst = $this->src->construct("Foo", "a", "b");
        $this->assertEquals("a", $inst->a);
        $this->assertEquals("b", $inst->b);
    }

    public function testConstructsFreshInstances() {
        $inst1 = $this->src->construct("Foo", "a", "b");
        $inst2 = $this->src->construct("Foo", "a", "b");
        $this->assertNotSame($inst1, $inst2);
    }

    public function testPassesSrc() {
        $tmp = array();
        $src = $this->src
        ->constructorFor("Bar", function($src) use (&$tmp) {
            $this->assertSame($src, $tmp["src"]);
        });
        $tmp["src"] = $src; 

        $src->construct("Bar");

        $this->assertSame($src, $tmp["src"]);
    }

    public function testDefaultConstructor() {
        $tmp = array( "called" => false );
        $src = (new Lechimp\Src\Src())
        ->defaultConstructor(function($src, $class_name, $params) use (&$tmp) {
            $tmp["called"] = true;
            $this->assertEquals("Foo", $class_name);
            $this->assertEquals("a", $params[0]);
            $this->assertEquals("b", $params[1]);
        });
        $src->construct("Foo", "a", "b");
        $this->assertTrue($tmp["called"]);
    }

    public function testNamedBeforeDefaultConstructor() {
        $tmp = array( "called" => false);
        $src = $this->src
        ->defaultConstructor(function($src, $class_name, $params) use ($tmp) {
            $tmp["called"] = false;
        }); 
        $src->construct("Foo", "a", "b");
        $this->assertFalse($tmp["called"]);
    }

    public function testPassesSrcToDefaultConstructor() {
        $tmp = array();
        $src = $this->src
        ->defaultConstructor(function($src, $class_name, $params) use (&$tmp) {
            $this->assertSame($src, $tmp["src"]);
        });
        $tmp["src"] = $src; 
        $src->construct("Bar");

        $this->assertSame($src, $tmp["src"]);
    }

    /**
     * @expectedException Lechimp\Src\Exceptions\UnknownClass
     */
    public function testUnknownClass() {
        $this->src->construct("Bar");
    }
}
