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

class SrcFactoryTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        $src = new Lechimp\Src\Src();
        $this->src = $src
        ->factory("Foo", function($src, $a, $b) {
            return new Foo($a, $b);
        });
    }

    public function testConstruct() {
        $factory = $this->src->factory("Foo");
        $inst = $factory("a", "b");
        $this->assertInstanceOf("Foo", $inst);
    }

    public function testParams() {
        $factory = $this->src->factory("Foo");
        $inst = $factory("a", "b");
        $this->assertEquals("a", $inst->a);
        $this->assertEquals("b", $inst->b);
    }

    public function testConstructsFreshInstances() {
        $factory = $this->src->factory("Foo");
        $inst1 = $factory("a", "b");
        $inst2 = $factory("a", "b");
        $this->assertNotSame($inst1, $inst2);
    }

    public function testPassesSrc() {
        $tmp = array();
        $src = $this->src
        ->factory("Bar", function($src) use (&$tmp) {
            $this->assertSame($src, $tmp["src"]);
        });
        $tmp["src"] = $src; 

        $factory = $src->factory("Bar");
        $factory();

        $this->assertSame($src, $tmp["src"]);
    }

    public function testDefaultFactory() {
        $tmp = array( "called" => false );
        $src = (new Lechimp\Src\Src())
        ->defaultFactory(function($src, $class_name, $params) use (&$tmp) {
            $tmp["called"] = true;
            $this->assertEquals("Foo", $class_name);
            $this->assertEquals(array("a", "b"), $params);
        });
        $factory = $src->factory("Foo");
        $factory("a", "b");
        $this->assertTrue($tmp["called"]);
    }

    public function testNamedBeforeDefaultFactory() {
        $tmp = array( "called" => false);
        $src = $this->src
        ->defaultFactory(function($src, $class_name, $params) use ($tmp) {
            $tmp["called"] = false;
        }); 
        $factory = $src->factory("Foo");
        $factory("a", "b");
        $this->assertFalse($tmp["called"]);
    }

    public function testPassesSrcToDefaultFactory() {
        $tmp = array();
        $src = $this->src
        ->defaultFactory(function($src, $class_name, $params) use (&$tmp) {
            $this->assertSame($src, $tmp["src"]);
        });
        $tmp["src"] = $src; 
        $factory = $src->factory("Bar");
        $factory();

        $this->assertSame($src, $tmp["src"]);
    }

    /**
     * @expectedException Lechimp\Src\Exceptions\UnknownClass
     */
    public function testUnknownClass() {
        $this->src->factory("Bar");
    }

    public function testIsImmutable() {
        $src = $this->src
        ->factory("Bar", function($src) {
            return "foo";
        });

        $this->assertNotSame($src, $this->src);
        try {
            $factory = $this->src->factory("Bar");
            $factory();
            $raised = false;
        }
        catch (Lechimp\Src\Exceptions\UnknownClass $e) {
            $raised = true;
        }
        $this->assertTrue($raised);
    }

    public function testIsImmutableAfterDefaultFactoryUpdate() {
        $src = $this->src
        ->defaultFactory(function($src, $class_name, $args) {
            return "foo";
        });

        $this->assertNotSame($src, $this->src);
        try {
            $factory = $this->src->factory("Bar");
            $factory();
            $raised = false;
        }
        catch (Lechimp\Src\Exceptions\UnknownClass $e) {
            $raised = true;
        }
        $this->assertTrue($raised);
    }

}
