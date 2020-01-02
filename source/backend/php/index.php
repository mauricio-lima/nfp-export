<?php
  require('logger.php');

  $logger = new Logger('log.txt');
  
  
  function GetDatabaseConnection()
   {
    $database = new PDO('mysql:host=localhost; dbname=mauri879_financial_nfp', 'mauri879_phpscpt', 'phpscpt');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $database->query('SET character_set_connection=utf8');
    $database->query('SET character_set_client=utf8');
    $database->query('SET character_set_results=utf8');
    
    return $database;
   }


  function listCoupons()
  {
    header('Content-type: text/plain');
  
    $sql = 'SELECT content FROM input';
    try 
    {
      $database = GetDatabaseConnection();
      $statment = $database->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
      $statment->execute();
      
      while ($row = $statment->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
        $data = json_decode($row['content'], false);
        if (json_last_error() != JSON_ERROR_NONE)
        {
          print('JSON Decode Error : ' . json_last_error());   
          throw new Exception('JSON Decode Error');
        }
        
        //print_r($data);
        $display  = '';
        $display .= ($data->customer->documents->cpf_cnpj != '') ? $data->customer->documents->cpf_cnpj : 'missing    ';
        //$display .= $data->documents->cnpj_cpf;
        $display .= '  ';
        $display .= substr($data->datetime,0,10) . '     ';
        $display .= substr('       ' . $data->totals->total, -8) . '    '; 
        $display .= $data->store->name;
        print($display . PHP_EOL);
       
        //print($row['content']);
        //print($data['totals']['total'] . PHP_EOL);
      }
    }
    catch (PDOException $e) 
    {
      print($e->getMessage());
    }
    catch (Exception $e)
    {
      print('Generic exception');
    }
    finally
    {
      $statment = null;
      print(PHP_EOL);
    }
  }

 
  function get($uri)
  {
     switch ($uri)
     {
       case '/nfp/list':
         listCoupons();
         break;
         
       default:
         header('Access-Control-Allow-Origin: *');     
         header('Content-type: application/json');
         $result = array(
                          'error'   => 'Unmapped URI',
                          'referer' =>  $_SERVER['HTTP_REFERER'],
                          'uri'     =>  $_SERVER['REQUEST_URI' ]
                        );
         print(json_encode($result, JSON_PRETTY_PRINT));
         
     }
  }

  
  function post()
  {
    global $logger;
    
    $body = file_get_contents('php://input');
  
    $sql  = '';
    $sql .= 'insert into `input`'    . PHP_EOL;
    $sql .= '   (content)' . PHP_EOL;
    $sql .= 'values'                          . PHP_EOL;
    $sql .= '   (';
    $sql .= '     \'' . $body . '\'';
    $sql .= '   )';
    
    $dump = '';
    try
     { 
      $database = GetDatabaseConnection(); 
      $statment = $database->prepare($sql);
      $statment->execute();
      
      $logger->put('ok');
      $result = array( 'message' => 'input registered' );
     }
    catch(PDOException $err)
     {
      $dump .= $err->getMessage()     . PHP_EOL;
      $dump .= $database->errorInfo() . PHP_EOL;
      $logger->put($dump);   
      $result = array( 'message' => $err->getMessage());
     }
     
    header('Content-type: application/json');
    print(json_encode($result, JSON_PRETTY_PRINT));
  }


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
       post();
       break;
       
    case 'GET':
       get($_SERVER['REQUEST_URI' ]);
       die();
       
    default:
       print($_SERVER['REQUEST_METHOD'] . ' : Method not processed');
       $logger->put($_SERVER['REQUEST_METHOD'] . PHP_EOL . 'Method not processed' );
       die();
       
  }

?>
