<?php

namespace TQ\Shamir\Tests;


use PHPUnit\Framework\TestCase;
use TQ\Shamir\Algorithm\Algorithm;
use TQ\Shamir\Algorithm\RandomGeneratorAware;
use TQ\Shamir\Algorithm\Shamir;
use TQ\Shamir\Random\Generator;
use TQ\Shamir\Secret;

class SecretTest extends TestCase
{
    protected $secretUtf8 = 'Lorem ipsum dolor sit असरकारक संस्थान δισεντιας قبضتهم нолюёжжэ 問ナマ業71職げら覧品モス変害';
    protected $secretAscii;

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Call protected/private static method of a class.
     *
     * @param string $class Name of the class
     * @param string $methodName Static method name to call
     * @param array $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeStaticMethod($class, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass($class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $parameters);
    }

    protected function setUp()
    {
        Secret::setRandomGenerator(null);
        Secret::setAlgorithm(null);
    }

    protected function tearDown()
    {
        Secret::setRandomGenerator(null);
        Secret::setAlgorithm(null);
    }


    public function convertBaseProvider()
    {
        return array(
            # dec -> dec
            array(0, '0123456789', '0123456789', 0),
            array(100, '0123456789', '0123456789', 100),
            array(999, '0123456789', '0123456789', 999),

            # dec -> bin
            array(0, '0123456789', '01', 0),
            array(100, '0123456789', '01', "1100100"),
            array(999, '0123456789', '01', "1111100111"),
            # dec -> oct
            array(0, '0123456789', '01234567', 0),
            array(100, '0123456789', '01234567', "144"),
            array(999, '0123456789', '01234567', "1747"),
            # dec -> hex
            array(0, '0123456789', '0123456789abcdef', 0),
            array(100, '0123456789', '0123456789abcdef', "64"),
            array(999, '0123456789', '0123456789abcdef', "3e7"),

            # bin -> dec
            array(0, '01', '0123456789', 0),
            array("11111", '01', '0123456789', 31),
            array("101010101010", '01', '0123456789', 2730),
            # oct -> dec
            array(0, '01234567', '0123456789', 0),
            array("100", '01234567', '0123456789', 64),
            array("77777", '01234567', '0123456789', 32767),
            # dec -> hex
            array(0, '0123456789abcdef', '0123456789', 0),
            array('ffff', '0123456789abcdef', '0123456789', 65535),
            array('abcdef0123', '0123456789abcdef', '0123456789', 737894400291),
        );
    }

    /**
     * @dataProvider convertBaseProvider
     */
    public function testConvBase($numberInput, $fromBaseInput, $toBaseInput, $expected)
    {
        $returnVal = $this->invokeStaticMethod(
            'TQ\Shamir\Algorithm\Shamir',
            'convBase',
            array($numberInput, $fromBaseInput, $toBaseInput)
        );
        $this->assertEquals($expected, $returnVal);
    }

    public function testReturnsDefaultAlgorithm()
    {
        $this->assertInstanceOf('\TQ\Shamir\Algorithm\Algorithm', Secret::getAlgorithm());
    }

    public function testReturnsDefaultRandomGenerator()
    {
        $this->assertInstanceOf('\TQ\Shamir\Random\Generator', Secret::getRandomGenerator());
    }

    public function testSetNewAlgorithmReturnsOld()
    {
        $current = Secret::getAlgorithm();
        /** @var \PHPUnit_Framework_MockObject_MockObject|Algorithm $new */
        $new = $this->getMockBuilder('\TQ\Shamir\Algorithm\Algorithm')->setMethods(['share', 'recover'])->getMock();

        $this->assertSame($current, Secret::setAlgorithm($new));
        $this->assertSame($new, Secret::getAlgorithm());

        // don't return old one with returnOld = false
        $this->assertSame(null, Secret::setAlgorithm($new, false));
    }

    public function testSetNewRandomGeneratorReturnsOld()
    {
        $current = Secret::getRandomGenerator();
        /** @var \PHPUnit_Framework_MockObject_MockObject|Generator $new */
        $new = $this->getMockBuilder('\TQ\Shamir\Random\Generator')->setMethods(['getRandomInt'])->getMock();

        $this->assertSame($current, Secret::setRandomGenerator($new));
        $this->assertSame($new, Secret::getRandomGenerator());
    }

