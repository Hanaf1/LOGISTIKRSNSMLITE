<?php
	use Workerman\Worker;
	use Workerman\Timer;
	require_once __DIR__ . '/vendor/autoload.php' ;
	
	// WebSocket lokal untuk browser. Event dari PHP web request dibaca dari queue file.
	$ws = new Worker('websocket://0.0.0.0:3892');
	$queueFile = __DIR__ . '/tmp/logistik_notifications.queue';
	$queueOffset = 0;

	$broadcast = function ($data) use ($ws) {
		$message = is_string($data) ? $data : json_encode($data);
		foreach ($ws->connections as $connection) {
			$connection->send($message);
		}
	};

	$ws->onWorkerStart = function () use (&$queueOffset, $queueFile, $broadcast) {
		Timer::add(0.5, function () use (&$queueOffset, $queueFile, $broadcast) {
			if (!is_file($queueFile)) return;
			$size = filesize($queueFile);
			if ($size === false || $size <= $queueOffset) return;
			$handle = @fopen($queueFile, 'rb');
			if (!$handle || !flock($handle, LOCK_SH)) return;
			fseek($handle, $queueOffset);
			while (($line = fgets($handle)) !== false) {
				$event = json_decode(trim($line), true);
				if (is_array($event) && ($event['type'] ?? '') === 'logistik_notification') {
					$broadcast($event);
				}
			}
			$queueOffset = ftell($handle);
			flock($handle, LOCK_UN);
			fclose($handle);
		});
	};
	
	// Jika ada yang terhubung
	$ws->onConnect = function($connection){
		$remote_ip = $connection->getRemoteIp();
		
		$connection->onWebSocketConnect = function($connection) use ($remote_ip){
			print("$remote_ip - Berhasil terhubung\n");
			unset($remote_ip);
		};
	};
	
	
	// Kompatibilitas: pesan dari browser juga tetap dibroadcast.
	$ws->onMessage = function($connection, $data) use($ws){
		
		// Broadcast datanya ke semua yang terhubung
		foreach($ws->connections as $connection_sub){
			$connection_sub->send($data);
		}
		
	};
	
	// Jika terputus
	$ws->onClose = function($connection){
		$remote_ip = $connection->getRemoteIp();
		print("$remote_ip - Telah terputus!\n");
	};
	
	
	Worker::runAll();
	
?>
