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
     * Размер буфера для работы в условиях переполнения памяти (в Кб)
     *
     * @var int
     */
    const MEMORY_OVERFLOW_BUFFER_SIZE = 64;

    /**
     * Битовая маска, определяющая какие ошибки, будут превращены в исключения
     *
     * @var int
     */
    private $errorConversionMask = null;

    /**
     * Состояние перехвата исключительных ситуаций
     * @var bool
     */
    private $enableExceptionHandling = false;

    /**
     * Состояние перехвата фатальных ошибок
     * @var bool
     */
    private $enableFatalErrorHandling = false;

    /**
     * Адреса e-mail для отправки извещений об ошибках
     * @var null|string
     */
    private $notify = null;

    /**
     * Путь к файлу с сообщением об ошибке
     *
     * @var null|string
     */
    private $messageFile = null;

    /**
     * Обработчик фатальных ошибок
     *
     * @var callable
     */
    private $fatalErrorHandler = null;

    /**
     * Маркер чтобы отличать настоящие сообщения об ошибках от похожих текстов, являющихся частью
     * контента.
     *
     * @var string
     */
    private $errorMarker;


    /**
     * Включает преобразование ошибок в исключения
     *
     * По умолчанию $mask включает в себя все ошибки кроме E_STRICT, E_NOTICE и E_USER_NOTICE.
     *
     * @param int $mask  битовая маска, задающая какие ошибки преобразовывать
     */
    public function enableErrorConversion($mask = null)
    {
        if (null === $mask)
        {
            $mask = E_ALL ^ (E_NOTICE | E_USER_NOTICE);
            if (version_compare(PHP_VERSION, '5.4', '>='))
            {
                $mask = $mask ^ E_STRICT;
            }
        }
        $this->errorConversionMask = $mask;
        set_error_handler(array($this, 'errorHandler'));
    }

    /**
     * Включает перехват исключительных ситуаций
     *
     * Вы можете указать файл, который надо вывести при возникновении такой ситуации, с помощью
     * метода {@link setMessageFile()}.
     */
    public function enableExceptionHandling()
    {
        if ($this->enableExceptionHandling)
        {
            return;
        }

        $this->enableExceptionHandling = true;

        set_exception_handler(array(__CLASS__, 'exceptionHandler'));
    }

    /**
     * Включает перехват фатальных ошибок
     *
     * Этот метод:
     * 1. резервирует в памяти буфер, освобождаемый для обработки ошибок нехватки памяти;
     * 2. отключает HTML-оформление стандартных сообщений об ошибках;
     *
     * @return boolean  true, если перехват включен и false если не удалось это сделать
     */
    public function enableFatalErrorHandling()
    {
        if ($this->enableFatalErrorHandling)
        {
            return true;
        }

        /*if (PHP_SAPI == 'cli')
        {
            return false;
        }*/

        $this->enableFatalErrorHandling = true;

        /*
         * В PHP нет стандартных методов для перехвата некоторых типов ошибок (например E_PARSE или
         * E_ERROR), однако способ всё же есть — зарегистрировать функцию через ob_start.
         * Но только не в режиме CLI.
         */
        // Резервируем буфер на случай переполнения памяти
        $GLOBALS['BEDOVED_MEMORY_OVERFLOW_BUFFER'] =
            str_repeat('x', self::MEMORY_OVERFLOW_BUFFER_SIZE * 1024);

        /* Задаём маркер, чтобы отличать реальные ошибки от текстовых сообщений */
        $this->errorMarker = uniqid();
        ini_set('error_append_string', '[' . $this->errorMarker . ']');
        // Необходимо для правильного определения фатальных ошибок
        ini_set('html_errors', 0);

        ob_start(array($this, 'fatalErrorHandler'), 4096);

        return true;
    }

    /**
     * Задаёт адрес (или адреса через запятую) куда будут отправляться сообщения об ошибках
     *
     * @param string $emails
     */
    public function setNotifyEmails($emails)
    {
        assert('is_string($emails)');

        $this->notify = $emails;
    }

    /**
     * Задаёт путь к файлу, содержимое которого должно быть отправлено клиенту в случае ошибки
     *
     * Если файл не существует, будет сделано предупреждение E_USER_WARNING.
     *
     * @param string $filename  путь к файлу
     */
    public function setMessageFile($filename)
    {
        assert('is_string($filename)');

        $path = realpath($filename);
        if ($path)
        {
            $this->messageFile = $filename;
        }
        else
        {
            trigger_error('File not found: ' . $filename, E_USER_WARNING);
        }
    }

    /**
     * Устанавливает обработчик фатальных ошибок
     *
     * Автоматически вызывает метод {@link enableFatalErrorHandling()}.
     *
     * @param callable $callback  обработчик фатальных ошибок
     *
     * @return boolean  true в случае успешного выполнения и false в случае ошибки
     */
    public function setFatalErrorHandler($callback)
    {
        if (!is_callable($callback))
        {
            return false;
        }

        $this->fatalErrorHandler = $callback;
        return $this->enableFatalErrorHandling();
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
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        /* Нулевое значение 'error_reporting' означает что был использован оператор "@" */
        if (error_reporting() == 0 || ($errno & $this->errorConversionMask) == 0)
        {
            return true;
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

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
    public function fatalErrorHandler($output)
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
            preg_match('/(parse|fatal) error:(.+) in (.+) on line (\d+)\s+(Call Stack.*\s)?\['
                . $this->errorMarker . '\]/is', $output, $m))
        {
            $errtype = $m[1];
            $errstr = trim($m[2]);
            $errfile = trim($m[3]);
            $errline = trim($m[4]);
            $severity = strcasecmp($errtype, 'parse') == 0 ? E_PARSE : E_ERROR;
            $e = new ErrorException($errstr, 0, $severity, $errfile, $errline);
            if ($this->fatalErrorHandler)
            {
                return call_user_func($this->fatalErrorHandler, $e, $output);
            }
            else
            {
                return $this->exceptionHandler($e, true);
            }
        }
        $GLOBALS['BEDOVED_MEMORY_OVERFLOW_BUFFER'] =
            str_repeat('x', self::MEMORY_OVERFLOW_BUFFER_SIZE * 1024);

        // возвращаем false для вывода буфера
        return false;
    }

    /**
     * Обработчик исключений
     *
     * @param Exception $e
     * @param bool      $return  true чтобы вернуть сообщение вместо вывода
     *
     * @return void|string
     */
    public function exceptionHandler(Exception $e, $return = false)
    {
        /*
         * Сообщение для администратора
         */

        $message = sprintf(
            "%s\n\n%s in %s at %s\n\nBacktrace:\n%s\n",
            $e->getMessage(),
            get_class($e),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        // Отправляем в журнал
        error_log($message);

        /* Отправляем по e-mail */
        if ($this->notify)
        {
            $message .= "\n";
            @$message .= 'URI: ' . $_SERVER['REQUEST_URI'] . "\n";
            @$message .= 'Host: ' . gethostbyaddr($_SERVER['REMOTE_ADDR']) . "\n";
            @$message .= 'Agent: ' . $_SERVER['HTTP_USER_AGENT'] . "\n";

            $subject = '[Bedoved] Error on ';
            $subject .= isset($_SERVER['HTTP_HOST'])
                ? substr($_SERVER['HTTP_HOST'], 0, 4) == 'www.'
                    ? substr($_SERVER['HTTP_HOST'], 4)
                    : $_SERVER['HTTP_HOST']
                : php_uname('n');
            mail($this->notify, $subject, $message);
        }

        /*
         * Сообщение для пользователя
         */

        $httpError = 'Internal Server Error';
        if (!headers_sent())
        {
            header($httpError, true, 500);
        }

        $message = false;
        if (@file_exists($this->messageFile) && @is_readable($this->messageFile))
        {
            @$message = file_get_contents($this->messageFile);
        }

        if (!$message)
        {
            $message = "<!doctype html>\n<html><head><title>$httpError</title></head>\n" .
                "<body><h1>$httpError</h1></body></html>";
        }

        if ($return)
        {
            return $message;
        }
        echo $message;
        return null;
    }
}
