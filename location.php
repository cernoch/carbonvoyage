<?php
require_once("../../../wp-load.php");

$err = "200 OK";
$out = array();
function finish() {
	global $out, $err;
	header("HTTP/1.1 $err");
	header("Content-Type: application/json");
	
	$mask = 0;
	if (defined('JSON_UNESCAPED_UNICODE'))
		$mask |= JSON_UNESCAPED_UNICODE;
	echo json_encode($out,$mask);
	die();
}

function getParam($name, $error) {
	global $out, $err;
	if (!isset($_GET[$name]) || ($_GET[$name] == '')) {
		$err = "400 Bad Request";
		$out['message'] = $error;
		finish();
	}
	return $_GET[$name];
}

function logUrl($url) {
	global $out;
	if (!isset($out['urls']))
		$out['urls'] = array();
	array_push($out['urls'], $url);
	return $url;
}

// http://dotclear.placeoweb.com/post/Formule-de-calcul-entre-2-points-wgs84-pour-calculer-la-distance-qui-separe-ces-deux-points
function carbonvoyage_wgs84dist($lat1,$lon1, $lat2,$lon2) { 
$r = 6366;
  
$lat1 = deg2rad($lat1);
$lon1 = deg2rad($lon1);
$lat2 = deg2rad($lat2);
$lon2 = deg2rad($lon2);
 
$ds= acos(sin($lat1)*sin($lat2)+cos($lat1)*cos($lat2)*cos($lon1-$lon2));
$dsr = $ds * $r;
 
$dp= 2 * asin(sqrt(
	pow( sin(($lat1-$lat2)/2) , 2)
	+ cos($lat1) * cos($lat2) * pow( sin(($lon1-$lon2)/2) , 2)
));
$dpr = $dp * $r;
 
return ($dsr + $dpr) / 2;
}


function carbonvoyage_response($r) {
	global $out, $err;
	
	if ($r instanceof WP_Error || wp_remote_retrieve_response_code($r) != 200) {
		$out['message'] = "Error communicating with Google API.";
		$out['details'] = wp_remote_retrieve_response_message($r);
		$err = "502 Bad Gateway";
		finish($out);
	}
	
	$d = wp_remote_retrieve_body($r);
	//echo $d;
	
	if ($d == "") {
		$out['message'] = "Google returned no data.";
		$err = "502 Bad Gateway";
		finish();
	}
	
	$j = json_decode($d);
	
	if ($j == null) {
		$out['message'] = "Google does not talk JSON.";
		$err = "502 Bad Gateway";
		finish();
	}
	
	if ($j->{'status'} == "OVER_QUERY_LIMIT") {
		$out['message'] = "Too busy. Try the request tomorrow.";
		$err = "503 Service Unavailable";
		finish();
	}

	if ($j->{'status'} == "ZERO_RESULTS") {
		$out['message'] = "Location not found.";
		$err = "400 Bad Request";
		finish();
	}
		
	if ($j->{'status'} == "MAX_ELEMENTS_EXCEEDED") {
		$out['message'] = "Enter exactly one origin and one destination.";
		$err = "400 Bad Request";
		finish($out);
	}
	
	if ($j->{'status'} == "REQUEST_DENIED") {
		$out['message'] = "Service is unavailable.";
		$err = "503 Service Unavailable";
		finish();
	}
	
	if ($j->{'status'} != "OK") {
		$out['message'] = "Unknown error.";
		$err = "500 Internal Server Error";
		finish();
	}

	return $j;
}

function carbonvoyage_resolve($addr,$sens) {
	global $sen;
	$url = logUrl(
		'http://maps.googleapis.com/maps/api/geocode/json'.
		"?address=".urlencode($addr).
		"&sensor=".urlencode($sens));
	
	$j = carbonvoyage_response(wp_remote_get($url));
	$out = array();
	$cnt = 0;
	foreach ($j->{'results'} as $res) {
		$cnt++;
		if ($cnt > 5)
			break;
		
		array_push($out, array(
			'name' => $res->{'formatted_address'},
			'lat' => $res->{'geometry'}->{'location'}->{'lat'},
			'lon' => $res->{'geometry'}->{'location'}->{'lng'},
		));
	}
	return $out;
}

function carbonvoyage_loc2list($locs) {
	$out = "";
	foreach ($locs as $loc) {
		if ($out != "")
			$out .= "|";
		$out .= $loc['name'];
	}
	return $out;
}

function carbonvoyage_driving_dist($ori, $dst) {	
	$beg = carbonvoyage_loc2list($ori);
	$end = carbonvoyage_loc2list($dst);
	
	$url = logUrl(
		'http://maps.googleapis.com/maps/api/distancematrix/json'.
		"?origins=".urlencode($beg).
		"&destinations=".urlencode($end).
		"&mode=".urlencode($mode).
		"&units=metric".
		"&language=en-US".
		"&sensor=false");
	
	$j = carbonvoyage_response(wp_remote_get($url));
	
	$out = array();
	foreach ($j->{'rows'} as $row) {
		foreach ($row->{'elements'} as $elm) {
			
			if ($elm->{'status'} == "ZERO_RESULTS") {
				array_push($out, array('message' => "No journey found."));
				continue;
			}

			if ($elm->{'status'} != "OK") {
				array_push($out, array('message' => "Unknown error.",
					                   'details' => $elm->{'status'}));
				continue;
			}
			
			$dist = $elm->{'distance'}->{'value'};
			array_push($out, array(
				'message' => "OK",
				'distance' => $dist/1000));
		}
	}
	return $out;
}

function carbonvoyage_plane_dist($ori,$dst) {
	$out = array();
	foreach ($ori as $beg)
		foreach ($dst as $end) 
			array_push($out,
				array('message' => "OK",
					'distance' => carbonvoyage_wgs84dist(
					$beg['lat'],$beg['lon'],
					$end['lat'],$end['lon']
				))
			);
	return $out;
}


$beg = getParam('from', 'Origin of the journey not specified.');
$end = getParam('to', 'Destination not specified.');
$bsn = getParam('from_sensor', "Usage of sensor 'from_sensor' not specified.");
$esn = getParam('to_sensor', "Usage of sensor 'to_sensor' not specified.");

$bsn = strtolower($bsn);
$esn = strtolower($esn);
if (($bsn != "true" && $bsn != "false") || 
	($esn != "true" && $esn != "false")) {
	$out['message'] = "Sensor value must be either 'true' or 'false'.";
	$err = "400 Bad Request";
	finish();
}

$ori = carbonvoyage_resolve($beg,$bsn);
$dst = carbonvoyage_resolve($end,$esn);

$out['from'] = $ori;
$out['to'] = $dst;

$out['air'] = carbonvoyage_plane_dist($ori,$dst);
$out['car'] = carbonvoyage_driving_dist($ori,$dst);



finish();
