<?php
// Fill these out with the values you got from budu
$buduClientID = '5f1b1280-fa5b-4dab-b3c6-0e7f4d87be64';
$buduClientSecret = '14b19805-e640-4403-936d-6821000bdca8';

// This is the URL we'll send the user to first to get their authorization
$authorizeURL = 'http://localhost:3000/xapi/oauth/authorize';

// This is the endpoint our server will request an access token from
$tokenURL = 'http://localhost:3000/xapi/oauth/access_token';

// This is the budu base URL we can use to make authenticated API requests
$apiURLBase = 'http://localhost:3000/xapi/';

// The URL for this script, used as the redirect URL
$baseURL = 'http://localhost:3030/';

// Start a session so we have a place to store things between redirects
session_start();


// Start the login process by sending the user
// to budu's authorization page
if(isset($_GET['action']) && $_GET['action'] == 'login') {
  unset($_SESSION['access_token']);

  // Generate a random hash and store in the session
  $_SESSION['state'] = bin2hex(random_bytes(16));

  $params = array(
    'client_id' => $buduClientID,
    'state' => $_SESSION['state']
  );

  // Redirect the user to budu's authorization page
  header('Location: '.$authorizeURL.'?'.http_build_query($params));
  die();
}


if(isset($_GET['action']) && $_GET['action'] == 'logout') {
  unset($_SESSION['access_token']);
  header('Location: '.$baseURL);
  die();
}

// When budu redirects the user back here,
// there will be a "code" and "state" parameter in the query string
if(isset($_GET['code'])) {
  // Verify the state matches our stored state
  if(!isset($_GET['state'])
    || $_SESSION['state'] != $_GET['state']) {

    header('Location: ' . $baseURL . '?error=invalid_state');
    die();
  }

  // Exchange the auth code for an access token
  $token = apiRequest($tokenURL, array(
    'client_id' => $buduClientID,
    'client_secret' => $buduClientSecret,
    'code' => $_GET['code']
  ));
  $_SESSION['access_token'] = $token['access_token'];

  header('Location: ' . $baseURL);
  die();
}


if(isset($_GET['action']) && $_GET['action'] == 'vacancies') {
  // Find all vacancies created by the authenticated user
  $resp = apiRequest($apiURLBase.'hr/vacancies?'.http_build_query([
    'page' => 0,
    'per_page' => 5,
  ]));

  echo '<ul>';
  foreach($resp['items'] as $vacancy) {
    echo '<li><a href="' . $vacancy['vacancy_url'] . '">'
      . $vacancy['vacancy_title'] . '</a></li>';
  }
  echo '</ul>';
}

// If there is an access token in the session
// the user is already logged in
if(!isset($_GET['action'])) {
  if(!empty($_SESSION['access_token'])) {
    echo '<h3>Logged In</h3>';
    echo '<p><a href="?action=vacancies">View Vacancies</a></p>';
    echo '<p><a href="?action=logout">Log Out</a></p>';
  } else {
    echo '<h3>Not logged in</h3>';
    echo '<p><a href="?action=login">Log In</a></p>';
  }
  die();
}


// This helper function will make API requests to budu, setting
// the appropriate headers budu expects, and decoding the JSON response
function apiRequest($url, $post=FALSE, $headers=array()) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

  if($post)
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));

  $headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: https://example-app.com/'
  ];

  if(isset($_SESSION['access_token']))
    $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);
  return json_decode($response, true);
}
