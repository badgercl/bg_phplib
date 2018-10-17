<?php

class Telegram {
	private $db;
	private $baseUrl;
	private $http;
	public $username;

	function __construct($token, $username, $db, $http) {
		$this->baseUrl = "https://api.telegram.org/bot" . $token;
		$this->db = $db;
		$this->username = mb_strtolower($username);	
		$this->http = $http;	
	}

	function parseInput(){
		$postData = file_get_contents('php://input');
		if(!isset($postData)) return NULL;
		$json = json_decode($postData, true);
		
		$m = $json;
		if( isset($json['message']) ) { 
			$m = $json['message'];
			$m = $this->getEntities ($m);
		}
		else if(isset($json['location'])) $m = $json;
		else if(isset($json['inline_query'])) $m = $json['inline_query'];
		return $m;
	}

	function getEntities($m) {
		if( !isset($m['entities']) ) return $m;
		$cmd = NULL;
		foreach ( $m['entities'] as &$entity ) {
			$entity['entity'] = mb_substr( $m['text'], $entity['offset'], $entity['length'] );
			if ($entity['type'] == "bot_command" && $entity['offset'] == 0) { 
				$m['bot_command'] = [
					'cmd' => $this->cleanCommand( $entity['entity'] ),
					'txt' => trim(mb_substr($m['text'], $entity['length']))
				];
			} 
		}
		unset($entity);
		return $m;
	}

	private function cleanCommand($cmd) {
		$cmd = mb_strtolower($cmd);
		return str_replace($this->username, "", $cmd);
	}

	function start($msg, $m, $token, $start){
	  $f = $m['from'];
	  $uid = $this->db->real_escape_string($f['id']);
	  $first_name = $this->db->real_escape_string(isset($f['first_name'])?$f['first_name']:"undefined");
	  $last_name = $this->db->real_escape_string(isset($f['last_name'])?$f['last_name']:"undefined");
	  $username = $this->db->real_escape_string(isset($f['username'])?$f['username']:"undefined");
	  $language_code = $this->db->real_escape_string(isset($f['language_code'])?$f['language_code']:"AA");

	  $sql = "SELECT * FROM tg_users WHERE uid = '$uid'";
	  $res = DbConfig::sql($this->db, $sql);
		if(!$res) {
			error_log($sql);
	        error_log($this->db->error);
		}
	  if(count($res)==0){
	    $sql = "INSERT INTO tg_users (uid, first_name, last_name, username, language_code, start) VALUES ('$uid', '$first_name', '$last_name', '$username', '$language_code'," . ($start?1:0) . ")";
		//error_log($sql);
	    if(!DbConfig::update($this->db, $sql)) {
			error_log($sql);
			error_log($this->db->error);
		}
	  }
	  else{
	    $sql = "UPDATE tg_users SET first_name='$first_name', last_name='$last_name', username='$username', language_code='$language_code'";
		if($start) $sql .= ' ,start=1';
		$sql .= " WHERE uid='$uid'";
	    if(!DbConfig::update($this->db, $sql)) {
			error_log($sql);
	        error_log($this->db->error);
		}
	  }
	  if($msg) tgsend_msg($msg, $uid, $token);
	}




	function sendMsg($msg, $uid, $reply_to_message_id = NULL){
		$msg = urlencode($msg);
		$cmd = $this->baseUrl . "/sendMessage?chat_id=$uid&text=$msg&parse_mode=HTML";
		if ($reply_to_message_id) $cmd .= "&reply_to_message_id=".$reply_to_message_id;
		$res = $http->request($cmd);

	}

	function editMessageText($msg, $uid, $message_id) {
		$msg = urlencode($msg);
		$cmd = $this->baseUrl . "/editMessageText?message_id=$message_id&text=$msg&parse_mode=HTML";
		$res = $http->request($cmd);

	}

