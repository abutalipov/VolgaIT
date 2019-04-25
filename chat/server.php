<?php
$host = 'localhost'; // адрес сервера 
$database = 'chat'; // имя базы данных
$user = 'root'; // имя пользователя
$password = ''; // пароль
class WebSocketServer
{
    const KEY = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    const CONNECTION_PING = 60;
    const CONNECTION_TIMEOUT = 10;

	
    public $clients = [];
    private $wsConnection;

    public function __construct($port)
    {
        $socket = socket_create_listen($port);
        socket_setopt($socket, SOL_SOCKET, SO_REUSEADDR, true);
        $error = socket_last_error($socket);
        socket_clear_error();
        if (!$socket) {echo "no_create_soc";}
        if ($error) {echo "err ".$error;}
        $this->wsConnection = $socket;
        socket_set_nonblock($this->wsConnection);
    }

    public function __destruct()
    {
        if (!$this->wsConnection) {return;}
        foreach ($this->clients as $client) {$this->disconnectClient($client[0], 1000);}
        socket_shutdown($this->wsConnection);
        socket_close($this->wsConnection);
    }

    public function listen()
    {
        if (!$this->wsConnection) return false;
        $works = [];
        while ($client = socket_accept($this->wsConnection)) {
            socket_set_nonblock($client);
            $address = $this->getAddress($client);
            $headers = $data = [];
            $buffer = '';
            $try = 0;
            while (true) {
                $byte = socket_read($client, 1);
                if ($byte === false)
                    if ($try++ >= 1000) {echo "err read";} else {
                        usleep(10*1000);
                        continue;
                    }
                $try = 0;
                if ($byte === "\r") {continue;}
                if ($byte === "\n") {
                    if (empty($buffer)) {break;} else {
                        $data[] = $buffer;
                        $buffer = '';
                    }
                } else {$buffer .= $byte;}
            }
            $get = false;
            foreach ($data as $key => $value) {
                if ($key === 0) {
                    $get = explode(' ', $value);
                    $get = trim($get[1], '/');
                    continue;
                }
                list($key, $value) = explode(':', $value);
                $headers[$key] = trim($value);
            }
            if (!($headers['Connection'] === 'Upgrade')) {
                $response = "HTTP/1.1 400 Bad Request\r\n\r\n";
                socket_write($client, $response);
                socket_close($client);
                echo "bad req";
            }
            if (!($headers['Sec-WebSocket-Version']) == 13) {
                $response = "HTTP/1.1 400 Bad Request\r\n\r\n";
                socket_write($client, $response);
                socket_close($client);
                echo "bad req";
            }
            $cookies = isset($headers['Cookie']) ? $headers['Cookie'] : null;
        
            $hash = base64_encode(sha1($headers['Sec-WebSocket-Key'] . self::KEY, true));
            $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: WebSocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: {$hash}\r\n" .
                (isset($headers['Sec-WebSocket-Protocol']) ? "Sec-WebSocket-Protocol: chat\r\n\r\n" : "\r\n");
            if (!socket_write($client, $response)) {
                socket_close($client);
                echo "err hs";
            }
            if (isset($this->clients[$address])) {
                echo "address isset";
            }
            $this->clients[$address] = [$client, time()];
            $works[] = [$address, $get, $cookies];
			$this->online_now();
        }
        return $works;
    }

public function readFrom($address)
    {
        if (!isset($this->clients[$address])) {return false;}
        $client = $this->clients[$address];
        if ($client[1] < (time() - self::CONNECTION_PING)) {$this->sendFrameTo($client[0], '{"type":"ping"}', 0x9);}
        return $this->readFramesFrom($client[0]);
    }

