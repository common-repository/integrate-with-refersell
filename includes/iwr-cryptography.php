<?php 
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
function integrate_rsn_encrypt($plaintext){
	$rsp_crypt_key = get_option('rsn-network-access-key', '');
	$rsp_crypt_key = hex2bin( $rsp_crypt_key);
	$rsp_crypt_iv_length = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
	$rsp_crypt_vector = mcrypt_create_iv($rsp_crypt_iv_length, MCRYPT_RAND);
	$rsp_ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $rsp_crypt_key, $plaintext, MCRYPT_MODE_CBC, $rsp_crypt_vector);
	$rsp_cipher_bin = $rsp_ciphertext.$rsp_crypt_vector;
	$rsp_cipher =  base64_encode($rsp_cipher_bin);
	return $rsp_cipher;
}
function integrate_rsn_decrypt($rsp_cipher){
	$rsp_crypt_key = get_option('rsn-network-access-key', '');
	$rsp_crypt_key = hex2bin( $rsp_crypt_key);
	$rsp_cipher_bin = base64_decode($rsp_cipher);
	$rsp_crypt_iv_length = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
	$rsp_crypt_vector = substr($rsp_cipher_bin, 0-$rsp_crypt_iv_length);
	if(strlen($rsp_crypt_vector)==$rsp_crypt_iv_length){
		$rsp_ciphertext = substr($rsp_cipher_bin, 0, 0-$rsp_crypt_iv_length);
		$plaintext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $rsp_crypt_key, $rsp_ciphertext, MCRYPT_MODE_CBC, $rsp_crypt_vector);
		return trim($plaintext);
	}else{
		return;
	}
}
function rsn_public_open($rsp_cipher, $public_key, $ekey){
	$e_rsp_crypt_key = base64_decode($ekey);
	openssl_public_decrypt($e_rsp_crypt_key, $rsp_crypt_key, $public_key);
	$rsp_cipher_bin = base64_decode($rsp_cipher);
	$rsp_crypt_iv_length = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
	$rsp_crypt_vector = substr($rsp_cipher_bin, 0-$rsp_crypt_iv_length);
	if(strlen($rsp_crypt_vector)==$rsp_crypt_iv_length){
		$rsp_ciphertext = substr($rsp_cipher_bin, 0, 0-$rsp_crypt_iv_length);
		$plaintext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $rsp_crypt_key, $rsp_ciphertext, MCRYPT_MODE_CBC, $rsp_crypt_vector);
		return trim($plaintext);
	}else{
		return;
	}
}
?>