<?php

header('Content-Type: text/plain');

print('Method : ' . $_SERVER['REQUEST_METHOD'] . PHP_EOL );
print('Path   : ' . $_SERVER['QUERY_STRING']   . PHP_EOL );


/*
On php.ini for XDebug 3 working

[XDebug]
//zend_extension = c:\xampp\php\ext\php_xdebug-2.9.0-7.2-vc15.dll
zend_extension = c:\xampp\php\ext\php_xdebug-3.0.1-7.4-vc15-x86_64.dll
xdebug.mode = debug
xdebug.start_with_request = yes
xdebug.remote_enable = 1
xdebug.remote_autostart = 1
xdebug.client_host = 127.0.0.1
xdebug.client_port = 9900
xdebug.log_level = 10
xdebug.client_log = c:\xampp\apache\logs\xdebug.log





Alias on end of http.conf file

Alias /nfp       	"d:\mauricio\projects\nfp-export\source\backend\php"
<Directory       	"d:\mauricio\projects\nfp-export\source\backend\php" >
	Options	Indexes FollowSymLinks Includes ExecCGI
	AllowOverride All
	Require all granted

	RewriteEngine	On
	RewriteBase		/nfp/api/
    RewriteCond 	%{REQUEST_FILENAME} 	!-f
	RewriteCond 	%{REQUEST_FILENAME} 	!-d
	RewriteCond 	%{REQUEST_URI} 			!^path\.php
	#RewriteRule 	^(.*)$ 			  		../path.php?$1 [L]
	RewriteRule 	^(.*)$ 			  		index.php?route=$1 [L]
</Directory>



*/


?>

