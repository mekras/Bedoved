<?php
/**
 * Бедовед
 *
 * @version 1.2.0
 * @copyright Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @author Михаил Красильников <m.krasilnikov@yandex.ru>
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
     * Режим отладки
     * @var bool
     *
     * @since 1.2.0
     */
    private $debug;

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
    private $exceptionHandlingEnabled = false;

    /**
     * Состояние перехвата фатальных ошибок
     * @var bool
     */
    private $fatalErrorHandlingEnabled = false;

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
     * Инициализирует Бедоведа
     *
     * @param bool $debug  true — включить режим отладки
     */
    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Включает преобразование ошибок в исключения
     *
     * По умолчанию $mask включает в себя все ошибки кроме E_STRICT, E_NOTICE и E_USER_NOTICE.
     *
     * @param int $mask  битовая маска, задающая какие ошибки преобразовывать
     *
     * @return $this
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

        return $this;
    }

    /**
     * Включает перехват исключительных ситуаций
     *
     * Вы можете указать файл, который надо вывести при возникновении такой ситуации, с помощью
     * метода {@link setMessageFile()}.
     *
     * @return $this
     */
    public function enableExceptionHandling()
    {
        if (!$this->exceptionHandlingEnabled)
        {
            $this->exceptionHandlingEnabled = true;
            set_exception_handler(array($this, 'handleException'));
        }

        return $this;
    }

    /**
     * Включает перехват фатальных ошибок
     *
     * Этот метод:
     * 1. резервирует в памяти буфер, освобождаемый для обработки ошибок нехватки памяти;
     * 2. отключает HTML-оформление стандартных сообщений об ошибках;
     *
     * @return $this
     */
    public function enableFatalErrorHandling()
    {
        if (!$this->fatalErrorHandlingEnabled)
        {
            $this->fatalErrorHandlingEnabled = true;

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
        }
        return $this;
    }

    /**
     * Задаёт адрес (или адреса через запятую) куда будут отправляться сообщения об ошибках
     *
     * @param string $emails
     *
     * @return $this
     */
    public function setNotifyEmails($emails)
    {
        assert('is_string($emails)');

        $this->notify = $emails;

        return $this;
    }

    /**
     * Задаёт путь к файлу, содержимое которого должно быть отправлено клиенту в случае ошибки
     *
     * Если файл не существует, будет сделано предупреждение E_USER_WARNING.
     *
     * @param string $filename  путь к файлу
     *
     * @return $this
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
        return $this;
    }

    /**
     * Устанавливает обработчик фатальных ошибок
     *
     * Автоматически вызывает метод {@link enableFatalErrorHandling()}.
     *
     * @param callable $callback  обработчик фатальных ошибок
     *
     * @return $this
     */
    public function setFatalErrorHandler($callback)
    {
        if (is_callable($callback))
        {
            $this->fatalErrorHandler = $callback;
            $this->enableFatalErrorHandling();
        }
        return $this;
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
                return $this->handleException($e, true);
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
     * @return null|string
     */
    public function handleException(Exception $e, $return = false)
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
        if ($this->notify && false === $this->debug)
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

        $message = $this->getUserNotification($e);

        if ($return)
        {
            return $message;
        }

        if (!headers_sent())
        {
            header('Internal Server Error', true, 500);
        }
        echo $message;
        return null;
    }

    /**
     * Возвращает сообщение об ошибке для пользователя
     *
     * @param Exception $e
     *
     * @return bool|string
     *
     * @since 1.2.0
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    private function getUserNotification(Exception $e)
    {
        $message = false;
        if ($this->debug)
        {
            ob_start();
            require __DIR__ . '/Resources/Debug.html.php';
            $message = ob_get_clean();
        }
        else
        {
            $messageFile = $this->messageFile ?: __DIR__ . '/Resources/FatalError.html';
            if (@file_exists($messageFile) && @is_readable($messageFile))
            {
                @$message = file_get_contents($messageFile);
            }

            if (!$message)
            {
                $message =
                    "<!doctype html>\n<html><head><title>Internal Server Error</title></head>\n" .
                    "<body><h1>Internal Server Error</h1></body></html>";
            }
        }
        return $message;
    }
}

