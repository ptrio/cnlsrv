<?php
abstract class TCPSocket extends \stdClass
{
  protected $handle = null;
  protected $host = null;
  protected $port = null;
  protected $usec_f = null; /* birth of this object */
  protected $userData = null;
  public function __construct ()
  {
    $this->usec_f = microtime ( TRUE );
  }
  public function getHost ()
  {
    return $this->host;
  }
  public function getPort ()
  {
    return $this->port;
  }
  public function getHandle ()
  {
    return $this->handle;
  }
  public function getErrorString ()
  {
    return socket_strerror ( socket_last_error ( $this->handle ) );
  }
  public function getCTime ()
  {
    return (float) $this->usec_f;
  }
  public function setUserData ( $data )
  {
    $this->userData = $data;
  }
}
class TCPClientSocket extends TCPSocket
{
  protected $handle = null;
  protected $host = null;
  protected $port = null;
  public function __construct ( $handle )
  {
    parent::__construct ();
    $this->handle = $handle;
    $host         = '';
    $port         = '';
    socket_getpeername ( $this->handle, $host, $port );
    $this->host = $host;
    $this->port = $port;
  }
  public function read ( $size = 8192 )
  {
    $data = @socket_read ( $this->handle, $size );
    if ( $data === FALSE )
      return FALSE;

    return ( $data === FALSE ) ? FALSE : $data;
  }
  public function write ( $data )
  {
    $size     = strlen ( $data );
    $numBytes = @socket_write ( $this->handle, $data, $size );

    return ( $numBytes == $size ) ? TRUE : FALSE;
  }
  public function close ()
  {
    @socket_close ( $this->handle );
  }
}
class TCPServerSocket extends TCPSocket
{
  protected $bRun = null;
  /**
   * @var \closure $thread
   */
  protected $thread = null;
  /**
   * @var \closure $error
   */
  protected $error = null;
  public function __construct ( $host, $port )
  {
    parent::__construct ();
    $this->host = $host;
    $this->port = $port;
    $this->bRun = FALSE;
  }
  public function setErrorFunction ( \Closure $fn )
  {
    $this->error = $fn;
  }
  public function setThreadFunction ( \closure $fn )
  {
    $this->thread = $fn;
  }
  public function setup ()
  {
    $this->handle = socket_create ( AF_INET, SOCK_STREAM, SOL_TCP );
    if ( $this->handle === FALSE )
    {
      $this->error ( socket_strerror ( socket_last_error () ) );

      return FALSE;
    }
    socket_set_option ( $this->handle, SOL_SOCKET, SO_REUSEADDR, 1 );
    if ( socket_bind ( $this->handle, $this->host, $this->port ) === FALSE )
    {
      $this->error ( socket_strerror ( socket_last_error ( $this->handle ) ) );

      return FALSE;
    }
  }
  public function shutdown ()
  {
    socket_shutdown ( $this->handle );
    socket_close ( $this->handle );
  }
  public function startListener ()
  {
    if ( is_null ( $this->handle ) )
    {
      $this->error ( 'FAIL - missing setup call? :-/' );

      return FALSE;
    }
    if ( socket_listen ( $this->handle, 25 ) === FALSE )
    {
      if ( !is_null ( $this->error ) )
        $this->error ( socket_strerror ( socket_last_error ( $this->handle ) ) );
    }
    $this->bRun = TRUE;
    $this->run ();
    $this->cleanup ();

    return TRUE;
  }
  public function error ( $data )
  {
    if ( !is_null ( $this->error )
         && is_callable ( $this->error )
    )
    {
      $fn = $this->error;
      $fn ( $data );
    }
  }
  protected function executeThreadFunction ( TCPClientSocket $client )
  {
    if ( !is_null ( $this->thread ) )
    {
      $fn = $this->thread;
      $fn ( $client, $this );
    }
  }
  protected function run ()
  {
    while ( $this->bRun )
    {
      $handle = null;
      if ( ( $handle = @socket_accept ( $this->handle ) ) === FALSE )
      {
        $this->error ( socket_strerror ( socket_last_error () ) );
        continue;
      }
      $client = new TCPClientSocket( $handle );
      $this->executeThreadFunction ( $client );
    }
  }
}