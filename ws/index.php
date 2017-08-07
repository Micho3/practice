<?php
	header("Context-type:text/html; charset=utf-8");
	class WebSocket
	{
		private $port = 2000;
		private $address = "";
		private $master = null;
		private $handshake = false;
		private $sockets = array();
		private $timeout = 60;
		public function __construct($address, $port)
		{
			echo "construct start";
			$this->port = $port;
			$this->address = $address;
			$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)or die("socket_create() failed");
			socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);
			socket_bind($this->master, $this->address, $this->port);
			socket_listen($this->master, 2);
			$this->sockets[] = $this->master;
			while (true) 
			{
				$write = null;
				$except = null;
				@socket_select($this->sockets, $write, $except, $this->timeout);
				foreach ($this->sockets as $socket)	
				{
					if($socket == $this->master)
					{
						$client = socket_accept($socket);
						if($client < 0)
						{
							echo "socket_accept error";
							file_put_contents("./test.txt", "socket_accept：" . socket_strerror(socket_last_error()) . "\n", FILE_APPEND);
						}
						else
						{
							array_push($this->sockets, $client);
						}
					}
					else
					{
						@$bytes = socket_recv($socket,$buffer,2048,0);
						if($bytes == 0)
						{
							echo "no bytes returned";							
							return ;
						}
						if(!$this->handshake)
						{
							$this->doHandshake($socket, $buffer);
						}
						else
						{
							$buffer = $this->decode($buffer);
							// $buffer = json_decode($buffer);
							// foreach ($buffer as $b) {
							// 	file_put_contents("./test.txt", "buffer: ". string($b) ."\n");
							// }
							file_put_contents("./test.txt", "buffer: ". (string)($buffer) ."\n");
							// file_put_contents("./test.txt", "buffer: ". $buffer."\n");
							$data = array('nihao' => "nihaoa" );
							// $msg = $this->frame(json_encode($data));
							// socket_write( $socket, $msg, strlen($msg) );
							socket_write( $socket, "sfasfmsg", strlen("sfasfmsg") );
						}
					}
				}
			}
		}

		private function doHandshake($socket, $buffer)
		{
			// 获取加密key
			$acceptKey = $this->encry($buffer);
			$upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
						"Upgrade: websocket\r\n" .
		    			"Connection: Upgrade\r\n" .
		    			"Sec-WebSocket-Accept: " . $acceptKey . "\r\n" .
		    			"\r\n";
		    // 写入socket
		    socket_write($socket,$upgrade.chr(0), strlen($upgrade.chr(0)));
		    // 标记握手已经成功，下次接受数据采用数据帧格式
		    $this->handshake = true;
		}

		private function decode($buffer)
		{
			    $len = $masks = $data = $decoded = null;
			    $len = ord($buffer[1]) < 127;
			    if ($len === 126)  {
			        $masks = substr($buffer, 4, 4);
			        $data = substr($buffer, 8);
			    } else if ($len === 127)  {
			        $masks = substr($buffer, 10, 4);
			        $data = substr($buffer, 14);
			    } else  {
			        $masks = substr($buffer, 2, 4);
			        $data = substr($buffer, 6);
			    }
			    for ($index = 0; $index < strlen($data); $index++) {
			        $decoded .= $data[$index] ^ $masks[$index % 4];
			    }
			    return $decoded;
		}

		private function getKey($req)
		{
		    $key = null;
		    if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $req, $match))
		    {
		        $key = $match[1];
		    }
		    return $key;
		}

		private function encry($req){
		    $key = $this->getKey($req);
		    $mask = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
		    return base64_encode(sha1($key . $mask, true));
		}

		private function frame( $s )
	    {
		    $a = str_split($s, 125);
		    if (count($a) == 1) {
		        return "\x81" . chr(strlen($a[0])) . $a[0];
		    }
		    $ns = "";
		    foreach ($a as $o) {
		        $ns .= "\x81" . chr(strlen($o)) . $o;
		    }
		    return $ns;
	    }
	}

	$ws = new WebSocket("localhost","2000");