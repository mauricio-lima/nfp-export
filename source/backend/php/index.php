<?php
  require('logger.php');

  $logger = new Logger('log.txt');
  
  header('Content-type: text/plain');
  switch ($_SERVER['REQUEST_METHOD'])
  {
    case 'OPTIONS':
        header('Access-Control-Allow-Origin: *');
  	header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
  	header('Access-Control-Allow-Headers: Content-Type');
  	
  	$logger->put('OPTIONS');
  	die();
  	
    case 'POST':
       header('Access-Control-Allow-Origin:  *');
       $body = file_get_contents('php://input');
       print($_SERVER['CONTENT_TYPE'] . PHP_EOL);
       print(PHP_EOL);
       print($body);  
       $logger->put('POST' . PHP_EOL . $_SERVER['CONTENT_TYPE'] . PHP_EOL . $body);
       die();
       
    case 'GET':
       header('Access-Control-Allow-Origin: *');     
       header('Content-type: application/json');
       $result = array(
                        'error' => 'Only POST method accepted for this service : ' . $_SERVER['REQUEST_METHOD'],
                        'referer' => $_SERVER['HTTP_REFERER'],
                        'uri'     => $_SERVER['REQUEST_URI' ]
                      );
       print(json_encode($result, JSON_PRETTY_PRINT));
       die();
          	
       print ('Only POST method accepted for this service : ' . $_SERVER['REQUEST_METHOD']);
       print (PHP_EOL . 'Referer : ' . $_SERVER['HTTP_REFERER']);
       print (PHP_EOL . 'URI     : ' . $_SERVER['REQUEST_URI' ]);
       $logger->put('GET' . PHP_EOL . 'Host : ' . $_SERVER['HTTP_REFERER']);
       die();
       
    default:
       print($_SERVER['REQUEST_METHOD'] . ' : Method not processed');
       $logger->put($_SERVER['REQUEST_METHOD'] . PHP_EOL . 'Method not processed' );
       die();
       
  } 
  	
?>
