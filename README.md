Bedoved
=======

Бедовед — библиотека для перехвата и обработки ошибок, в том числе фатальных.

[![Latest Stable Version](https://poser.pugx.org/mekras/bedoved/v/stable.png)](https://packagist.org/packages/mekras/bedoved)
[![License](https://poser.pugx.org/mekras/bedoved/license.png)](https://packagist.org/packages/mekras/bedoved)
[![Build Status](https://travis-ci.org/mekras/Bedoved.svg?branch=develop)](https://travis-ci.org/mekras/Bedoved)
[![Coverage Status](https://coveralls.io/repos/mekras/Bedoved/badge.png?branch=master)](https://coveralls.io/r/mekras/Bedoved?branch=master)



Позволяет:

* перехватывать и обрабатывать фатальные ошибки;
* превращать ошибки в исключения на основе заданной маски;
* отсылать извещения по почте в случае ошибок или исключений;
* выводить содержимое заданного файла вместо стандартного сообщения об ошибке или исключения;
* выводить подробную отладочную информацию об ошибке.


Пример использования:

```php
require 'Bedoved.php';
$bedoved = new Bedoved()
// Включить превращение ошибок в исключительные ситуации
$bedoved->enableErrorConversion();
// Включить перехват исключений, не перехваченных приложением
$bedoved->enableExceptionHandling();
// Включить перехват фатальных ошибок
$bedoved->enableFatalErrorHandling();
// Включить отправку извещений об ошибках по e-mail
$bedoved->setNotifyEmails('admin@example.org');
// При возникновении ошибки показывать этот файл
$bedoved->setMessageFile('/path/to/file.html');
```

Перехват и обработка фатальных ошибок
-------------------------------------

В PHP нет стандартных методов для перехвата некоторых типов ошибок (например E_PARSE или
E_ERROR), однако способ всё же есть — зарегистрировать функцию через
[ob_start](http://php.net/ob_start). Не работает в режиме CLI.

Перехват фатальных ошибок с помощью Бедоведа включается вызовом метода `enableFatalErrorHandling`.
Есть возможность задать свой собственный обработчик при помощи метода `setFatalErrorHandler`.

```php
<?php
require 'Bedoved.php';
$bedoved = new Bedoved();
$bedoved->enableFatalErrorHandling();
$bedoved->setFatalErrorHandler(
    /**
     * Ваш обработчик ошибок
     *
     * Чтобы вывести что-нибудь в браузер используйте return.
     *
     * @param ErrorException $e       исключение, содержащее информацию об ошибке
     * @param string         $output  фрагмент вывода, где обнаружено сообщение об ошибке
     *
     * @return string  вывод для браузера
     */
    function (ErrorException $e, $output)
    {
        return 'ERROR: ' . $e->getMessage();
    }
);

$x = new Foo;
```

Превращение ошибок в исключения
-------------------------------

```php
<?php
require 'Bedoved.php';
$bedoved = new Bedoved();
$bedoved->enableErrorConversion();

try
{
    $x = 1 / 0;
}
catch (ErrorException $e)
{
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}

```

Предопределённые действия в случае ошибки
-----------------------------------------

Не обязательно писать собственные обработчики ошибок, можно использовать предопределённые в Бедоведе
действия: отправку извещения по почте и показ файла.

```php
$bedoved = new Bedoved();
$bedoved
    ->enableErrorConversion()
    ->enableExceptionHandling()
    ->enableFatalErrorHandling()
    ->setNotifyEmails('admin@example.org')
    ->setMessageFile('/path/to/file.html');
```

Метод `setNotifyEmails` позволяет задать список адресов e-mail (одной строкой через запятую), на
которые будут отправляться сообщения об ошибках. В сообщении указывается текст ошибки, место её
возникновения, стек вызовов, запрошенный URI, запрашивающий хост и пользовательский агент. В теме
сообщения указывается доменное имя сайта (из `$_SERVER['HTTP_HOST']`) или имя сервера, возвращаемое
`php_uname('n')`.

Метод `setMessageFile` позволяет задать файл, содержимое которого будет показано в случае
возникновения ошибки.

Режим отладки
-------------

В версии 1.2.0 добавлен режим отладки, в котором:

* сообщения по e-mail не отсылаются;
* игнорируется зачение, заданное методом `setMessageFile()`
* в браузер выводится подробное описание ошибки.

Чтобы включить режим отладки, надо передать в конструкторе первым аргументом `true`:

```php
$bedoved = new Bedoved(true);
```

Замечания
---------

* Встроенный обработчик ошибок всегда записывает в журнал перехваченные ошибки и исключения вызовом
функции [error_log](http://php.net/error_log).
* Если в момент ошибки заголовки HTTP ещё не были отправлены, будет отправлен заголовок с кодом 500.
* Включение перехвата фатальных ошибок отключает HTML-оформление сообщений об ошибках и добавляет
к ним уникальный маркер.
