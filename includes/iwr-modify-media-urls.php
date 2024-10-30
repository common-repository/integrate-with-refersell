<?php 
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
add_filter( 'wp_get_attachment_url', 'iwr_modify_wp_get_attachment_url', 99, 2 );
add_filter( 'get_image_tag', 'iwr_modify_get_image_tag', 99, 6 );
add_filter( 'wp_get_attachment_image_src', 'iwr_modify_wp_get_attachment_image_src', 99, 4 );
add_filter( 'wp_prepare_attachment_for_js', 'iwr_modify_wp_prepare_attachment_for_js', 99, 3 );
add_filter( 'image_get_intermediate_size', 'iwr_modify_image_get_intermediate_size', 99, 3 );
add_filter( 'get_attached_file', 'iwr_modify_get_attached_file', 10, 2 );
function iwr_modify_wp_get_attachment_url($requested_url, $attachment_id){
	$attachment_real_location = get_the_guid($attachment_id);
	$attchment_home = parse_url($attachment_real_location);
	$this_website = parse_url(home_url());
	if($attchment_home['host']===$this_website['host']){
		return $requested_url;
	}
	if(!empty($requested_url)&&!empty($attachment_real_location)){
		$modified_url = iwr_get_rsn_resource_url($requested_url,$attachment_real_location);
		return $modified_url;
	}
	return $requested_url;
}
function iwr_modify_get_image_tag($html, $attachment_id, $alt, $title, $align, $size){
	$attachment_real_location = get_the_guid($attachment_id);
	$attchment_home = parse_url($attachment_real_location);
	$this_website = parse_url(home_url());
	if($attchment_home['host']===$this_website['host']){
		return $html;
	}
	preg_match( '@\ssrc=[\'\"]([^\'\"]*)[\'\"]@', $html, $matches );
	if ( ! isset( $matches[1] ) ) {
		// Can't establish img src
		return $html;
	}
	$requested_url = $matches[1];	
	$modified_url = iwr_get_rsn_resource_url($requested_url,$attachment_real_location);
	return str_replace( $requested_url, $modified_url, $html );
}
function iwr_modify_wp_get_attachment_image_src($image, $attachment_id, $size, $icon){
	$attachment_real_location = get_the_guid($attachment_id);
	$attchment_home = parse_url($attachment_real_location);
	$this_website = parse_url(home_url());
	if($attchment_home['host']===$this_website['host']){
		return $image;
	}
	$requested_url = $image[0];
	$modified_url = iwr_get_rsn_resource_url($requested_url,$attachment_real_location);
	$image[0] = $modified_url;
	return $image;
}
function iwr_modify_wp_prepare_attachment_for_js($response, $attachment, $meta){
	$attachment_real_location = get_the_guid($attachment->ID);
	$attchment_home = parse_url($attachment_real_location);
	$this_website = parse_url(home_url());
	if($attchment_home['host']===$this_website['host']){
		return $response;
	}
	$requested_url = $response['url'];
	$response['url'] = iwr_get_rsn_resource_url($requested_url,$attachment_real_location);
	if ( isset( $response['sizes'] ) && is_array( $response['sizes'] ) ) {
		foreach ( $response['sizes'] as $key => $value ) {
			$response['sizes'][ $key ]['url'] = iwr_get_rsn_resource_url( $value['url'], $attachment_real_location);
		}
	}
	return $response;
}
function iwr_modify_image_get_intermediate_size($data, $attachment_id, $size){
	$attachment_real_location = get_the_guid($attachment_id);
	$attchment_home = parse_url($attachment_real_location);
	$this_website = parse_url(home_url());
	if($attchment_home['host']===$this_website['host']){
		return $data;
	}
	if ( isset( $data['url'] ) ) {
		$data['url'] = iwr_get_rsn_resource_url($data['url'], $attachment_real_location);
	}
	return $data;
}
function iwr_modify_get_attached_file($requested_url, $attachment_id){
	$attachment_real_location = get_the_guid($attachment_id);
	$attchment_home = parse_url($attachment_real_location);
	$this_website = parse_url(home_url());
	if($attchment_home['host']===$this_website['host']){
		return $requested_url;
	}
	$modified_url = iwr_get_rsn_resource_url($requested_url, $attachment_real_location);
	return $modified_url;
}
function iwr_get_rsn_resource_url($requested_url,$attachment_real_location){
	$attachment_real_dir_parts = explode('/', $attachment_real_location);
	array_pop($attachment_real_dir_parts);
	$requested_url_parts = explode('/', $requested_url);
	$attachment_real_dir_parts[]=array_pop($requested_url_parts);
	return implode('/',$attachment_real_dir_parts);
}
?>