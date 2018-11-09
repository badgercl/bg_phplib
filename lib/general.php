<?php

class HTTP {
	function httpdie($code, $msg){
		http_response_code($code);
		die($msg);
	}

	function request($url) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$server_output = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close ($ch);

		$res = json_decode($server_output, TRUE);
		$res['http_code'] = $http_code;
		return $res;
	}
}

class UTIL {
	static function startsWith($haystack, $needle){
		$length = mb_strlen($needle);
     	return (mb_substr($haystack, 0, $length) === $needle);
	}

	static function endsWith($haystack, $needle) {
    	$length = mb_strlen($needle);
    	if ($length == 0) {
        	return true;
    	}
	    return (mb_substr($haystack, -$length) === $needle);
	}

	static function LOG($m) {
		error_log(json_encode($m));
	}

	static function msg_chunks( $txt, $limit = 7500 ) {
		$res = [];
		$chunks = explode( "\n", $txt );

		$i = 0;
		foreach( $chunks as $chunk ) {
			if( !isset($res[$i]) ) $res[$i] = "";
			if( (mb_strlen($res[$i]) + mb_strlen($chunk)) < $limit ) {
				$res[$i] .= $chunk . "\n";
			} else {
				$i++;
			}
		}
		return $res;
	}
}

class TG_UTILS {
	static function is_admin($m, $telegram, $reply) {
		$chat_member = $telegram->getChatMember($m['chat']['id'], $m['from']['id']);
		if( !isset($chat_member['result']['status']) ) return FALSE;

		$is_admin = $chat_member['result']['status'] == "creator" || $chat_member['result']['status'] == "administrator";
		if( !$is_admin ) {
			$telegram->sendMsg("Debes ser admin para ejecutar esta acción", $m['chat']['id'], $m['message_id']);
		}
		
		return $is_admin;
	} 
}
