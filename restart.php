<?php
sleep(5);
shell_exec('nohup /usr/local/php5/bin/php '.dirname(__FILE__).'/Conference.php > '.dirname(__FILE__).'/log &');
?>