	function setGroupTitle($title, $m){
		$uid = $m['chat']['id'];
		$title = urlencode($title);
		$cmd = $this->baseUrl . "/setChatTitle?chat_id=$uid&title=$title";
		$res = $http->request($cmd); 
		if ($res['http_code'] != 200 ) {
			$this->sendMsg("No puedo cambiar el título. Debo ser admin de este grupo.", $uid, $m['message_id']);
		}
		return $res;
	}

	function tgrequest_geo($msg, $uid, $token){
		$msg = urlencode($msg);
		$reply_mark = urlencode(json_encode(
			['one_time_keyboard' => TRUE, 
			 'keyboard' => [[ ['text' => 'Enviar localización', 
								'request_location' => TRUE] ]] ]));
		$cmd = "https://api.telegram.org/bot$token/sendMessage?chat_id=$uid&text=$msg&parse_mode=HTML&reply_markup=$reply_mark";
		$res = file_get_contents($cmd);
	}

	function showOptions($msg, $options, $uid, $selective) {
		if(!is_array($options) || count($options) <= 0) return;
		$reply_mark = [];
		$reply_mark['one_time_keyboard'] = TRUE;
		$reply_mark['keyboard'] = [];
		foreach($options as $o){
			$reply_mark['keyboard'][] = [['text'=>$o]];
		}
		$reply_mark = urlencode(json_encode($reply_mark));
	    $cmd = $this->baseUrl . "/sendMessage?chat_id=$uid&text=$msg&parse_mode=HTML&reply_markup=$reply_mark";
	    if ($selective) $cmd .= "&selective=true";
		$res = $http->request($cmd);
	}

	function showInlineOptions($msg, $options, $uid, $reply_to_message_id = NULL, $selective = true) {
		if(!is_array($options) || count($options) <= 0) return;

		$reply_mark = [];
		$reply_mark['inline_keyboard'] = [];
		foreach($options as $o){
			if(is_array($o)) $reply_mark['inline_keyboard'][] = [$o];
			else $reply_mark['inline_keyboard'][] = [['text'=>$o]];
		}

		$reply_mark = urlencode(json_encode($reply_mark));
	    $cmd = $this->baseUrl . "/sendMessage?chat_id=$uid&text=$msg&parse_mode=HTML&reply_markup=$reply_mark";
	    if ($reply_to_message_id) $cmd .= "&reply_to_message_id=" . $reply_to_message_id;
	    if ($selective) $cmd .= "&selective=true";
	    echo $cmd;
		$res = $this->http->request($cmd);
	}

	function dismissKeyboard($msg, $uid) {
		$reply_mark = [ 'remove_keyboard' => TRUE ];
		$reply_mark = urlencode(json_encode($reply_mark));
		$cmd = $this->baseUrl . "/sendMessage?chat_id=$uid&text=$msg&parse_mode=HTML&reply_markup=$reply_mark";
		$http->request($cmd);
	}

	function getChatMember($chat_id, $user_id) {
		$cmd = $this->baseUrl . "/getChatMember?chat_id=$chat_id&user_id=$user_id";
		return $this->http->request($cmd);
	}

	function tgchat_action($action, $uid, $token){
		$cmd = "https://api.telegram.org/bot$token/sendChatAction?chat_id=$uid&action=$action";
		file_get_contents($cmd);	
	}

	function tgshowoptions($options, $qid, $token){
		$res = [];
		foreach($options as $o){
			$rid = md5($qid . 
		$o['title'] . 
	$o['msg']);
			$res[] = [
				'type' => 'article',
			'id' => $rid,
			'title' => $o['title'],
			'input_message_content' => ['message_text'=>$o['msg'], 'parse_mode' => 'HTML']
			];
		}
		$results = urlencode(json_encode($res));
		error_log(print_r(json_encode($res), TRUE));
		$cmd = "https://api.telegram.org/bot$token/answerInlineQuery?inline_query_id=$qid&results=$results";
	 	//error_log($cmd);
	    $res = file_get_contents($cmd);
	}
}
