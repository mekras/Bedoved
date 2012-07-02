<?php
/**
 * Бедовед
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


/**
 * Ядро обработчика ошибок и исключительных ситуаций
 *
 * @package Bedoved
 */
class Bedoved
{
	/**
	 * Резервный буфер для отлова ошибок переполнения памяти (в Кб)
	 *
	 * @var int
	 */
	const MEMORY_OVERFLOW_BUFFER_SIZE = 64;

	/**
	 * Битовая маска, определяющая какие ошибки, будут превращены в исключения
	 *
	 * @var int
	 */
	private static $errorConversionMask = null;

	/**
	 * Обработчик фатальных ошибок
	 *
	 * @var callback
	 */
	private static $fatalErrorHandler = null;

	/**
	 * Включает преобразование ошибок в исключения
	 *
	 * По умолчанию $mask включает в себя все ошибки кроме E_STRICT, E_NOTICE и E_USER_NOTICE.
	 *
	 * @param int $mask  битовая маска, задающая какие ошибки преобразовывать
	 */
	public static function enableErrorConversion($mask = null)
	{
		if (null === $mask)
		{
			$mask = E_ALL ^ (E_STRICT | E_NOTICE | E_USER_NOTICE);
		}
		self::$errorConversionMask = $mask;
		set_error_handler(array('Bedoved', 'errorHandler'));
	}

	/**
	 * Устанавливает обработчик фатальных ошибок
	 *
	 * Этот метод:
	 * 1. резервирует в памяти буфер, освобождаемый для обработки ошибок нехватки памяти;
	 * 2. отключает HTML-оформление стандартных сообщений об ошибках;
	 *
	 * @param callback $callback  обработчик фатальных ошибок
	 *
	 * @return boolean
	 */
	public static function setFatalErrorHandler($callback)
	{
		/*
		 * В PHP нет стандартных методов для перехвата некоторых типов ошибок (например E_PARSE или
		 * E_ERROR), однако способ всё же есть — зарегистрировать функцию через ob_start.
		 * Но только не в режиме CLI.
		 */
		if (PHP_SAPI == 'cli' || !is_callable($callback))
		{
			return false;
		}
		// Резервируем буфер на случай переполнения памяти
		$GLOBALS['BEDOVED_MEMORY_OVERFLOW_BUFFER'] =
			str_repeat('x', self::MEMORY_OVERFLOW_BUFFER_SIZE * 1024);

		// Немного косметики
		ini_set('html_errors', 0);

		self::$fatalErrorHandler = $callback;
		ob_start(array('Bedoved', 'fatalErrorHandler'), 4096);

		return true;
	}

	/**
	 * Обработчик ошибок
	 *
	 * Обработчик ошибок, устанавливаемый через {@link set_error_handler() set_error_handler()} в
	 * методе {@link enableErrorConversion()}. Все ошибки, соответствующие заданной маске,
	 * превращаются в исключения {@link http://php.net/ErrorException ErrorException}.
	 *
	 * Примечание: На самом деле этот метод обрабатывает только E_STRICT, E_NOTICE, E_USER_NOTICE,
	 * E_WARNING, E_USER_WARNING и E_USER_ERROR.
	 *
	 * @param int    $errno    тип ошибки
	 * @param string $errstr   описание ошибки
	 * @param string $errfile  имя файла, в котором произошла ошибка
	 * @param int    $errline  строка, где произошла ошибка
	 *
	 * @throws ErrorException
	 *
	 * @return bool
	 */
	public static function errorHandler($errno, $errstr, $errfile, $errline)
	{
		/* Нулевое значение 'error_reporting' означает что был использован оператор "@" */
		if (error_reporting() == 0 || ($errno & self::$errorConversionMask) == 0)
		{
			return true;
		}

		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	}
	//-----------------------------------------------------------------------------

	/**
	 * Обработчик фатальных ошибок
	 *
	 * Этот обработчик пытается перехватывать сообщения о фатальных ошибках, недоступных при
	 * использовании {@link set_error_handler() set_error_handler()}. Это делается через обработчик
	 * {@link ob_start() ob_start()}, устанавливаемый в методе {@link setFatalErrorHandler()}.
	 *
	 * <i>Замечание по производительности</i>: этот метод освобождает в начале и выделяет в конце
	 * своей работы буфер в памяти для отлова ошибок переполнения памяти. Эти операции замедляют
	 * вывод примерно на 1-2%.
	 *
	 * @param string $output  содержимое буфера вывода
	 *
	 * @return string|bool
	 */
	public static function fatalErrorHandler($output)
	{
		// Освобождает резервный буфер
		unset($GLOBALS['BEDOVED_MEMORY_OVERFLOW_BUFFER']);

		/* Предварительная проверка без использования медленных регулярных выражений */
		$errorTokens =
			stripos($output, 'fatal error') !== false ||
			stripos($output, 'parse error') !== false;

		/*
		 * Окончательная проверка. Регулярные выражения будут применены только если переменная
		 * $errorTokens истинна.
		 */
		if ($errorTokens &&
			preg_match('/(parse|fatal) error:(.+?) in (.+?) on line (\d+)/i', $output, $m))
		{
			$errtype = $m[1];
			$errstr = trim($m[2]);
			$errfile = trim($m[3]);
			$errline = trim($m[4]);
			return call_user_func(self::$fatalErrorHandler, $errtype, $errstr, $errfile, $errline,
				$output);
		}
		$GLOBALS['BEDOVED_MEMORY_OVERFLOW_BUFFER'] =
			str_repeat('x', self::MEMORY_OVERFLOW_BUFFER_SIZE * 1024);

		// возвращаем false для вывода буфера
		return false;
	}
}
