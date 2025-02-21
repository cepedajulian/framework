<?php

namespace Dasintranet\Framework;

use stdClass;

class Api{
	public $method 	= 'GET';
	public $url 	= null;
	public $body 	= '';
	public $token	= '';
	public $status	= 0;
	public $response;
	
	public function __construct( $url = null, $method = null, $body = null, $token = null){
		if($url 	!= null){$this->url		= $url;}  
		if($method 	!= null){$this->method 	= $method;}
		if($body	!= null){$this->body	= $body;}
		if($token	!= null){$this->token	= $token;}

		if($url != null){
			return $this->exec();
		}
    }

	public function getAccess($url, $client_id, $client_secret){
		$authorization 	= base64_encode($client_id.':'.$client_secret);
		$header 		= array("Authorization: Basic {$authorization}","Content-Type: application/json");
	
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_RETURNTRANSFER => true
		));
		
		//$this->response = curl_exec($curl);
		
		$temp = curl_exec($curl);

		try {
			if(is_array(json_decode($temp,true))){
				$this->response = json_decode($temp);
			}else{
				$this->response = $temp;
			}
		} catch  (\Exception $e) {
			$this->response = $temp;
		}
	
		if(curl_errno($curl)){
			curl_close($curl);
			echo 'Curl PHP error:\n\n' . curl_error($curl);
			http_response_code(500);
            die();
		}
		
		curl_close($curl);
		return $this->response;
	}

	public function exec($url = null, $method = null, $body = null, $token = null){
		if($url 	!= null){$this->url		= $url;}  
		if($method 	!= null){$this->method 	= $method;}
		if($body	!= null){$this->body	= $body;}
		if($token	!= null){$this->token	= $token;}

		$header 	= array("Authorization: Bearer {$this->token}" , "Content-Type: application/json");	
		$curl 		= curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $this->url,
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_RETURNTRANSFER => true
		));
	
		if($this->method == 'POST'){
			curl_setopt($curl, CURLOPT_POSTFIELDS, $this->body);
			curl_setopt($curl, CURLOPT_POST, true);			
		}

		if($this->method == 'DELETE' OR $this->method == 'PUT' OR $this->method == 'PATCH'){
			curl_setopt($curl, CURLOPT_POSTFIELDS, $this->body);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->method);
		}

		$this->status 	= 0;
	
		$temp = curl_exec($curl);

		try {
			if(is_array(json_decode($temp,true))){
				$this->response = json_decode($temp);
			}else{
				$this->response = $temp;
			}
		} catch  (\Exception $e) {
			$this->response = $temp;
		}
		
		if(curl_errno($curl)){
			curl_close($curl);
			echo 'Curl PHP error:\n\n' . curl_error($curl);
			http_response_code(500);
            die();
		}
		
		curl_close($curl);
		return $this->response;
    }
}