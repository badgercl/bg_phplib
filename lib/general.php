<?php

class HTTP {
	static function httpdie($code, $msg){
		http_response_code($code);
		die($msg);
	}

	static function request($url) {
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


