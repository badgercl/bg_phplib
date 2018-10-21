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
		return $json;
	}

	function sendMsg($msg, $uid, $reply_to_message_id = NULL){
		$msg = urlencode($msg);
		$cmd = $this->baseUrl . "/sendMessage?chat_id=$uid&text=$msg&parse_mode=HTML";
		if ($reply_to_message_id) $cmd .= "&reply_to_message_id=".$reply_to_message_id;
		$res = $this->http->request($cmd);

	}

	function editMessageText($msg, $chat_id, $message_id) {
		$msg = urlencode($msg);
		UTIL::LOG($message_id);	
		$cmd = $this->baseUrl . "/editMessageText?chat_id=$chat_id&message_id=$message_id&text=$msg&parse_mode=HTML&reply_markup={}";
		UTIL::LOG($cmd);
		$res = $this->http->request($cmd);

	}

	function setGroupTitle($title, $m){
		$uid = $m['chat']['id'];
		$title = urlencode($title);
		$cmd = $this->baseUrl . "/setChatTitle?chat_id=$uid&title=$title";
		$res = $this->http->request($cmd); 
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

	function getChatMembersCount( $chat_id ) {
		$cmd = $this->baseUrl . "/getChatMembersCount?chat_id=$chat_id";
		return $this->http->request($cmd);
	}

	function exportChatInviteLink( $gid ) {
		$cmd = $this->baseUrl . "/exportChatInviteLink?chat_id=$gid";
		return $this->http->request($cmd);
	}

	function getChatAdministrators( $gid ) {
		$cmd = $this->baseUrl . "/getChatAdministrators?chat_id=$gid";
		return $this->http->request($cmd);
	}

	function getChat( $chat_id ) {
		$cmd = $this->baseUrl . "/getChat?chat_id=$chat_id";
		return $this->http->request($cmd);
	}

	function tgchat_action($action, $uid, $token){
		$cmd = "https://api.telegram.org/bot$token/sendChatAction?chat_id=$uid&action=$action";
		file_get_contents($cmd);	
	}

	function tgshowoptions($options, $qid, $token){
		$res = [];
		foreach($options as $o){
			$rid = md5($qid . $o['title'] . $o['msg']);
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
