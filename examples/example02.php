<?php

if (PHP_SAPI == 'cli')
{
    die('Can not run in CLI mode' . PHP_EOL);
}

require __DIR__ .'/../src/Bedoved.php';
$bedoved = new Bedoved();
$bedoved->enableFatalErrorHandling();
$bedoved->setFatalErrorHandler(
    function (ErrorException $e)
    {
        return 'ERROR: ' . $e->getMessage();
    }
);

$x = new Foo;
