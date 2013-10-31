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
            font-family: monospace;
            height: 100%;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        header
        {
            background-color: #a00;
            box-shadow: 0 .1em .2em #000;
            color: #fff;
            font-size: 1em;
            margin: 0;
            padding: .5em 1em;
        }

        header > h1
        {
            margin: 0;
        }

        h1
        {
            font-size: 1.5em;
            margin: 1em 0 .5em;
            padding: 0;
        }

        article
        {
            padding: 1em;
        }

        article > h1
        {
            border-bottom: dashed 1px;
            margin: 0;
            padding-bottom: .5em;
        }

        section
        {
            margin: 1em 0 .5em;
        }

        section > h1
        {
            font-size: 1.2em;
            margin: 1em 0 .5em;
        }

        pre
        {
            margin: 0;
        }

        .location
        {
            font-family: monospace;
            margin: 1em 0;
        }

        .code,
        .trace
        {
            border: solid 1px #888;
            margin: 0 0 1em;
            overflow-x: scroll;
            padding: .5em 0;
        }

        .code code
        {
            display: block;
        }

        .code .error-line
        {
            background-color: #faa;
        }

        .trace
        {
            padding: .5em 1em;
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

