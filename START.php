<?php
	//WINDOWS only!
	$WshShell = new COM('Wscript.Shell');
	// Запуск cmd в фоновом режиме, иконка на панели задач не выводится
	$oExec = $WshShell->Run('D:\usr\local\php5\php.exe '.dirname(__FILE__).'\Conference.php WIN D:\usr\local\php5\php.exe > '.dirname(__FILE__).'\log', 0, false);
?>