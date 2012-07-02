<?php

require '../src/Bedoved.php';
var_dump(Bedoved::setFatalErrorHandler(
	function ($errtype, $errstr, $errfile, $errline, $output)
	{
		return "Type $errtype; Text: $errstr; File: $errfile; Line: $errline";
	}
));

foo();
