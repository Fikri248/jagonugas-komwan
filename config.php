<?php
// folder aplikasi relatif terhadap domain
// contoh di localhost:  http://localhost/jagonugas-native/  -> BASE_PATH = '/jagonugas-native'
// contoh di Azure:      https://namasite.azurewebsites.net/ -> BASE_PATH = ''

$scriptName = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
define('BASE_PATH', $scriptName === '' ? '' : $scriptName);
