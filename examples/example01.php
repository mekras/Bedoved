<?php

require __DIR__ .'/../src/Bedoved.php';

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
