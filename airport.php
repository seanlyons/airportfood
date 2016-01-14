<?php

/**
 * Yelp API v2.0 code sample.
 *
 * This program demonstrates the capability of the Yelp API version 2.0
 * by using the Search API to query for businesses by a search term and location,
 * and the Business API to query additional information about the top result
 * from the search query.
 * 
 * Please refer to http://www.yelp.com/developers/documentation for the API documentation.
 * 
 * This program requires a PHP OAuth2 library, which is included in this branch and can be
 * found here:
 *      http://oauth.googlecode.com/svn/code/php/
 * 
 * Sample usage of the program:
 * `php sample.php --term="bars" --location="San Francisco, CA"`
 */

// Enter the path that the oauth library is in relation to the php file
require_once('lib/OAuth.php');
require_once('/home/sean/projects/common/orm.php');
require_once('/home/sean/projects/common/utils.php');

// Set your OAuth credentials here  
// These credentials can be obtained from the 'Manage API Access' page in the
// developers documentation (http://www.yelp.com/developers)

$secrets = new Secrets;
$CONSUMER_KEY = $secrets->get('yelp', 'consumer_key');
$CONSUMER_SECRET = $secrets->get('yelp', 'consumer_secret');
$TOKEN = $secrets->get('yelp', 'token');
$TOKEN_SECRET = $secrets->get('yelp', 'token_secret');

$API_HOST = 'api.yelp.com';
$DEFAULT_TERM = 'dinner';
$DEFAULT_LOCATION = 'San Francisco, CA';
$SEARCH_LIMIT = 3;
$SEARCH_PATH = '/v2/search/';
$BUSINESS_PATH = '/v2/business/';


/** 
 * Makes a request to the Yelp API and returns the response
 * 
 * @param    $host    The domain host of the API 
 * @param    $path    The path of the APi after the domain
 * @return   The JSON response from the request      
 */
