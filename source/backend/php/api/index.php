<?php
  require('logger.php');

  $logger = new Logger('log.txt');
  
  $error_string = [];
  
  function load_translations()
  {
    global $error_string;

    $translations = parse_ini_file('translations.ini', true);
    $error_string = $translations['pt-br'];
  }


  function error_message($key)
  {
    global $error_string;

    if (!isset($error_string[$key]))
      return $key;

    return $error_string[$key];
  }


  function GetDatabaseConnection()
  {
    $configuration = parse_ini_file('configuration.ini', true);

    if (!isset($configuration['database']['host']))
      throw new Exception(error_message('missing.host.database.configuration'));

    if (!isset($configuration['database']['name']))
    {
      $message = error_message('missing.name.database.configuration');
      throw new Exception(error_message('missing.name.database.configuration'));
    }
      

    if (!isset($configuration['database']['user']))
      throw new Exception(error_message('missing.user.database.configuration'));

    if (!isset($configuration['database']['password'])) 
      throw new Exception(error_message('missing.password.database.configuration'));

    $database = new PDO('mysql:host=' . $configuration['database']['host'] . '; dbname='  . $configuration['database']['name'], $configuration['database']['user'], $configuration['database']['password']);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $database->query('SET character_set_connection=utf8');
    $database->query('SET character_set_client=utf8');
    $database->query('SET character_set_results=utf8');
    
    return $database;
  }


  function storeExceptions($database, $exceptions)
  {
    $sql = 'SELECT * FROM `descriptions`';
    $statement = $database->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
    $statement->execute();
    $descriptions = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement = null;
        
    $statement = $database->prepare('INSERT INTO `exceptions` (`description`) values (?)');
    foreach($exceptions as $exception)
    {
    var_dump($exception['data']);
        if (!isset($exception['key'])) continue;
        $description = array_filter($descriptions, function($description) use ($exception) { return $description['key'] == $exception['key']; });
        $description = array_shift($description);
        $description = !$description ? '' : $description['description'];
        foreach($exception['data'] as $key => $value)
        {
          $description = str_replace('{'. ($key + 1) .'}', $value, $description);
        }
        $statement->execute([$description]);
    }
  }
  
  
  function mapValues($key,$value)
  {
    return is_null($value) ? 'ISNULL(' . $key . ')' : $key . ' = \'' . $value . '\'';
  }
  
  
  function getStoreId($database, $data, &$item)
  {
    $exceptions = [];
  
    if ( !property_exists($data, 'store') )
      return null;

    $name    = (property_exists($data->store, 'name'   ) && !empty($data->store->name   )) ? $data->store->name    : null;
    $address = (property_exists($data->store, 'address') && !empty($data->store->address)) ? $data->store->address : null;
    if (property_exists($data->store, 'documents'))
    {
      $cnpj = (property_exists($data->store->documents, 'cnpj') && !empty($data->store->documents->cnpj)) ? $data->store->documents->cnpj : null;
      $ie   = (property_exists($data->store->documents, 'ie'  ) && !empty($data->store->documents->ie  )) ? $data->store->documents->ie   : null;
      $im   = (property_exists($data->store->documents, 'im'  ) && !empty($data->store->documents->im  )) ? $data->store->documents->im   : null;
    }
    else
    {
      $cnpj = null;
      $ie   = null;
      $im   = null;
    }      
      
    $parameters = array( 'name' => $name, 'address' => $address, 'cnpj' => $cnpj, 'ie' => $ie, 'im' => $im );
    $parameters = array_map('mapValues', array_keys($parameters), $parameters);
    $sql = 'SELECT store_id, name, address, cnpj, ie, im FROM stores where ' . join(' AND ', $parameters);

    $storesQuery = $database->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
    $storesQuery->execute();
    if ($storesQuery->rowCount() == 0)
    {
       $sql = 'INSERT INTO stores (name, address, cnpj, ie, im) VALUES (?,?,?,?,?)';
       $storesInsert = $database->prepare($sql);
       $storesInsert->execute([$name, $address, $cnpj, $ie, $im]);
       $storeId = $database->lastInsertId();
       $item['store_is_new'] = true;
       
       if (!$name)    array_push($exceptions, array( 'key' => 'store_name_not_defined',    'data' => [$storeId, $item['id']] ));
       if (!$address) array_push($exceptions, array( 'key' => 'store_address_not_defined', 'data' => [$storeId, $item['id']] ));
       if (!$cnpj)    array_push($exceptions, array( 'key' => 'store_cnpj_not_defined',    'data' => [$storeId, $item['id']] ));
       if (!$ie)      array_push($exceptions, array( 'key' => 'store_ie_not_defined',      'data' => [$storeId, $item['id']] ));
       if (!$im)      array_push($exceptions, array( 'key' => 'store_im_not_defined',      'data' => [$storeId, $item['id']] ));
    }
    else
    {
       $storeId = intval($storesQuery->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)['store_id']);
       $item['store_is_new'] = false;
    }
    $storesQuery = null;
    
    //$exceptions = array_map(function($exception) { $data = array_merge($exception['data'], [$item('id'), $storeId]); return array('key'=> $exception['key'], 'data' => data); }, $exceptions);
    storeExceptions($database, $exceptions);
    
    return $storeId;
  }


  function getCustomerId($database, $data, &$item)
  {
    $exceptions = [];
  
    if ( !property_exists($data, 'customer') )
      return null;

    $name    = (property_exists($data->customer, 'name'   ) && !empty($data->customer->name   )) ? $data->customer->name    : null;
    $address = (property_exists($data->customer, 'address') && !empty($data->customer->address)) ? $data->customer->address : null;
    if (property_exists($data->customer, 'documents'))
    {
      $cpf_cnpj = (property_exists($data->customer->documents, 'cpf_cnpj') && !empty($data->customer->documents->cpf_cnpj)) ? $data->customer->documents->cpf_cnpj : null;
    }
    else
    {
      $cpf_cnpj = null;
    }      
      
    $parameters = array( 'name' => $name, 'address' => $address, 'cpf_cnpj' => $cpf_cnpj );
    $parameters = array_map('mapValues', array_keys($parameters), $parameters);
    $sql = 'SELECT customer_id, name, address, cpf_cnpj FROM customers where ' . join(' AND ', $parameters);

    $customersQuery = $database->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
    $customersQuery->execute();
    if ($customersQuery->rowCount() == 0)
    {
       $sql = 'INSERT INTO customers (name, address, cpf_cnpj) VALUES (?,?,?)';
       $customersInsert = $database->prepare($sql);
       $customersInsert->execute([$name, $address, $cpf_cnpj]);
       $customerId = $database->lastInsertId();
       $item['customer_is_new'] = true;
       
       if (!$name)     array_push($exceptions, array( 'key' => 'customer_name_not_defined',     'data' => [$customerId, $item['id']] ));
       if (!$address)  array_push($exceptions, array( 'key' => 'customer_address_not_defined',  'data' => [$customerId, $item['id']] ));
       if (!$cpf_cnpj) array_push($exceptions, array( 'key' => 'customer_document_not_defined', 'data' => [$customerId, $item['id']] ));
    }
    else
    {
       $customerId = intval($storesQuery->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)['customer_id']);
       $item['customer_is_new'] = false;
    }
    $storesQuery = null;
    
    //$exceptions = array_map(function($exception) { $data = array_merge($exception['data'], [$item('id'), $storeId]); return array('key'=> $exception['key'], 'data' => data); }, $exceptions);
    storeExceptions($database, $exceptions);
    
    return $storeId;
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
    catch (Exception $exception)
    {
      throw $exception;
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
      
      $result     = [];
      $exceptions = [];
      while ($row = $statment->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) 
      {
        $item = array( 'id' => intval($row['input_id']));
        if ($row['processed'] != null)
        {
          $item['previously_processed'] = $row['processed'];
          array_push($result, $item);
          continue;
        }
        
        $data = json_decode($row['content'], false);
        if (json_last_error() != JSON_ERROR_NONE)
        {
          print('JSON Decode Error : ' . json_last_error());   
          throw new Exception('JSON Decode Error');
        }
        
        $item = array_merge($item,  array('store_is_new' => false, 'customer_is_new' => false, 'items' => array( 'count' => 0, 'new' => 0 ), 'start' => 0, 'interval' => 0));
        
        $storeId    = getStoreId   ($database, $data, $item);
        $customerId = getCustomerId($database, $data, $item);
        
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


  try
  {
    throw new ErrorException("teste");
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

    load_translations();

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
  }
  catch (Exception $exception)
  {
    header('Content-type: application/json');
    print(json_encode(array(
      'code'        =>  102,
      'message'     => 'General exception',
      'description' =>  $exception->getMessage()        
    )));
  }

?>