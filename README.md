Bedoved
=======

Бедовед — библиотека для перехвата и обработка ошибок, в том числе фатальных. Позволяет:

* Перехватывать и обрабатывать фатальные ошибки
* Превращать ошибки в исключения на основе заданной маски
* Отсылать извещения по почте в случае ошибок или исключений
* Выводить содержимое заданного файла вместо стандартного сообщения об ошибке или исключения

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
Есть задать свой собственный обработчик при помощи метода `setFatalErrorHandler`.

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
require 'Bedoved.php';
$bedoved = new Bedoved()
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

Замечания
---------

* Встроенный обработчик ошибок всегда записывает в журнал перехваченные ошибки и исключения вызовом
функции [error_log](http://php.net/error_log).
* Если в момент ошибки заголовки HTTP ещё не были отправлены, будет отправлен заголовок с кодом 500.
* Включение перехвата фатальных ошибок отключает HTML-оформление сообщений об ошибках и добавляет
к ним уникальный маркер.
