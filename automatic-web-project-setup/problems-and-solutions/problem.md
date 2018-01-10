If you found Guzzle error : 

 [GuzzleHttp\Exception\RequestException]                                       
  cURL error 60: SSL certificate problem: unable to get local issuer certificate (see http://curl.haxx.se/libcurl/c/l
  ibcurl-errors.html)               
  
  
  Then do the following: 
  
  ◘ Download cacert.pem file from here: http://curl.haxx.se/docs/caextract.html
  ◘ Save the file in your PHP installation folder. ( ex D:\wamp\php\cacert.pem).
  ◘ Open your php.ini file and add this line: curl.cainfo="D:\wamp\php\cacert.pem"
  ◘ Restart your Apache server

REF: https://stackoverflow.com/questions/38258016/guzzlehttp-exception-requestexception-when-creating-a-project-with-symfony-2-8-f