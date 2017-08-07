<?php
	namespace Micho;
	isset($_REQUEST['port'])?$port = $_REQUEST['port']:$port = null;
	new WebSocket( $port );
	class WebSocket{
	    private $timeout = 60;  //超时时间
	    private $handShake = False; //默认未牵手
	    private $master = 1;  //主进程
	    private $port = 8094;  //监听端口
	    private $connectPool = [];  //连接池
	    private $maxConnectNum = 1024; //最大连接数
	   	public function __construct(){
	   		$this->port = empty($port)?$this->port:$port;
	   		$this->start_socket();
	   	}
	   	private function start_socket(){
	   		$this->master = socket_create_listen( $this->port );
	   		if(!$this->master){
		   		echo socket_strerror(socket_last_error()) + "master";
		   		exit;
	   		}

	   		$this->connectPool[] = $this->master;
	   		while(true){
	   			$readFds = $this->connectPool;
	   			// $writeFds = array();
	   			$e = null;
	   			$res = socket_select( $readFds, $writeFds, $e, $this->timeout );
		   		if(!$res){
			   		echo socket_strerror(socket_last_error()) + "readFds";
			   		exit;
		   		}
	   			foreach ($readFds as $socket) {
	   				if ($socket == $this->master){
	   					$client = socket_accept($socket);
	   					if(!$client){
	   						echo socket_strerror(socket_last_error()) + "client";
			   				exit;
	   					}
	   					$this->connectPool[] = $client;
	   				}
	   				else
	   				{
	   					echo "bytes\n";
	   					$bytes = socket_recv($socket, $buffer, 2048, 0);
	   					if(!$bytes){
	   						echo socket_strerror(socket_last_error()) + "bytes";
			   				exit;
	   					}
	   					if(!$this->handShake){
	   						$this->doHandShake($socket, $buffer);
	   					}
	   				}
	   			}
	   			// $client = socket_accept( $this->master );
	   		}
	   	}

	   	private function doHandShake($socket, $buffer)
	    {
	        list($resource, $host, $origin, $key) = $this->getHeaders($buffer);
	        $upgrade  = "HTTP/1.1 101 Switching Protocol\r\n" .
	            "Upgrade: websocket\r\n" .
	            "Connection: Upgrade\r\n" .
	            "Sec-WebSocket-Accept: " . $this->calcKey($key) . "\r\n\r\n";  //必须以两个回车结尾
	        socket_write($socket, $upgrade, strlen($upgrade));
	        $this->handShake = true;
	        return true;
	    }

	    //获取请求头
	    private function getHeaders( $req )
	    {
	        $r = $h = $o = $key = null;
	        if (preg_match("/GET (.*) HTTP/"              , $req, $match)) { $r = $match[1]; }
	        if (preg_match("/Host: (.*)\r\n/"             , $req, $match)) { $h = $match[1]; }
	        if (preg_match("/Origin: (.*)\r\n/"           , $req, $match)) { $o = $match[1]; }
	        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $req, $match)) { $key = $match[1]; }
	        return [$r, $h, $o, $key];
	    }
	}