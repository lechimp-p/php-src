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

class SrcServiceTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->src = new Lechimp\Src\Src();
    }

    public function testRegisterAndRequest() {
        $src = $this->src
        ->service("foo", function($src) {
            return "bar";
        });

        $this->assertEquals("bar", $src->service("foo"));
    }

    public function testResolvesCorrectly() {
        $src = $this->src
        ->service("foo", function($src) {
            return "foo";
        })
        ->service("bar", function($src) {
            return "bar";
        });

        $this->assertEquals("foo", $src->service("foo"));
        $this->assertEquals("bar", $src->service("bar"));
    }

    public function testPassesSrc() {
        $tmp = array();
        $src = $this->src
        ->service("foo", function($src) use (&$tmp) {
            $this->assertSame($tmp["src"], $src);
            $tmp["executed"] = true;
        });
        $tmp["src"] = $src;
        $src->service("foo");
        $this->assertTrue($tmp["executed"]);
    }

    public function testRequestServiceTwice() {
        $src = $this->src
        ->service("foo", function($src) {
            return new StdClass();
        });
        $one = $src->service("foo");
        $two= $src->service("foo");
        $this->assertSame($one, $two);
    }

    /**
     * @expectedException Lechimp\Src\Exceptions\UnknownService
     */
    public function testUnknownService() {
        $this->src->service("foo"); 
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
        $src->service("foo");
    }


    public function testIsImmutable() {
        $src = $this->src
        ->service("foo", function($src) {
            return "foo";
        });

        $this->assertNotSame($src, $this->src);
        try {
            $this->src->service("foo");
            $raised = false;
        }
        catch (Lechimp\Src\Exceptions\UnknownService $e) {
            $raised = true;
        }
        $this->assertTrue($raised);
    }

    public function testIdenticalServiceAfterSrcUpdate() {
        $src = $this->src
        ->service("foo", function($src) {
            return new StdClass;
        });

        $one = $src->service("foo");

        $src2 = $src
        ->service("bar", function($src) {
            return "bar";
        });

        $two = $src2->service("foo");
        $this->assertSame($one, $two);
    }

    public function testDifferentServiceAfterUpdate() {
        $src = $this->src
        ->service("foo", function ($src) {
            return "foo";
        });
        $foo = $src->service("foo");
        
        $src = $src
        ->service("foo", function ($src) {
            return "FOO";
        });
        $foo2 = $src->service("foo");
   
        $this->assertEquals($foo, "foo");
        $this->assertEquals($foo2, "FOO");
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
        $foobar = $src->service("foobar");
        $this->assertEquals($foobar, "foobar");
        
        $src = $src
        ->service("foo", function($src) {
            return "FOO";
        });
        $foobar2 = $src->service("foobar");
        $this->assertEquals($foobar2, "FOObar");
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
            $src->service("foo");
            $src->service("bar");
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
            $src->service("foo");
        })
        ->service("baz", function($src) {
            $src->service("bar");
        });
        $deps = $src->dependenciesOf("baz");
        $this->assertEquals($deps, array("bar"));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSrcIsInvalidServiceName() {
        $this->src->service("Src", function($src) {
            return 10;
        });
    }

    public function testSrcServiceIsSrc() {
        $src2 = $this->src->service("Src");
        $this->assertSame($this->src, $src2);
    }
}
