<?php

define('LINE_LIMIT', 15);

$lines = file($e->getFile());
$firstLine = $e->getLine() < LINE_LIMIT ? 0 : $e->getLine() - LINE_LIMIT;
$lastLine = $firstLine + LINE_LIMIT < count($lines)
    ? $firstLine + LINE_LIMIT
    : count($lines);
$code = '';

$isNormalMode = current(current($e->getTrace())) != 'fatalErrorHandler';

for ($i = $firstLine; $i < $lastLine; $i++)
{
    $s = $lines[$i];
    if ($isNormalMode)
    {
        $s = highlight_string('<?php' . $s, true);
        $s = preg_replace('/&lt;\?php/', '', $s, 1);
    }
    else
    {
        $s = '<pre>' . $s . '</pre>';
    }
    if ($i == $e->getLine() - 1)
    {
        $s = preg_replace('/(<\w+)/', '$1 class="error-line"', $s);
    }
    $code .= $s;
}


return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . get_class($e) . '</title>
    <style>
        html,
        body
        {
            background: #fff !important;
            color: #000 !important;
            font-family: monospace !important;
            font-size: 12px !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        header
        {
            background: #a00 !important;
            box-shadow: 0 .1em .2em #000;
            font-size: 1em !important;
            margin: 0 !important;
            padding: .5em 1em !important;
        }

        header > h1
        {
            color: #fff !important;
            margin: 0 !important;
        }

        h1
        {
            background: transparent !important;
            color: #000 !important;
            font-size: 1.5em !important;
            margin: 1em 0 .5em !important;
            padding: 0 !important;
        }

        article
        {
            padding: 1em !important;
        }

        article > h1
        {
            border-bottom: dashed 1px !important;
            margin: 0 !important;
            padding-bottom: .5em !important;
        }

        section
        {
            margin: 1em 0 .5em !important;
        }

        section > h1
        {
            font-size: 1.2em !important;
            margin: 1em 0 .5em !important;
        }

        pre
        {
            margin: 0 !important;
        }

        .location
        {
            font-family: monospace !important;
            margin: 1em 0 !important;
        }

        .code,
        .trace
        {
            border: solid 1px #888 !important;
            margin: 0 0 1em !important;
            overflow-x: scroll !important;
            padding: .5em 0 !important;
        }

        .code code
        {
            display: block !important;
        }

        .code .error-line
        {
            background-color: #faa !important;
        }

        .trace
        {
            padding: .5em 1em !important;
        }

    </style>
</head>
<body>
    <header>
        <h1>' . get_class($e) . '</h1>
    </header>

    <article>
        <h1>' . $e->getMessage() . '</h1>

        <section>
            <h1>Место возникновения ошибки</h1>
            <div class="location">' . $e->getFile() . ': ' . $e->getLine() . '</div>
            <div class="code">' . $code . '</div>
        </section>

        ' . ($isNormalMode ? '<section>
            <h1>Стек вызовов</h1>
            <pre class="trace">' . $e->getTraceAsString() . '</pre>
        </section>' : '') . '

    </article>
</body>
</html>';

