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


  function getPendingProcessingEntries()
  {
    header('Content-type: application/json');
    
    $sql = 'SELECT input_id, timestamp FROM input order by timestamp';
    try 
    {
      $database = GetDatabaseConnection();
      $statment = $database->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
      $statment->execute();
      
      $result = [];
      while ($row = $statment->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) 
      {
        array_push($result, array( 'id' => intval($row['input_id']), 'datetime' => $row['timestamp']));
      }
      
      print(json_encode($result, JSON_PRETTY_PRINT));   
    }
    catch (PDOException $e) 
    {
      print(json_encode(array(
                               'code'        =>  103,
                               'message'     => 'Database exception',
                               'description' =>  $e->getMessage()        
                             )));         
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
  
  
  function listCoupons()
  {
    header('Content-type: text/plain');
  
    $sql = 'SELECT content FROM input';
    try 
    {
      $database = GetDatabaseConnection();
      $statment = $database->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
      $statment->execute();
      
      while ($row = $statment->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) 
      {
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


  function postEntry()
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
  
  
  function postProcessEntries()
  {
    header('Content-type: application/json');
 
    try
    {
      $body = file_get_contents('php://input');
      $list = json_decode($body, false);
      if (json_last_error() != JSON_ERROR_NONE)
      {
        print('JSON Decode Error : ' . json_last_error());   
        throw new Exception('JSON Decode Error : ' . json_last_error());
      }
      
      if (!is_array($list)) 
      {
         throw new Exception('Invalid array supplied');
      }
      
      $sql = 'SELECT input_id, processed, content FROM input where input_id in (' .  str_repeat('?,', count($list) - 1) . '?' . ')';
      $database = GetDatabaseConnection();
      $statment = $database->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
      $statment->execute($list);
      
      $result = [];
      while ($row = $statment->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) 
      {
        $item = array( 'id' => intval($row['input_id']));
        if ($row['processed'] != null)
        {
          $item['previously_processed'] = $row['processed'];
          array_push($result, $item);
          continue;
        }
        
        $item = array_merge($item,  array('store_is_new' => false, 'items' => array( 'count' => 0, 'new' => 0 ), 'start' => 0, 'interval' => 0));
        array_push($result, $item);
      }
      
      print(json_encode($result, JSON_PRETTY_PRINT));
    }
    catch (Exception $e)
    {
      print(json_encode(array(
                               'code'        =>  102,
                               'message'     => 'General exception',
                               'description' =>  $e->getMessage()        
                             )));
    }
  }
 
 
  function get()
  {
     $uri = $_GET['route'];
     $uri = (substr($uri,-1) == '/') ? substr($uri, 0, strlen($uri)-1) : $uri;
     
     switch ($uri)
     {
       case 'pending':
         getPendingProcessingEntries();
         break;
       
       case '/nfp/list':
         listCoupons();
         break;
         
       default:
         header('Content-type: text/plain');
         var_dump($_GET);
         print(PHP_EOL);
         print($_SERVER['QUERY_STRING']. PHP_EOL);
         $start = strpos($_SERVER['QUERY_STRING'], '&');
         var_dump($start);
         var_dump(strpos($_SERVER['QUERY_STRING'], 'route',$start));
         break;
         //header('Access-Control-Allow-Origin: *');     
         //header('Content-type: application/json');
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
     $uri = $_GET['route'];
     $uri = (substr($uri,-1) == '/') ? substr($uri, 0, strlen($uri)-1) : $uri;
     
     switch ($uri)
     {
       case 'entry'  :
         postEntry();
         break;
         
       case 'process':
         postProcessEntries();
         break;
     }  
  }

  $start = strpos($_SERVER['QUERY_STRING'], '&'); 
  if ( ($start > 0) && (strpos($_SERVER['QUERY_STRING'], 'route', $start) !== false) )
  {
    header('Content-type: application/json');
    http_response_code(406);
    $result = array(
                    'code'    =>  101,
                    'message' => 'The parameter \'route\' could not be present in query string',
                    'query'   =>  $_SERVER['QUERY_STRING']        
                   );
    print(json_encode($result, JSON_PRETTY_PRINT));
    die();
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