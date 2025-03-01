<?php

namespace coviu\Api;

use PHPUnit_Framework_TestCase;

class OAuth2ClientTest extends PHPUnit_Framework_TestCase
{
  private static $endpoint = 'http://localhost:9400/v1';
  private static $api_key = '8de85310-7c43-4606-a450-43a348398a4b';
  private static $key_secret = 'abcdefg';

  public function testCanGetAccessToken()
  {
    $client = new OAuth2Client(self::$api_key, self::$key_secret, Request::request(self::$endpoint));
    $res = $client->getAccessToken()->run();
    $this->assertTrue(isset($res['body']['access_token']));
    $this->assertTrue(isset($res['body']['refresh_token']));
    $this->assertTrue(isset($res['body']['expires_in']));
  }

  public function testCanRefreshAccessToken()
  {
    $client = new OAuth2Client(self::$api_key, self::$key_secret, Request::request(self::$endpoint));
    $res = $client->getAccessToken()->run();
    $this->assertTrue(isset($res['body']['access_token']));
    $this->assertTrue(isset($res['body']['refresh_token']));
    $this->assertTrue(isset($res['body']['expires_in']));
    $res = $client->refreshAccessToken($res['body']['refresh_token'])->run();
    $this->assertTrue(isset($res['body']['access_token']));
    $this->assertTrue(isset($res['body']['refresh_token']));
    $this->assertTrue(isset($res['body']['expires_in']));
  }

  public function testAuthorizationCodeToken() {
    $req = Request::request(self::$endpoint);
    $client = new OAuth2Client(self::$api_key, self::$key_secret, $req);
    $authenticator = new Authenticator($client);
    $post = $req->auth($authenticator)->post()->json();
    // Create a user
    $user_data = Examples::user();
    $user = $post->path('/users')->body($user_data)->run()['body'];
    // Create a team.
    $team = $post->path('/users/'.$user['userId'].'/teams')->body(Examples::team())->run()['body'];
    // Create an api client for that team owned by that user.
    $user_client = $post->path('/system/clients')->body(Examples::client($user['userId'], $team['teamId']))->run()['body'];

    // Login as the user
    $opts = ['grant_type' => 'password', 'username' => $user_data['email'], 'password' => $user_data['password']];
    $grant = $client->passwordAccess($user_data['email'], $user_data['password'])->run()['body'];
    $authenticator->setupGrant($grant);

    // Create an application
    $app = $post->path('/teams/'.$team['teamId'].'/applications')->body(Examples::application())->run()['body'];

    // Create an API key
    $api_key = $post->path('/teams/'.$team['teamId'].'/clients')->body(['client_name' => 'foo'])->run()['body'];

    // Get the auth code for that application
    $authorization_code = $post->path('/user/auth-codes')->body(['clientId' => $app['appId'], 'teamId' => $team['teamId']])->run()['body']['code'];

    // Use new API key and auth code to get a grant
    $client = new OAuth2Client($api_key['clientId'], $api_key['secret'], $req);
    $res = $client->authorizationCode($authorization_code)->run();
    $this->assertTrue(isset($res['body']['access_token']));
    $this->assertTrue(isset($res['body']['refresh_token']));
    $this->assertTrue(isset($res['body']['expires_in']));
    $res = $client->refreshAccessToken($res['body']['refresh_token'])->run();
    $this->assertTrue(isset($res['body']['access_token']));
    $this->assertTrue(isset($res['body']['refresh_token']));
    $this->assertTrue(isset($res['body']['expires_in']));
  }
}
