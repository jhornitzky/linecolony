<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use IsevLtd\OAuth2\Client\Provider\Wrike as WrikeProvider;
use IsevLtd\Wrike\Client as Wrike;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

use Illuminate\Http\Request as Request;
use Illuminate\Support\Facades\Session as Session;
use Illuminate\Support\Facades\Log as Log;

class LandingController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

	public function index( Request $request ) {
		$oauth = new WrikeProvider([
			//Line Colony - Laravel
		  'clientId'     => 'DgGgRmQA',
		  'clientSecret' => 'k60bGX05h4WO2nR1AKop9GUdTGZCG7jA9y7ha2KT7ykAJxV8n3i7d7Oq2AIroTaF',
		  'redirectUri'  => 'http://localhost:8000'
		]);

		if ( !Session::has('wrike_token') || !Session::get('wrike_token') instanceof AccessToken ) {
			Log::debug('will authenticate');
		  return $this->authenticate( $oauth, $request );
		  //return redirect()->away('http://google.com');
		}
		elseif ( Session::get('wrike_token')->getExpires() < time() ) { //refresh the token
			Log::debug('refreshing token');
		  $token = $oauth->getAccessToken( 'refresh_token', [ 'refresh_token' => Session::get('wrike_token')->getRefreshToken() ]);
		  Session::put('wrike_token',$token);
		  Session::save();
		  return redirect('/');
		}
		else {
		  // Test an API call
		  Log::debug('making API call');
		  $output = '';
		  $client = new Wrike( Session::get('wrike_token'), $oauth );

		  //lots of settings
		  $time = date('d-M-y H:i');
		  $trees = [];
		  $teamIds = [];
		  $folderIds = [];

		  //lets get the folders first
		  /*
		  $folderTree = $client->get_folder_tree();
		  foreach($folderTree as $folder) {
			  $output .= $folder['title'] . '<br>';
		  }
		  */

		  /**
		  * TIME LOGS
		  */
		  $tree = [
		  	'title' => 'Time by day',
			'target' => 5.5*8,
			'leaves' => []
		  ];

		  $dateFormat = "Y-m-d";
		  $niceFormat = "d-M";
		  $start = date($dateFormat,strtotime('9 days ago'));
		  $now = date($dateFormat,strtotime('1 day'));
		  $logs = $client->get_account_timelogs($start, $now);

		  $days = [];
		  $key = date($dateFormat);
		  $days[$key] = 0;
		  for ($i=0;$i<9;$i++) {
			  //increment the counter
			  $key = date($dateFormat,strtotime($i.' days ago'));;
			  $days[$key] = 0;
		  }

		  foreach ($logs as $log) {
			  if (array_key_exists($log['trackedDate'], $days))
			  	$days[$log['trackedDate']] = $days[$log['trackedDate']] + $log['hours'];
			  else
			  	$days[$log['trackedDate']] = $log['hours'];
		  }

		  foreach ($days as $key => $value) {
			  $tree['leaves'][] = [
				  'key' => date($niceFormat,strtotime($key)),
				  'value' => round($value)
		  	  ];
		  }
		  $trees[] = $tree;

		  /**
		  * RETAINERS
		  */
		  $tree = [
		  	'title' => 'Retainers this month',
			'leaves' => []
		  ];
		  $retainerFolders = [
			  ['title'=>'RSPCA NSW', 'id'=>'24346730', 'target'=>28]
		  ];

		  foreach ($retainerFolders as $key => $folder) {

		  }

		  return view('welcome', ['trees' => $trees, 'time' => $time]);
		}
	}

	private function authenticate( $oauth, $request ) {
	  if ( !$request->has('code')) {
		return $oauth->authorize([], function( $url, $oauth ){
		  Session::put('wrike_oauth2_state', $oauth->getState());
		  Log::debug('Saving the state: '.$oauth->getState());
		  Session::save();
		  return redirect()->away($url);
		});
	  }
	  elseif ( empty( $request->input('state') ) ||
	  	( $request->input('state') !== str_replace('+', ' ',Session::get('wrike_oauth2_state')) ) ) {
		Log::debug('Could not reconcile : ' .  Session::get('wrike_oauth2_state'));
		$errorMsg = 'Invalid state : ' . $request->input('state') . ' : ' . Session::get('wrike_oauth2_state');
		$errorMsg .= '<br><a href="/">Retry</a>';
		Session::forget('wrike_oauth2_state');
		Session::save();
		return view('fail',['errorMsg' => $errorMsg]);
	  }
	  else {
		try {
			Log::debug('Attempt to getAccessToken for wrike_oauth2_state : ' .  Session::get('wrike_oauth2_state'));
		    $token = $oauth->getAccessToken( 'authorization_code', [ 'code' => $request->input('code') ]);
		    Session::put('wrike_oauth2_state', $oauth->getState());
		    Session::put('wrike_token' , $token);
			Session::save();
			Log::debug('tokens set!');
		}
		catch ( IdentityProviderException $e ) {
		  // Failed to get the access token or user details.
		  Log::error('Failed to getAccessToken : ' . $e->getMessage());
		}
		return redirect('/');
	  }
	}
}
