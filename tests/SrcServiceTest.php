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
        });
        $tmp["src"] = $src;
        $src->service("foo");
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
