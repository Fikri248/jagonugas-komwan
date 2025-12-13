<?php

$scriptName = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
define('BASE_PATH', $scriptName === '' ? '' : $scriptName);