    public function sendTo($address, $data)
    {
        if (!isset($this->clients[$address])) {return false;}
        return $this->sendFrameTo($this->clients[$address][0], $data);
    }
    private function readFramesFrom($socket)
    {
        $frames = 0;
        $result = [];
        $i = 0;
        
            while (true) {
                do {
                    if ($byte = $this->_readFrom($socket, 1, false)) {$byte = ord($byte);} else {break 2;}
                    $fin = ($byte & 0b10000000) === 128;
                    if (!isset($opcode)) {$opcode = $byte & 0b00001111;}
                    $byte = ord($this->_readFrom($socket, 1));
                    $mask = ($byte & 0b10000000) === 128;
                    $length = ($byte & 0b01111111);
                    if ($length === 126) {
                        for ($j = 0, $length = 0; $j < 2; $j++) {$length = ($length << 8) | ord($this->_readFrom($socket, 1));}
                    } elseif ($length === 127) {
                        for ($j = 0, $length = 0; $j < 8; $j++) { $length = ($length << 8) | ord($this->_readFrom($socket, 1)); }
                    }
                    if (!isset($result[$frames])) {$result[$frames] = ''; }
                    if ($mask) {
                        $mask = $this->_readFrom($socket, 4);
                        $i += 4;
                        for ($j = 0; $j < $length; $j += 4) {$result[$frames] .= $this->_readFrom($socket, min($length - $j, 4)) ^ $mask;}
                    } else {
                        $result[$frames] .= $this->_readFrom($socket, $length);
                    }
                } while (!$fin);
                $frames++;
                switch ($opcode) {
                    case 0x1 : 
                    case 0x2 :
                        break;
                    case 0x8 :
                        $this->disconnectClient($this->getAddress($socket), 1000);
                        break;
                    case 0x9 :
                        $this->sendFrameTo($socket, $result[$frames], 0xA);
                        break;
                    case 0xA : 
                        $this->onPong($socket);
                        break;
                    case 0x0 :
                        $this->disconnectClient($this->getAddress($socket), 1002);
                        return false;
                }
                if ($opcode < 1 || $opcode > 2) {
                    unset($result[$frames--]);
                }
            }
        
        return $result;
    }
    private function _readFrom($socket, $length, $need = true)
    {
        $buffer = '';
        $tryCount = 0;
        while (($in = socket_read($socket, $length)) || $need) {
            $buffer .= $in;
            if (strlen($in) < $length) {
                if ($tryCount > 1000) {echo "err read";}
                $tryCount++;
                $length -= strlen($in);
                usleep(10*1000);
            } else { break;}
        }
        return strlen($buffer) === 0 ? false : $buffer;
    }
private function onPong($socket)
{
	$address = $this->getAddress($socket);
	if (!isset($this->clients[$address])) {
		throw new RuntimeException('FATAL ERROR: ON PONG ADDRESS NOT FOUND');
	}
	$this->clients[$address][1] = time();
}
 private function sendFrameTo($socket, $data, $opcode = 0x01)
    {
        $frame = chr((0b10000000 | $opcode));
        $length = strlen($data);
        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length < 65536) {
            $frame .= chr(126) . chr(($length >> 8) & 0b11111111) . chr($length & 0b11111111);
        } else {
            $frame .= chr(127) . chr(($length >> 64) & 0b11111111) . // 1 byte
                chr(($length >> 56) & 0b11111111) . // 2 byte
                chr(($length >> 48) & 0b11111111) . // 3 byte
                chr(($length >> 40) & 0b11111111) . // 4 byte
                chr(($length >> 32) & 0b11111111) . // 5 byte
                chr(($length >> 24) & 0b11111111) . // 6 byte
                chr(($length >> 16) & 0b11111111) . // 7 byte
                chr($length & 0b11111111); // 8 byte
        }
        $data=$frame . $data;
    
        $bytes = 0;
        $length = strlen($data);
        do {
            $result = socket_write($socket, $data);
            if ($result === false) {
                return false;
            } elseif ($result === 0) {
            } else {
                $data = substr($data, $result);
                $bytes += $result;
            }
        } while ($bytes < $length);
        return true;
    }

    private function disconnectClient($address, $code)
    {
        if (!isset($this->clients[$address])) {return false;}
        $this->sendFrameTo($this->clients[$address][0], $code, 0x8);
        socket_close($this->clients[$address][0]);
        unset($this->clients[$address]);
        $this->online_now();
        return true;
    }
    private function getAddress($socket)
    {
        if (!$socket) {return false;}
        $address = '';
        $port = 0;
        if (socket_getpeername($socket, $address, $port)) {return $address . ':' . $port;} else {return false;}
    }
	public function start($address){
		if (!isset($this->clients[$address]["uid"]))
			$this->clients[$address]["uid"]=uniqid();
			$msg=json_encode(Array("type"=>"uid_set","msg"=>$this->clients[$address]["uid"]));
			$this->sendTo($address,$msg );
			return true;
	}
	private function online_now(){
		$count=count(array_keys($this->clients));
		$data=Array("type"=>"online_now","msg"=>$count);
		$msg=json_encode($data);
		if ($count>0)
		foreach (array_keys($this->clients) as $add) {
			$this->sendTo($add, $msg);
		}
	}
}
$server = new WebSocketServer(5655);
while (false !== ($activity = $server->listen())) {
	
    foreach (array_keys($server->clients) as $address) {
		if ($data = $server->readFrom($address)) {
			$data=json_decode($data[0],true);
			print_r($data);
			$link = mysqli_connect($host, $user, $password, $database) 	or die("Ошибка " . mysqli_error($link));
			switch($data['type']){
				case 'start':
					$msg=array('type'=>'history','data'=>array());
					$query='SELECT * FROM `history` LIMIT 20';
					$result=mysqli_query($link, $query) or die("Ошибка " . mysqli_error($link)); 
					while ($row = $result->fetch_assoc()) {
						$msg['data'][]=$row;
					}
					
					$server->start($address);
					$msg=json_encode($msg);
					$server->sendTo($address, $msg);
				break;
				case 'msg':
					 $query='INSERT INTO `history`(`name`, `msg`) VALUES ("'.$data['name'].'","'.$data['msg'].'")';
					 mysqli_query($link, $query) or die("Ошибка " . mysqli_error($link)); 
					$data['time']=date("H:i:s" );
					$msg=json_encode($data);
					foreach (array_keys($server->clients) as $add) {
						$server->sendTo($add, $msg);
					}
				break;
			}
				mysqli_close($link);
        }
    }
    usleep(200000);
}