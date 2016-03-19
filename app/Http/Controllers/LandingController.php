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
			//localhost - Line Colony - Laravel
		  'clientId'     => env('WRIKE_CLIENT_ID'),
		  'clientSecret' => env('WRIKE_CLIENT_SECRET'),
		  'redirectUri'  => env('WRIKE_CLIENT_REDIRECT_URI')
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
		  $time = date('d-M H:i');
		  $trees = [];

		  /**
		  * TIME LOGS

		  $tree = [
		  	'titleKey' => 'Team hours by day',
			'titleValue' => '',
			'leaves' => [],
			'css' => 'col-xs-1'
		  ];

		  $target = 5.5*8;
		  $dateFormat = "Y-m-d";
		  $niceFormat = "d-M";
		  $start = date($dateFormat,strtotime('-9 days'));
		  $now = date($dateFormat,strtotime('+1 day'));
		  $logs = $client->get_account_timelogs($start, $now);

		  $days = [];
		  $key = date($dateFormat);
		  $days[$key] = 0;
		  for ($i=0;$i<9;$i++) {
			  //increment the counter
			  $key = date($dateFormat,strtotime($i.' days ago'));
			  $days[$key] = 0;
		  }

		  foreach ($logs as $log) {
			  if (array_key_exists($log['trackedDate'], $days))
			  	$days[$log['trackedDate']] += $log['hours'];
			  else
			  	$days[$log['trackedDate']] = $log['hours'];
		  }

		  foreach ($days as $key => $value) {
			  $css = '';
			  if ($value < $target*0.5)
			  	$css = 'red';
			  else if ($value > $target*0.5 && $value < $target)
			    $css = 'amber';
			  else
  			  	$css = 'green';
			  $tree['leaves'][] = [
				  'key' => date($niceFormat,strtotime($key)),
				  'value' => round($value),
				  'css' => $css
		  	  ];
		  }

		  $count = 0;
		  $sum = 0;
		  foreach ($days as $key => $value) {
			  $count++;
			  $sum += $value;
		  }
		  $tree['outcomeKey'] = 'Average';
		  $tree['outcomeValue'] = round($sum/$count);

		  $trees[] = $tree;
		  */


		  /**
		  * TEAM MEMBER TIME PER WEEK
		  */
		  $tree = [
		   'titleKey' => 'Hours per user this week',
		   'titleValue' => '',
		   'leaves' => [],
		   'css' => 'col-xs-2'
		 ];

		  //get the team users first
		  $contacts = $client->get_contacts();
		  $allowedContacts = [];

		  //lets loop through the contacts into the tree
		  foreach($contacts as $contact) {
			  if (!$contact['deleted']) {
				  $tree['leaves'][$contact['id']] = [
					  'key'=> $contact['firstName'] . ' ' . $contact['lastName'],
					  'value'=> 0,
		  			  'css' => 'col-xs-2'
				  ];
			  }
	  	  }

		  //now lets get the time this week
		  $target = 5*8;
		  $dateFormat = "Y-m-d";
		  $niceFormat = "d-M";
		  $start = date($dateFormat,strtotime('last monday', strtotime('tomorrow')));
		  $now = date($dateFormat,strtotime('+1 day'));
		  $logs = $client->get_account_timelogs($start, $now);

		  //now lets loop through the time logs and add these to the tree
		  foreach ($logs as $log) {
			  if (array_key_exists($log['userId'], $tree['leaves']))
			  	$tree['leaves'][$log['userId']]['value'] = round($tree['leaves'][$log['userId']]['value'] + $log['hours']);
			  else
			  	Log::error('Time log for non existent user id : ' . $log['userId']);
		  }

		  //prune the zeros, and optionally add some styling one day
		  foreach ($tree['leaves'] as $key => $leaf) {
			  if ($leaf['value'] == 0)
				  unset($tree['leaves'][$key]);
		  }


		  $tree['outcomeKey'] = '';
		  $tree['outcomeValue'] = '';
		  $trees[] = $tree;


		  /**
		  * RETAINERS
		  */
		  $tree = [
		  	'titleKey' => 'Retainer hours this month',
			'titleValue' => '',
			'leaves' => [],
			'css' => 'col-xs-2'
		  ];

		  $retainerFolders = [
			  ['title'=>'RSPCA NSW', 'id'=>'IEAAFWIKI4AXHADK', 'target'=>28],
			  ['title'=>'AstraZeneca', 'id'=>'IEAAFWIKI4BLFAGV'],
			  ['title'=>'Canteen', 'id'=>'IEAAFWIKI4CEQZRF'],
			  ['title'=>'Cerebral Palsy Alliance', 'id'=>'IEAAFWIKI4CALKDE']
		  ];

		  $start = date('Y-m-01'); // hard-coded '01' for first day
		  $end = date('Y-m-t');
		  foreach ($retainerFolders as $key => $folder) {
			  $logs = $client->get_folder_timelogs($folder['id'],$start,$end);
			  $total = 0;
			  foreach ($logs as $log) {
				  $total += $log['hours'];
			  }
			  $css = '';
			  if (isset($folder['target']) && $total < $folder['target']*0.5)
			   $css = 'red';
			  else if (isset($folder['target']) && $total > $folder['target']*0.5 && $total < $folder['target'])
			   $css = 'amber';
			  else if (isset($folder['target']))
			   $css = 'green';

			  $tree['leaves'][] = [
				  'key'=> $folder['title'],
				  'value'=> round($total),
				  'css' => $css
			  ];
		  }

		  $tree['outcomeKey'] = '';
		  $tree['outcomeValue'] = '';
		  $trees[] = $tree;


		  /**
		  * PROJECT STATUS
		  */
		  $tree = [
		  	'titleKey' => 'Project Status',
			'titleValue' => '',
			'leaves' => [],
			'css' => 'col-xs-1'
		  ];

		  //loop through folders for non-completed projects
		  $folderTree = $client->get_folder_tree();
		  foreach($folderTree as $folder) {
			  //Log::debug($folder['id'] . ' :: ' . $folder['title']);

			  if (array_key_exists('project', $folder) &&
			  	$folder['project']['status'] != 'Completed' &&
			  	$folder['project']['status'] != 'Cancelled' &&
				$folder['project']['status'] != 'OnHold') {
				  Log::debug($folder['project']['status'] . ' :: ' . $folder['id'] . ' :: ' . $folder['title']);
				  $css = '';
	  			  if ($folder['project']['status'] == 'Red')
	  			   $css = 'red';
	  			  else if ($folder['project']['status'] == 'Yellow')
	  			   $css = 'amber';
	  			  else if ($folder['project']['status'] == 'Green')
	  			   $css = 'green';
				  else if ($folder['project']['status'] == 'OnHold')
 	  			   $css = 'grey';

	  			  $tree['leaves'][] = [
	  				  'key'=> $folder['title'],
	  				  'value'=> '',
	  				  'css' => $css
	  			  ];
			  }
		  }

		  $tree['outcomeKey'] = '';
		  $tree['outcomeValue'] = '';
		  $trees[] = $tree;

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
