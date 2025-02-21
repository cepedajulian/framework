<?php

namespace Dasintranet\Framework;

class ApiFree{
	public $method 	= 'GET';
	public $url 	= null;
	public $body 	= '';
	public $status	= 0;
	public $response;
	
	public function __construct( $url = null, $method = null, $body = null){
		if($url 	!= null){$this->url		= $url;}  
		if($method 	!= null){$this->method 	= $method;}
		if($body	!= null){$this->body	= $body;}

		if($url != null){
			return $this->exec();
		}
    }

	public function exec($url = null, $method = null, $body = null){
		if($url 	!= null){$this->url		= $url;}  
		if($method 	!= null){$this->method 	= $method;}
		if($body	!= null){$this->body	= $body;}

		$header 	= array("Content-Type: application/json");	
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
		
		// $this->response = json_decode(curl_exec($curl));

		//// START Para compartir session con api
		$strCookie = session_name() . '=' . $_COOKIE[ session_name() ] . '; path=/';
        curl_setopt( $curl, CURLOPT_COOKIE, $strCookie );
        session_write_close();
		//// END Para compartir session con api

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
		curl_close($curl);
	
		session_start();	// Para compartir session con api

		return $this->response;
    }
}