function request($host, $path) {
    $unsigned_url = "http://" . $host . $path;

    // Token object built using the OAuth library
    $token = new OAuthToken($GLOBALS['TOKEN'], $GLOBALS['TOKEN_SECRET']);

    // Consumer object built using the OAuth library
    $consumer = new OAuthConsumer($GLOBALS['CONSUMER_KEY'], $GLOBALS['CONSUMER_SECRET']);

    // Yelp uses HMAC SHA1 encoding
    $signature_method = new OAuthSignatureMethod_HMAC_SHA1();

    $oauthrequest = OAuthRequest::from_consumer_and_token(
        $consumer, 
        $token, 
        'GET', 
        $unsigned_url
    );
    
    // Sign the request
    $oauthrequest->sign_request($signature_method, $consumer, $token);
    
    // Get the signed URL
    $signed_url = $oauthrequest->to_url();
    
    // Send Yelp API Call
    $ch = curl_init($signed_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    $data = curl_exec($ch);
    curl_close($ch);
    
    return $data;
}

/**
 * Query the Search API by a search term and location 
 * 
 * @param    $term        The search term passed to the API 
 * @param    $location    The search location passed to the API 
 * @return   The JSON response from the request 
 */
function search($nearest) {
    $url_params = array(
        'location' => $nearest['iata'],
        'sort' => 2,
        'limit' => 1,
        'radius_filter' => 1500,
        'category_filter' => 'restaurants,food'
    );
        
    $search_path = $GLOBALS['SEARCH_PATH'] . "?" . http_build_query($url_params);
    
    return request($GLOBALS['API_HOST'], $search_path);
}

/**
 * Query the Business API by business_id
 * 
 * @param    $business_id    The ID of the business to query
 * @return   The JSON response from the request 
 */
function get_business($business_id) {
    $business_path = $GLOBALS['BUSINESS_PATH'] . $business_id;
    
    return request($GLOBALS['API_HOST'], $business_path);
}

/**
 * Queries the API by the input values from the user 
 * 
 * @param    $term        The search term to query
 * @param    $location    The location of the business to query
 */
function query_api($nearest) {
    $response = json_decode(search($nearest), TRUE)['businesses'][0];

    $x = $response['location']['coordinate']['latitude'];
    $y = $response['location']['coordinate']['longitude'];
    
    $name = urlencode($response['name']);
    $gmap = "https://www.google.com/maps/search/$name/@$x,$y,15z";

    $yelp = $response['mobile_url'];
    
    $ret = array(
        'yelp' => $yelp,
        'gmap' => $gmap,
        'x' => $x,
        'y' => $y,
        'name' => $name
    );
    
    return $ret;
}

function get_nearest_airport($data) {
    $x = $data['user']['x'];
    $y = $data['user']['y'];

    $closeness = 1.0;
    $nearest = NULL;
    $nearest_dist = 0;
    $others = array();
    
    //TODO: Sanitize this! Toss geolookups into Db.
    $lower_x = (float) $x - $closeness;
    $lower_y = (float) $y - $closeness;
    $upper_x = (float) $x + $closeness;
    $upper_y = (float) $y + $closeness;
    
    $query = "select * from airports where x >= $lower_x and x <= $upper_x and y >= $lower_y and y <= $upper_y";
    $db = new Db();
    $sites = $db->performQuery($query)['data'];
    
    if (empty($sites)) {
       throw new Exception("You don't appear to be at an airport. Or near one. Or even within 70 miles of a far one. Awkward :/");
    }
    foreach($sites as $site) {
        $dist = calc_distance(array('x' => $x, 'y' => $y), array('x' => $site['x'], 'y' => $site['y']));
        $site['dist'] = $dist;
        if (empty($nearest) || $dist < $nearest_dist) {
            if (!empty($nearest)) {
                $others[] = $nearest;
            }            
            $nearest = $site;
            $nearest_dist = $dist;
            continue;
        } else {
            $others[] = $site;
        }
    }
    $data['nearest'] = $nearest;
    $data['others'] = $others;

    return $data;
}

function calc_distance($coords1, $coords2) {
    $x_diff = $coords1['x'] - $coords2['x'];
    $y_diff = $coords1['y'] - $coords2['y'];
    $delta_x = pow($x_diff, 2);
    $delta_y = pow($y_diff, 2);
    $summed = $delta_x + $delta_y;
    $root = sqrt($summed);
    return $root;
}

function get_params($args) {
    if (!isset($args['x'])
    || !isset($args['y'])) {
        throw new Exception('GET arguments x and y are required.');
    }
    if (!is_numeric($args['x'])
    || !is_numeric($args['y'])) {
        throw new Exception('GET arguments x and y must be coordinates.');
    }
    
    foreach($args as $k => $v) {
        if ($k !== 'x'
        && $k !== 'y') {
            unset($args[$k]);
            continue;
        }
        for($i = 0; $i < strlen($v); $i++) {
            if ($v[$i] !== '-'
            && $v[$i] !== '.'
            && !ctype_digit($v[$i])) {
                unset($args[$k]);
                continue;
            }
        }
    }    
    
    if (!isset($args['x'])
    || !isset($args['y'])) {
        throw new Exception('GET arguments x and y are required.');
    }
    
    $x = (float) $args['x'];
    $y = (float) $args['y'];
    
    return array($x, $y);
}

//http://www.geodatasource.com/developers/php
function coords_to_distance($lat1, $lon1, $lat2, $lon2, $unit = 'm') {
  $theta = $lon1 - $lon2;
  $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
  $dist = acos($dist);
  $dist = rad2deg($dist);
  $miles = $dist * 60 * 1.1515;
  $unit = strtoupper($unit);

  if ($unit == "K") {
    return ($miles * 1.609344);
  } else if ($unit == "N") {
      return ($miles * 0.8684);
    } else {
        return $miles;
      }
}


header('Content-Type: application/json');
try {
    list($x, $y) = get_params($_GET);
    $data['user']['x'] = $x;
    $data['user']['y'] = $y;    
    $airports = get_nearest_airport($data);
} catch (Exception $e) {
    echo json_encode(array("err" => $e->getMessage()));
    return;
}
    
// print_r(json_encode($airports, JSON_PRETTY_PRINT));
    

    
$data = query_api($airports['nearest']);
$data = array_merge($data, $airports);
// $data['dist_to_airport'] = calc_distance($airports['nearest'], $data['user']);
// $data['dist_to_food'] = calc_distance($airports['nearest'], $data);
$data['dist_to_airport'] = coords_to_distance($airports['nearest']['x'], $airports['nearest']['y'], $data['user']['x'], $data['user']['y']);
$data['dist_to_food'] = coords_to_distance($airports['nearest']['x'], $airports['nearest']['y'], $data['user']['x'], $data['user']['y']);

echo json_encode($data, JSON_PRETTY_PRINT);
