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
          'clientId' => config('wrike.id'),
          'clientSecret' => config('wrike.secret'),
          'redirectUri' => config('wrike.redirect'),
        ]);
    }

    /* ROUTE FUNCTIONS */

    public function hours(Request $request)
    {
        $response = $this->processLogin($request);
        if (!($response instanceof Wrike)) return $response;
        $client = $response;

        //setup vars
        $time = date('d-M H:i');
        $trees = [];

      	//fetch reusable wrike data
      	$contacts = $client->get_contacts();

      	//fetch data and collect into trees
      	$trees[] = $this->getTimeByUser($contacts, $client, 0, 'Hours per user this week');
      	$trees[] = $this->getTimeByUser($contacts, $client, 7, 'Hours per user last week');
      	$trees[] = $this->getTimeByUser($contacts, $client, 14, 'Hours per user two weeks ago');
      	$trees[] = $this->getTimeByUser($contacts, $client, 21, 'Hours per user three weeks ago');
      	$trees[] = $this->getTimeByUser($contacts, $client, 28, 'Hours per user four weeks ago');

      	return view('trees', ['trees' => $trees, 'time' => $time]);
    }

    public function projects(Request $request)
    {
        $response = $this->processLogin($request);
        if (!($response instanceof Wrike)) return $response;
        $client = $response;

        //setup vars
        $time = date('d-M H:i');
        $trees = [];

      	//fetch reusable wrike data
      	$contacts = $client->get_contacts();

      	//fetch data and collect into trees
      	$trees[] = $this->getProjectStatus($client);
      	$trees[] = $this->getRetainers($client);

      	return view('trees', ['trees' => $trees, 'time' => $time]);
    }

	public function overdue(Request $request)
    {
        $response = $this->processLogin($request);
        if (!($response instanceof Wrike)) return $response;
        $client = $response;

        //setup vars
        $time = date('d-M H:i');
        $trees = [];

      	//fetch reusable wrike data
      	$contacts = $client->get_contacts();

      	//fetch data and collect into trees
		$trees[] = $this->getTeamStatus($contacts, $client);

      	return view('trees', ['trees' => $trees, 'time' => $time]);
    }

    public function owners(Request $request) {
        $response = $this->processLogin($request);
        if (!($response instanceof Wrike)) return $response;
        $client = $response;

        //setup vars
        $time = date('d-M H:i');
        $trees = [];

        //fetch reusable wrike data
      	$contacts = $client->get_contacts();

        //fetch data and collect into trees
		$trees[] = $this->getProjectOwners($contacts, $client);

      	return view('trees', ['trees' => $trees, 'time' => $time]);
    }


    /* PRIVATE FUNCTIONS */

    private function findGroupsForContact($groups, $contact) {
        $groupsFound = [];
        foreach($groups as $group) {
            if (in_array($contact['id'], $group['memberIds'])) {
                $groupsFound[] = $group['firstName'];
            }
        }
        return implode(',',$groupsFound);
    }

    private function getProjectOwners($contacts, $client) {
        $tree = [
            'titleKey' => 'Projects by owner',
            'titleValue' => '',
            'leaves' => [],
            'css' => 'col-md-2',
        ];

        //get groups first
        $groups=[];
        foreach ($contacts as $contact) {
            if (!$contact['deleted'] && $contact['type'] == 'Group') {
                $groups[] = $contact;
            }
        }

        //lets loop through the contacts into the tree
        //dd($contacts);
        foreach ($contacts as $contact) {
            if (!$contact['deleted'] && $contact['type'] == 'Person') {
                $css = 'col-md-2 extra tall';
                $groupString = $this->findGroupsForContact($groups, $contact);
                $tree['leaves'][$contact['id']] = [
                    'key' => $contact['firstName'].' '.$contact['lastName'],
                    'subtitle' => $groupString,
                    'groups' => $groupString,
                    'value' => [],
                    'css' => $css
                ];
            }
        }

        //get the projects connected through
        //loop through folders for non-completed projects
		$folderTree = $client->get_folder_tree();
        //dd($folderTree);
        $projectIds = [];
        foreach ($folderTree as $folder) {
            if (array_key_exists('project', $folder) &&
            array_key_exists('ownerIds', $folder['project']) &&
            $folder['project']['status'] != 'Completed' &&
            $folder['project']['status'] != 'Cancelled' &&
            $folder['project']['status'] != 'OnHold') {
                //store projectIds
                $projectIds[] = $folder['id'];
            }
        }

        //now lets grab the projects with data
        $projectsData = $client->send_request_via_factory([
            'method' => 'get',
            'action' => '/folders/'.implode(',',$projectIds),
            'params' => []
        ]);

        $projects = [];
        foreach ($projectsData as $folder) {
            //set color
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

            //find phase
            $phase = null;
            foreach($folder['customFields'] as $customField) {
                if ($customField['id'] == 'IEAAFWIKJUAARM2Z') { //FIXME phase set through config
                    $phase = $customField['value'];
                }
            } 

            //store project data
            $projects[] = [
                'id'=>$folder['id'],
                'css'=>$css,
                'value'=>$folder['title'].' : '.$phase,
                'phase'=>$phase, 
                'ownerIds' =>$folder['project']['ownerIds'],
                'link' => $folder['permalink']
            ];
        }

        //sort based on the phases
        $sort = array();
        foreach($projects as $k=>$v) {
            $sort['phase'][$k] = $v['phase'];
        }
        array_multisort($sort['phase'], SORT_ASC, $projects);


        //now lets loop through the tasks and add these to the user object
        foreach ($projects as $project) {
            if (array_key_exists('ownerIds', $project)) {
                foreach ($project['ownerIds'] as $ownerId) {
                    if (array_key_exists($ownerId, $tree['leaves'])) {
						$css = $project['css'];

						//add to the tree
                        $tree['leaves'][$ownerId]['value'][] = [
							'css'=>$css,
							'value'=>$project['value'],
							'link'=>$project['link']
						];
                    } else {
                        Log::error('Project for non existent user id : '.$ownerId);
                    }
                }
            }
        }


        //prune the zeros, add the number of projects to the header and optionally add some styling one day
        foreach ($tree['leaves'] as $key => $leaf) {
            if (empty($leaf['value'])) {
                unset($tree['leaves'][$key]);
            } else {
                $tree['leaves'][$key]['numberOfProjects'] .= count($tree['leaves'][$key]['value']);
                $tree['leaves'][$key]['key'] .= ' (' .count($tree['leaves'][$key]['value']) . ')';
            }
        }

        //sort according to groups and then others
        $sort = array();
        foreach($tree['leaves'] as $k=>$v) {
            $sort['groups'][$k] = $v['groups'];
        }
        array_multisort($sort['groups'], SORT_ASC, $tree['leaves']);

        return $tree;
    }

	private function getProjectStatus($client) {
		$tree = [
		  //'titleKey' => 'Project Status',
		  'titleValue' => '',
		  'leaves' => [],
		  'css' => 'col-md-2',
        ];
    
		$folderTree = $client->get_folder_tree();
        //dd($folderTree);
        $projectIds = [];
        foreach ($folderTree as $folder) {
            if (array_key_exists('project', $folder)) {
                //store projectIds
                $projectIds[] = $folder['id'];
            }
        }

        //now lets grab the projects with data
        $projectsData = $client->send_request_via_factory([
            'method' => 'get',
            'action' => '/folders/'.implode(',',$projectIds),
            'params' => []
        ]);

        $projects = [];
        foreach ($projectsData as $folder) {
            if (array_key_exists('project', $folder) &&
            $folder['project']['status'] != 'Completed' &&
            $folder['project']['status'] != 'Cancelled' &&
            $folder['project']['status'] != 'OnHold') {
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

                //find phase
                $phase = null;
                foreach($folder['customFields'] as $customField) {
                    if ($customField['id'] == 'IEAAFWIKJUAARM2Z') { //FIXME phase set manually
                        $phase = $customField['value'];
                    }
                } 
                if ($phase != '08 Retainer') { //dont include retainers
                    $tree['leaves'][] = [
                        'key' => $folder['title'],
                        'link' => 'https://www.wrike.com/workspace.htm#path=folder&id='.$folder['id'],
                        'value' => '',
                        'css' => $css,
                        'priority' => $priority
                    ];
                }
            }
        }

        //sort based on priority
        $sort = array();
        foreach($tree['leaves'] as $k=>$v) {
            $sort['priority'][$k] = $v['priority'];
            $sort['key'][$k] = $v['key'];
        }
        array_multisort($sort['priority'], SORT_ASC, $sort['key'], SORT_ASC,$tree['leaves']);

        //set number of projects
		$tree['titleKey'] = 'Projects ('.count($tree['leaves']).')';

        return $tree;
    }

    
    private function getRetainers($client) {
		$tree = [
		  //'titleKey' => 'Project Status',
		  'titleValue' => '',
		  'leaves' => [],
		  'css' => 'col-md-2',
        ];

		$folderTree = $client->get_folder_tree();
        //dd($folderTree);
        $projectIds = [];
        foreach ($folderTree as $folder) {
            if (array_key_exists('project', $folder)) {
                //store projectIds
                $projectIds[] = $folder['id'];
            }
        }

        //now lets grab the projects with data
        $projectsData = $client->send_request_via_factory([
            'method' => 'get',
            'action' => '/folders/'.implode(',',$projectIds),
            'params' => []
        ]);

        $projects = [];
        foreach ($projectsData as $folder) {
            if (array_key_exists('project', $folder) &&
            $folder['project']['status'] != 'Completed' &&
            $folder['project']['status'] != 'Cancelled' &&
            $folder['project']['status'] != 'OnHold') {
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

                //find phase
                $phase = null;
                foreach($folder['customFields'] as $customField) {
                    if ($customField['id'] == 'IEAAFWIKJUAARM2Z') { //FIXME phase set manually
                        $phase = $customField['value'];
                    }
                } 
                if ($phase == '08 Retainer') { //include retainers
                    $tree['leaves'][] = [
                        'key' => $folder['title'],
                        'link' => 'https://www.wrike.com/workspace.htm#path=folder&id='.$folder['id'],
                        'value' => '',
                        'css' => $css,
                        'priority' => $priority
                    ];
                }
            }
        }

        //sort based on priority
        $sort = array();
        foreach($tree['leaves'] as $k=>$v) {
            $sort['priority'][$k] = $v['priority'];
            $sort['key'][$k] = $v['key'];
        }
        array_multisort($sort['priority'], SORT_ASC, $sort['key'], SORT_ASC,$tree['leaves']);

        //set number of projects
		$tree['titleKey'] = 'Retainers ('.count($tree['leaves']).')';

        return $tree;
	}

    private function getTimeByUser($contacts, $client, $negativeDays = 0, $titleKey = 'Hours per user this week')
    {
        $tree = [
         'titleKey' => $titleKey,
         'titleValue' => '',
         'leaves' => [],
         'css' => 'col-md-2'
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
        $target = 5 * 7.5;
        $dateFormat = 'Y-m-d';
        $dayString = '+1 days';
        if ($negativeDays > 0) $dayString = -1*($negativeDays-1).' days';
        $start = date($dateFormat, strtotime('last monday', strtotime($dayString)));
        $now = date($dateFormat, strtotime('next monday',strtotime($start)));
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
                if ($leaf['value'] < $target) {
                    $tree['leaves'][$key]['css'] = 'amber';
                } 
			}
        }

        //order by users with the highest values
        $sort = array();
        foreach($tree['leaves'] as $k=>$v) {
            $sort['value'][$k] = $v['value'];
        }
        array_multisort($sort['value'], SORT_DESC,$tree['leaves']);

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
         'titleKey' => 'Overdue tasks by user',
         'titleValue' => '',
         'leaves' => [],
         'css' => 'col-md-2',
       ];

        //lets loop through the contacts into the tree
        //dd($contacts);
        foreach ($contacts as $contact) {
            if (!$contact['deleted'] && $contact['type'] == 'Person') {
                $css = 'col-md-2 tall';
                $tree['leaves'][$contact['id']] = [
                    'key' => $contact['firstName']. ' ' . $contact['lastName'],
                    'value' => [],
                    'css' => $css,
                    'count' => 0
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
                        $tree['leaves'][$responsibleId]['count']++;
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
            } else {
                $tree['leaves'][$key]['key'] = $tree['leaves'][$key]['key'].' ('.$tree['leaves'][$key]['count'].')';
            }
        }

        //order by users with the highest values
        $sort = array();
        foreach($tree['leaves'] as $k=>$v) {
            $sort['count'][$k] = $v['count'];
        }
        array_multisort($sort['count'], SORT_DESC,$tree['leaves']);

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

    private function processLogin($request) {
        //auth
        if (!Session::has('wrike_token') || !Session::get('wrike_token') instanceof AccessToken) {
            return $this->authenticate($this->oauth, $request);
        } elseif (Session::get('wrike_token')->getExpires() < time()) { //refresh the token
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
                Session::save();

                return redirect()->away($url);
            });
        } elseif (empty($request->input('state')) ||
        ($request->input('state') !== str_replace('+', ' ', Session::get('wrike_oauth2_state')))) {
            $errorMsg = 'Invalid state : '.$request->input('state').' : '.Session::get('wrike_oauth2_state');
            $errorMsg .= '<br><a href="/">Retry</a>';
            Session::forget('wrike_oauth2_state');
            Session::save();
            return view('fail', ['errorMsg' => $errorMsg]);
        } else {
            try {
                $token = $oauth->getAccessToken('authorization_code', ['code' => $request->input('code')]);
                Session::put('wrike_oauth2_state', $oauth->getState());
                Session::put('wrike_token', $token);
                Session::save();
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