    public function testSetNewRandomGeneratorUpdatesGeneratorOnAlgorithm()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Generator $new */
        $new = $this->getMockBuilder('\TQ\Shamir\Random\Generator')->setMethods(['getRandomInt'])->getMock();

        Secret::setRandomGenerator($new);
        $algorithm = Secret::getAlgorithm();
        if (!$algorithm instanceof RandomGeneratorAware) {
            $this->markTestSkipped('Algorithm does not implement RandomGeneratorAware');
        }
        $this->assertSame($new, $algorithm->getRandomGenerator());
    }

    public function provideShareAndRecoverMultipleBytes()
    {
        if (empty($this->secretAscii)) {
            // generate string with all ASCII chars
            $this->secretAscii = '';
            for ($i = 0; $i < 256; ++$i) {
                $this->secretAscii .= chr($i);
            }
        }

        $return = array();
        // add full ASCII charset
        for ($bytes = 1; $bytes < 8; ++$bytes) {
            $return[] = array($this->secretAscii, $bytes);
        }
        // add some unicode chars
        for ($bytes = 1; $bytes < 8; ++$bytes) {
            $return[] = array($this->secretUtf8, $bytes);
        }
        return $return;
    }

    /**
     * @dataProvider provideShareAndRecoverMultipleBytes
     */
    public function testShareAndRecoverMultipleBytes($secret, $bytes)
    {
        $shamir = new Shamir();
        $shamir->setChunkSize($bytes);

        $shares = $shamir->share($secret, 2, 2);

        // create new instance to check if all necessary values
        // are set with the keys
        $shamir = new Shamir();
        $recover = $shamir->recover(array_slice($shares, 0, 2));
        $this->assertSame($secret, $recover);
    }

    public function testShareAndRecoverShuffleKeys()
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 50, 2);

        for ($i = 0; $i < count($shares); ++$i) {
            for ($j = $i + 1; $j < count($shares); ++$j) {
                $recover = Secret::recover(array($shares[$i], $shares[$j]));
                $this->assertSame($secret, $recover);
            }
        }
    }

    public function testShareAndRecoverOneByte()
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 10, 2);

        $recover = Secret::recover(array_slice($shares, 0, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 2, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 4, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 5, 4));
        $this->assertSame($secret, $recover);

        // test different length of secret
        $template = 'abcdefghijklmnopqrstuvwxyz';
        for ($i = 1; $i <= 8; ++$i) {
            $secret = substr($template, 0, $i);
            $shares = Secret::share($secret, 3, 2);

            $recover = Secret::recover(array_slice($shares, 0, 2));
            $this->assertSame($secret, $recover);
        }
    }

    public function testShareAndRecoverTwoBytes()
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 260, 2);

        $recover = Secret::recover(array_slice($shares, 0, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 2, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 4, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 6, 4));
        $this->assertSame($secret, $recover);

        // test different length of secret
        $template = 'abcdefghijklmnopqrstuvwxyz';
        for ($i = 1; $i <= 8; ++$i) {
            $secret = substr($template, 0, $i);
            $shares = Secret::share($secret, 260, 2);

            $recover = Secret::recover(array_slice($shares, 0, 2));
            $this->assertSame($secret, $recover);
        }

    }

    public function testShareAndRecoverThreeBytes()
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 75000, 2);

        $recover = Secret::recover(array_slice($shares, 0, 2));
        $this->assertSame($secret, $recover);
    }

    /**
     * @dataProvider provideShareAndRecoverMultipleBytes
     */
    public function testChunkSizeGetter($secret, $bytes)
    {
        $shamir = new Shamir();
        $shamir->setChunkSize($bytes);

        $this->assertSame($shamir->getChunkSize(), $bytes);
    }

    /**
     * @expectedException OutOfRangeException
     */
    public function testSetChunkSizeException()
    {
        $shamir = new Shamir();
        $shamir->setChunkSize(99);

    }

    /**
     * @expectedException OutOfRangeException
     */
    public function testShareAndShareSmallerThreshold()
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 1, 2);
    }


}
