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

    private $oauth;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->oauth = new WrikeProvider([
            //localhost - Line Colony - Laravel
          'clientId' => env('WRIKE_CLIENT_ID'),
          'clientSecret' => env('WRIKE_CLIENT_SECRET'),
          'redirectUri' => env('WRIKE_CLIENT_REDIRECT_URI'),
        ]);
    }

    public function index(Request $request)
    {
        $response = $this->processLogin();
        if (!($response instanceof Wrike)) return $response;
        $client = $response;

        //setup vars
        $time = date('d-M H:i');
        $trees = [];
        /*
        $retainerFolders = [
            ['title' => 'RSPCA NSW', 'id' => 'IEAAFWIKI4C7D5JM', 'target' => 28],
            ['title' => 'AstraZeneca', 'id' => 'IEAAFWIKI4CRFLND'],
            ['title' => 'Canteen', 'id' => 'IEAAFWIKI4CEQZRF'],
            ['title' => 'Cerebral Palsy Alliance', 'id' => 'IEAAFWIKI4CALKDE'],
        ];
        */

      	//fetch reusable wrike data
      	$contacts = $client->get_contacts();

      	//fetch data and collect into trees
      	$trees[] = $this->getTimeByUser($contacts, $client);
	  	//$trees[] = $this->getDueAndOverdueByUser($contacts, $client);
      	//$trees[] = $this->getCompletedTasksByUser($contacts, $client);
      	//$trees[] = $this->getRetainerHoursByFolder($retainerFolders, $client);
      	$trees[] = $this->getProjectStatus($client);

      	return view('trees', ['trees' => $trees, 'time' => $time]);
    }

	public function team(Request $request)
    {
        $response = $this->processLogin();
        if (!($response instanceof Wrike)) return $response;
        $client = $response;

        // Setup client
        $client = new Wrike(Session::get('wrike_token'), $this->oauth);

        //setup vars
        $time = date('d-M H:i');
        $trees = [];

      	//fetch reusable wrike data
      	$contacts = $client->get_contacts();

      	//fetch data and collect into trees
		$trees[] = $this->getTeamStatus($contacts, $client);

      	return view('trees', ['trees' => $trees, 'time' => $time]);
    }

	private function getProjectStatus($client) {
		$tree = [
		  'titleKey' => 'Project Status',
		  'titleValue' => '',
		  'leaves' => [],
		  'css' => 'col-md-2',
		];

		//loop through folders for non-completed projects
		$folderTree = $client->get_folder_tree();
		  foreach ($folderTree as $folder) {
			  Log::debug($folder['id'].' :: '.$folder['title']);

			  if (array_key_exists('project', $folder) &&
			  $folder['project']['status'] != 'Completed' &&
			  $folder['project']['status'] != 'Cancelled' &&
			  $folder['project']['status'] != 'OnHold') {
				  Log::debug($folder['project']['status'].' :: '.$folder['id'].' :: '.$folder['title']);
				  $css = '';
				  if ($folder['project']['status'] == 'Red') {
					  $css = 'red';
                      $priority = 1;
				  } elseif ($folder['project']['status'] == 'Yellow') {
					  $css = 'amber';
                      $priority = 2;
				  } elseif ($folder['project']['status'] == 'Green') {
					  $css = 'green';
                      $priority = 3;
				  } elseif ($folder['project']['status'] == 'OnHold') {
					  $css = 'grey';
                      $priority = 4;
				  }

				  $tree['leaves'][] = [
					'key' => $folder['title'],
                    'link' => 'https://www.wrike.com/workspace.htm#path=folder&id='.$folder['id'],
					'value' => '',
					'css' => $css,
                    'priority' => $priority
				 ];
			  }
		  }

          //sort based on priority
          $sort = array();
          foreach($tree['leaves'] as $k=>$v) {
              $sort['priority'][$k] = $v['priority'];
              $sort['key'][$k] = $v['key'];
          }
          array_multisort($sort['priority'], SORT_ASC, $sort['key'], SORT_ASC,$tree['leaves']);

		  return $tree;
	}

    private function getTimeByUser($contacts, $client)
    {
        $tree = [
         'titleKey' => 'Hours per user this week',
         'titleValue' => '',
         'leaves' => [],
         'css' => 'col-md-2',
       ];

        //lets loop through the contacts into the tree
        foreach ($contacts as $contact) {
            if (!$contact['deleted']) {
                $tree['leaves'][$contact['id']] = [
                    'key' => $contact['firstName'].' '.$contact['lastName'],
                    'value' => 0,
                    'css' => 'col-md-2',
                ];
            }
        }

        //now lets get the time this week
        $target = 5 * 8;
        $dateFormat = 'Y-m-d';
        $start = date($dateFormat, strtotime('last monday', strtotime('tomorrow')));
        $now = date($dateFormat, strtotime('+1 day'));
        $logs = $client->get_account_timelogs($start, $now);

        //now lets loop through the time logs and add these to the tree
        foreach ($logs as $log) {
            if (array_key_exists($log['userId'], $tree['leaves'])) {
                $tree['leaves'][$log['userId']]['value'] += $log['hours'];
            } else {
                Log::error('Time log for non existent user id : '.$log['userId']);
            }
        }

        //prune the zeroes, and optionally add some styling one day
        foreach ($tree['leaves'] as $key => $leaf) {
            if ($leaf['value'] == 0) {
                unset($tree['leaves'][$key]);
            } else {
				$tree['leaves'][$key]['value'] = round($tree['leaves'][$key]['value']);
			}
        }

        return $tree;
    }

    private function getDueAndOverdueByUser($contacts, $client)
    {
        $tree = [
         'titleKey' => 'Due & overdue tasks per user',
         'titleValue' => '',
         'leaves' => [],
         'css' => 'col-md-2',
       ];

        //lets loop through the contacts into the tree
        foreach ($contacts as $contact) {
            if (!$contact['deleted']) {
                $tree['leaves'][$contact['id']] = [
                    'key' => substr($contact['firstName'], 0, 1).substr($contact['lastName'], 0, 1),
                    'value' => 0,
                    'css' => 'col-md-2',
                ];
            }
        }

        //now lets grab tasks
        $dateFormat = 'Y-m-d';
        $end = date($dateFormat, strtotime('today'));
        $fields = ['responsibleIds'];
        $tasks = $client->get_tasks('Active', $end, $fields);

        //now lets loop through the tasks and add these the user object
        foreach ($tasks as $task) {
            Log::debug($task);
            if (array_key_exists('responsibleIds', $task)) {
                foreach ($task['responsibleIds'] as $responsibleId) {
                    if (array_key_exists($responsibleId, $tree['leaves'])) {
                        $tree['leaves'][$responsibleId]['value']++;
                    } else {
                        Log::error('Task for non existent user id : '.$responsibleId);
                    }
                }
            }
        }

        //prune the zeros, and optionally add some styling one day
        foreach ($tree['leaves'] as $key => $leaf) {
            if ($leaf['value'] == 0) {
                unset($tree['leaves'][$key]);
            }
        }

        return $tree;
    }

	private function getTeamStatus($contacts, $client)
    {
        $tree = [
         'titleKey' => 'Team tasks by user',
         'titleValue' => '',
         'leaves' => [],
         'css' => 'col-md-2',
       ];

        //lets loop through the contacts into the tree
        dd($contacts);
        foreach ($contacts as $contact) {
            if (!$contact['deleted'] && $contact['type'] == 'Person') {
                $css = 'col-md-2 tall';
                $myTeam = 2;
                if (array_key_exists('myTeam', $contact) && $contact['myTeam']) {
                    $myTeam = 1;
                    $css .= ' green';
                }
                $tree['leaves'][$contact['id']] = [
                    'key' => $contact['firstName']. ' ' . $contact['lastName'],
                    'value' => [],
                    'css' => $css,
                    'myTeam' => $myTeam
                ];
            }
        }

        //now lets grab tasks
        $dateFormat = 'Y-m-d';
        $end = date($dateFormat, strtotime('today'));
        $fields = ['responsibleIds'];
        $tasks = $client->get_tasks('Active', $end, $fields);

        //now lets loop through the tasks and add these the user object
        foreach ($tasks as $task) {
            Log::debug($task);
            if (array_key_exists('responsibleIds', $task)) {
                foreach ($task['responsibleIds'] as $responsibleId) {
                    if (array_key_exists($responsibleId, $tree['leaves'])) {
						$css='';
						if (array_key_exists('dates', $task)) {
							if (array_key_exists('due', $task['dates'])) {
								$dueDate = strtotime($task['dates']['due']);
								if (time() > $dueDate) $css='red';
							}
						}

						//add to the tree
                        $tree['leaves'][$responsibleId]['value'][] = [
							'css'=>$css,
							'value'=>$task['title'],
						];
                    } else {
                        Log::error('Task for non existent user id : '.$responsibleId);
                    }
                }
            }
        }

        //prune the zeros, and optionally add some styling one day
        foreach ($tree['leaves'] as $key => $leaf) {
            if (empty($leaf['value'])) {
                unset($tree['leaves'][$key]);
            }
        }

        //sort according to my team and then others
        $sort = array();
        foreach($tree['leaves'] as $k=>$v) {
            $sort['myTeam'][$k] = $v['myTeam'];
            $sort['key'][$k] = $v['key'];
        }
        array_multisort($sort['myTeam'], SORT_ASC, $sort['key'], SORT_ASC,$tree['leaves']);

        return $tree;
    }

    private function getCompletedTasksByUser($contacts, $client)
    {
        $tree = [
         'titleKey' => 'Completed tasks per user this week',
         'titleValue' => '',
         'leaves' => [],
         'css' => 'col-md-2',
       ];

        //lets loop through the contacts into the tree
        foreach ($contacts as $contact) {
            if (!$contact['deleted']) {
                $tree['leaves'][$contact['id']] = [
                    'key' => substr($contact['firstName'], 0, 1).substr($contact['lastName'], 0, 1),
                    'value' => 0,
                    'css' => 'col-md-2',
                ];
            }
        }

        //now lets grab tasks
        $dateFormat = 'Y-m-d';
        $start = date($dateFormat, strtotime('last monday', strtotime('tomorrow')));
        $end = date($dateFormat, strtotime('tomorrow'));
        $fields = ['responsibleIds'];
        $tasks = $client->get_completed_tasks('Completed', $start, $end, $fields);

        //now lets loop through the tasks and add these the user object
        foreach ($tasks as $task) {
            Log::debug($task);
            if (array_key_exists('responsibleIds', $task)) {
                foreach ($task['responsibleIds'] as $responsibleId) {
                    if (array_key_exists($responsibleId, $tree['leaves'])) {
                        $tree['leaves'][$responsibleId]['value']++;
                    } else {
                        Log::error('Task for non existent user id : '.$responsibleId);
                    }
                }
            }
        }

        //prune the zeros, and optionally add some styling one day
        foreach ($tree['leaves'] as $key => $leaf) {
            if ($leaf['value'] == 0) {
                unset($tree['leaves'][$key]);
            }
        }

        return $tree;
    }

    private function getRetainerHoursByFolder($retainerFolders, $client)
    {
        $tree = [
          'titleKey' => 'Retainer hours this month',
          'titleValue' => '',
          'leaves' => [],
          'css' => 'col-md-2',
        ];

        $start = date('Y-m-01'); // hard-coded '01' for first day
        $end = date('Y-m-t');

        foreach ($retainerFolders as $key => $folder) {
            $logs = $client->get_folder_timelogs($folder['id'], $start, $end);
            $total = 0;
            foreach ($logs as $log) {
                $total += $log['hours'];
            }
            $css = '';
            if (isset($folder['target']) && $total < $folder['target'] * 0.5) {
                $css = 'red';
            } elseif (isset($folder['target']) && $total > $folder['target'] * 0.5 && $total < $folder['target']) {
                $css = 'amber';
            } elseif (isset($folder['target'])) {
                $css = 'green';
            }

            $tree['leaves'][] = [
                'key' => $folder['title'],
                'value' => round($total),
                'css' => $css,
            ];
        }

        return $tree;
    }

    private function processLogin() {
        //auth
        if (!Session::has('wrike_token') || !Session::get('wrike_token') instanceof AccessToken) {
            Log::debug('will authenticate');
            return $this->authenticate($oauth, $request);
        } elseif (Session::get('wrike_token')->getExpires() < time()) { //refresh the token
            Log::debug('refreshing token');
            $token = $this->oauth->getAccessToken('refresh_token', ['refresh_token' => Session::get('wrike_token')->getRefreshToken()]);
            Session::put('wrike_token', $token);
            Session::save();
            return redirect('/');
        } else {
            return new Wrike(Session::get('wrike_token'), $this->oauth);
        }
    }

    private function authenticate($oauth, $request)
    {
        if (!$request->has('code')) {
            return $oauth->authorize([], function ($url, $oauth) {
                Session::put('wrike_oauth2_state', $oauth->getState());
                Log::debug('Saving the state: '.$oauth->getState());
                Session::save();

                return redirect()->away($url);
            });
        } elseif (empty($request->input('state')) ||
        ($request->input('state') !== str_replace('+', ' ', Session::get('wrike_oauth2_state')))) {
            Log::debug('Could not reconcile : '.Session::get('wrike_oauth2_state'));
            $errorMsg = 'Invalid state : '.$request->input('state').' : '.Session::get('wrike_oauth2_state');
            $errorMsg .= '<br><a href="/">Retry</a>';
            Session::forget('wrike_oauth2_state');
            Session::save();
            return view('fail', ['errorMsg' => $errorMsg]);
        } else {
            try {
                Log::debug('Attempt to getAccessToken for wrike_oauth2_state : '.Session::get('wrike_oauth2_state'));
                $token = $oauth->getAccessToken('authorization_code', ['code' => $request->input('code')]);
                Session::put('wrike_oauth2_state', $oauth->getState());
                Session::put('wrike_token', $token);
                Session::save();
                Log::debug('tokens set!');
            } catch (IdentityProviderException $e) {
                // Failed to get the access token or user details.
          		Log::error('Failed to getAccessToken : '.$e->getMessage());
	            return view('fail', ['errorMsg' => $e->getMessage()]);
            }

            return redirect('/');
        }
    }

    public function logout()
    {
        Session::forget('wrike_oauth2_state');
        Session::forget('wrike_token');
        Session::save();

        return view('loggedout');
    }
}
