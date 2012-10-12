<?php
/**
 * Тесты
 *
 * @version 0.1
 * @copyright Михаил Красильников <mihalych@vsepofigu.ru>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @author Михаил Красильников <mihalych@vsepofigu.ru>
 *
 * Copyright 2012 Mikhail Krasilnikov (Михаил Красильников).
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package Bedoved
 */

require_once __DIR__ . '/../src/Bedoved.php';

class Bedoved_Test extends PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        restore_error_handler();
    }

    /**
     * @covers Bedoved::enableErrorConversion
     */
    public function testEnableErrorConversion()
    {
        $mask = new ReflectionProperty('Bedoved', 'errorConversionMask');
        $mask->setAccessible(true);

        $b = new Bedoved();
        $b->enableErrorConversion();
        $this->assertGreaterThan(0, $mask->getValue($b));

        $b->enableErrorConversion(E_ERROR | E_USER_ERROR);
        $this->assertEquals(E_ERROR | E_USER_ERROR, $mask->getValue($b));
    }

    /**
     * @covers Bedoved::errorHandler
     */
    public function testErrorHandler()
    {
        $b = new Bedoved();
        $b->enableErrorConversion(E_ERROR);
        try
        {
            $b->errorHandler(E_ERROR, 'Foo', 'bar.php', 123);
        }
        catch (ErrorException $e)
        {
            $this->assertEquals(E_ERROR, $e->getSeverity());
            $this->assertEquals('Foo', $e->getMessage());
            $this->assertEquals('bar.php', $e->getFile());
            $this->assertEquals(123, $e->getLine());
            return;
        }
        $this->fail('Expecting ErrorException.');
    }

    /**
     * @covers Bedoved::errorHandler
     */
    public function testErrorHandlerAtEscaped()
    {
        $b = new Bedoved();
        $b->enableErrorConversion(E_ERROR);
        @$b->errorHandler(E_ERROR, 'Foo', 'bar.php', 123);
    }

    /**
     * @covers Bedoved::errorHandler
     */
    public function testErrorHandlerMasked()
    {
        $b = new Bedoved();
        $b->enableErrorConversion(E_ERROR);
        $b->errorHandler(E_NOTICE, 'Foo', 'bar.php', 123);
    }

    /**
     * @covers Bedoved::fatalErrorHandler
     */
    public function testFatalErrorHandler()
    {
        $b = new Bedoved();
        $marker = new ReflectionProperty('Bedoved', 'errorMarker');
        $marker->setAccessible(true);
        $marker->setValue($b, '1234');
        $mask = new ReflectionProperty('Bedoved', 'fatalErrorHandler');
        $mask->setAccessible(true);
        $mask->setValue($b,
            function (ErrorException $e, $output)
            {
                return "{$e->getSeverity()}|{$e->getMessage()}|{$e->getFile()}|"
                    . "{$e->getLine()}|$output";
            }
        );

        $this->assertFalse($b->fatalErrorHandler('foo'));

        $text = 'Fatal error: Foo in bar.php on line 123 [1234]';
        $this->assertEquals('1|Foo|bar.php|123|' . $text, $b->fatalErrorHandler($text));
    }
}

