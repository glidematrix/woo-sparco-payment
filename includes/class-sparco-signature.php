<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class SparcoSignature{

    public function __construct($pubKey=null, $secKey=null) {
        $this->timestamp = time();
        $this->pubKey = $pubKey;
        $this->secKey = $secKey;
    }

    public function generate($payload = null)
    {
        $msg = "";
        $data = [

        ];

        if($payload){
            $data = array_merge($data, $payload);
        }

        $signedFields = array_key_exists('signedFields', $data) ? $data['signedFields'] : null;

        if($signedFields){
            $signedFields = explode(',', $signedFields);
        }else{
            $signedFields = array_keys($data);
            array_push($signedFields, "signedFields");
            
        }

        $data['signedFields'] = implode(',', $signedFields);

        $msg  =  implode(',', array_map(function($key) use ($data){ return "$key={$data[$key]}";},$signedFields));

        $sig = base64_encode(hash_hmac('sha256', $msg, $this->secKey, true));

        $data["signature"] = $sig;


        return $data;
    }

    public function verify($payload = null)
    {
        $this->pubKey = array_key_exists('pubKey', $payload) ? $payload['pubKey'] : $this->pubKey;
        $this->timestamp = array_key_exists('timestamp', $payload) ? $payload['timestamp'] : null;
        $sig = array_key_exists('signature', $payload) ? $payload['signature'] : null;

        $generatedSig = $this->generate($payload)['signature'];

        return [
            'signature' => $sig,
            'generatedSig' => $generatedSig,
            'isVerified' => ($sig  == $generatedSig)
        ];
    }

}