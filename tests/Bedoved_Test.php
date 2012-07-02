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
	public function test_enableErrorConversion()
	{
		$mask = new ReflectionProperty('Bedoved', 'errorConversionMask');
		$mask->setAccessible(true);

		Bedoved::enableErrorConversion();
		$this->assertGreaterThan(0, $mask->getValue('Bedoved'));

		Bedoved::enableErrorConversion(E_ERROR | E_USER_ERROR);
		$this->assertEquals(E_ERROR | E_USER_ERROR, $mask->getValue('Bedoved'));
	}

	/**
	 * @covers Bedoved::errorHandler
	 */
	public function test_errorHandler()
	{
		Bedoved::enableErrorConversion(E_ERROR);
		try
		{
			Bedoved::errorHandler(E_ERROR, 'Foo', 'bar.php', 123);
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
	public function test_errorHandler_at_escaped()
	{
		Bedoved::enableErrorConversion(E_ERROR);
		@Bedoved::errorHandler(E_ERROR, 'Foo', 'bar.php', 123);
	}

	/**
	 * @covers Bedoved::errorHandler
	 */
	public function test_errorHandler_masked()
	{
		Bedoved::enableErrorConversion(E_ERROR);
		Bedoved::errorHandler(E_NOTICE, 'Foo', 'bar.php', 123);
	}

	/**
	 * @covers Bedoved::fatalErrorHandler
	 */
	public function test_fatalErrorHandler()
	{
		$mask = new ReflectionProperty('Bedoved', 'fatalErrorHandler');
		$mask->setAccessible(true);
		$mask->setValue('Bedoved', function ($errtype, $errstr, $errfile, $errline, $output)
			{
				return "$errtype|$errstr|$errfile|$errline|$output";
			}
		);

		$this->assertFalse(Bedoved::fatalErrorHandler('foo'));

		$text = 'Fatal error: Foo in bar.php on line 123';
		$this->assertEquals('Fatal|Foo|bar.php|123|' . $text, Bedoved::fatalErrorHandler($text));
	}
}