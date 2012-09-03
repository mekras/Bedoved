Bedoved
=======

Перехват и обработка ошибок.

Пример использования:

```php
// Включить превращение ошибок в исключительные ситуации
Bedoved::enableErrorConversion();
// Включить перехват исключений, не перехваченных приложением
Bedoved::enableExceptionHandling();
// Включить перехват фатальных ошибок
Bedoved::enableFatalErrorHandling();
// Включить отправку извещений об ошибках по e-mail
Bedoved::setNotifyEmails('admin@example.org');
// При возникновении ошибки показывать этот файл
Bedoved::setMessageFile('/path/to/file.html');
```
