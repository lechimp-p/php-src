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

class SrcLazyTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->src = new Lechimp\Src\Src();
    }

    public function testRegisterAndRequest() {
        $src = $this->src
        ->service("foo", function($src) {
            return "bar";
        });

        $foo = $src->lazy("foo");
        $this->assertEquals("bar", $foo());
    }

    public function testResolvesCorrectly() {
        $src = $this->src
        ->service("foo", function($src) {
            return "foo";
        })
        ->service("bar", function($src) {
            return "bar";
        });

        $foo = $src->lazy("foo");
        $bar = $src->lazy("bar");

        $this->assertEquals("foo", $foo());
        $this->assertEquals("bar", $bar());
    }

    public function testPassesSrc() {
        $tmp = array();
        $src = $this->src
        ->service("foo", function($src) use (&$tmp) {
            $this->assertSame($tmp["src"], $src);
            $tmp["executed"] = true;
        });
        $tmp["src"] = $src;

        $foo = $src->lazy("foo");
        $foo();
        $this->assertTrue($tmp["executed"]);
    }

    public function testRequestServiceTwice() {
        $src = $this->src
        ->service("foo", function($src) {
            return new StdClass();
        });
        $one = $src->lazy("foo");
        $two = $src->lazy("foo");
        $this->assertSame($one(), $two());
    }

    /**
     * @expectedException Lechimp\Src\Exceptions\UnknownService
     */
    public function testUnknownService() {
        $foo = $this->src->lazy("foo"); 
        $foo(); 
    }

    /**
     * @expectedException Lechimp\Src\Exceptions\UnresolvableDependency
     */
    public function testUnresolvableDependency() {
        $src = $this->src
        ->service("foo", function($src) {
            $src->service("bar");
        })
        ->service("bar", function($src) {
            $src->service("foo");
        });
        $foo = $src->lazy("foo");
        $foo();
    }

    public function testIsLazy() {
        $src = $this->src
        ->service("foo", function($src) {
            $src->service("bar");
        })
        ->service("bar", function($src) {
            $src->service("foo");
        });
        $foo = $src->lazy("foo");
        // This would have raised if this was not lazy,
        // as wittnessed by testUnresolvableDependency.
        $this->assertTrue(true);
    }


    public function testIdenticalServiceAfterSrcUpdate() {
        $src = $this->src
        ->service("foo", function($src) {
            return new StdClass;
        });

        $one = $src->lazy("foo");

        $src2 = $src
        ->service("bar", function($src) {
            return "bar";
        });

        $two = $src2->lazy("foo");
        $this->assertSame($one(), $two());
    }

    public function testDifferentServiceAfterUpdate() {
        $src = $this->src
        ->service("foo", function ($src) {
            return "foo";
        });
        $foo = $src->lazy("foo");
        
        $src = $src
        ->service("foo", function ($src) {
            return "FOO";
        });
        $foo2 = $src->lazy("foo");
   
        $this->assertEquals($foo(), "foo");
        $this->assertEquals($foo2(), "FOO");
    }

    public function testTransitiveDifferentServiceAfterUpdate() {
        $src = $this->src
        ->service("foo", function($src) {
            return "foo";
        })
        ->service("bar", function($src) {
            return "bar";
        })
        ->service("foobar", function($src) {
            return $src->service("foo").$src->service("bar");
        });
        $foobar = $src->lazy("foobar");
        $this->assertEquals($foobar(), "foobar");
        
        $src = $src
        ->service("foo", function($src) {
            return "FOO";
        });
        $foobar2 = $src->lazy("foobar");
        $this->assertEquals($foobar2(), "FOObar");
    }

    public function testGetDependenciesOf() {
        $src = $this->src
        ->service("foo", function($src) {
            return "foo";
        })
        ->service("bar", function($src) {
            return "bar";
        })
        ->service("foobar", function($src) {
            $src->lazy("foo");
            $src->lazy("bar");
        });
        $deps = $src->dependenciesOf("foobar");
        $this->assertContains("foo", $deps);
        $this->assertContains("bar", $deps);
        $this->assertCount(2, $deps);
    }

    public function testGetDependenciesOfOnlyDirect() {
        $src = $this->src
        ->service("foo", function($src) {
        })
        ->service("bar", function($src) {
            $src->lazy("foo");
        })
        ->service("baz", function($src) {
            $src->lazy("bar");
        });
        $deps = $src->dependenciesOf("baz");
        $this->assertEquals($deps, array("bar"));
    }
}
