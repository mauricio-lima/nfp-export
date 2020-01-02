<?php

class Logger 
{
    private $file;
    private $prefix;
    private $format;
    private $first;

    public function __construct($filename, $format = 'd/m/y h:m:s')
    {
        $this->file   = $filename;
        $this->format = $format;
    }

    public function setPrefixFormat($format)
    {
    	$this->format = $format;
    }
    
    public function getPrefix($filler = false) 
    {
    	if (strlen($format == '') == 0)
    	   return '';
    	   
        $prefix = date($this->format). ' >> ';
        if ($filler)
        {
     	   $prefix = str_pad('', strlen($prefix), ' ', STR_PAD_RIGHT);
        }
        
        return $prefix;
    }


    public function put($insert) 
    {
        $first  = true;
        $stream = '';
        
    	$prefix = $this->getPrefix(false);
    	
    	$lines  = explode(PHP_EOL, $insert);	
    	foreach($lines as $line)
    	{
    	  $stream .= $first ? $prefix : str_pad('', strlen($prefix), ' ', STR_PAD_RIGHT);
    	  $stream .= $line . PHP_EOL;
    	  $first = false;
    	}
    		
        file_put_contents($this->file, $stream, FILE_APPEND); 
    }
    
}

?>
