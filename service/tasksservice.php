<?php
namespace OCA\Tasks\Service;

use \OCA\Tasks\Controller\Helper;

class TasksService {

	private $userId;

	public function __construct($userId){
		$this->userId = $userId;
	}

	/**
	 * get a list of Tasks filtered by listID and type
	 * 
	 * @param  string $listID
	 * @param  string $type
	 * @return array
	 * @throws \Exception
	 */
	public function getAll($listID = 'all', $type = 'all'){
		
		$user_timezone = \OC_Calendar_App::getTimezone();
		if ($listID == 'all'){
			$calendars = \OC_Calendar_Calendar::allCalendars($this->userId, true);
		} else {
			$calendar = \OC_Calendar_App::getCalendar($listID, true, false);
			$calendars = array($calendar);
		}

		$tasks = array();
		$lists = array();
		foreach( $calendars as $calendar ) {
			$calendar_entries = \OC_Calendar_Object::all($calendar['id']);
			$tasks_selected = array();
			foreach( $calendar_entries as $task ) {
				if($task['objecttype']!='VTODO') {
					continue;
				}
				if(is_null($task['summary'])) {
					continue;
				}
				if(!($vtodo = Helper::parseVTODO($task))){
					continue;
				}
				$task_data = Helper::arrayForJSON($task['id'], $vtodo, $user_timezone, $calendar['id']);
				switch($type){
					case 'all':
						$tasks[] = $task_data;
						break;
					case 'init':
						if (!$task_data['completed']){
							$tasks[] = $task_data;
						} else {
							$tasks_selected[] = $task_data;
						}
						break;
					case 'completed':
						if ($task_data['completed']){
							$tasks[] = $task_data;
						}
						break;
					case 'uncompleted':
						if (!$task_data['completed']){
							$tasks[] = $task_data;
						}
						break;
				}
			}
			$nrCompleted = 0;
			$notLoaded = 0;
			usort($tasks_selected, array($this, 'sort_completed'));
			foreach( $tasks_selected as $task_selected){
				$nrCompleted++;
				if ($nrCompleted > 5){
					$notLoaded++;
					continue;
				}
				$tasks[] = $task_selected;
			}
			$lists[] = array(
				'id' 		=> $calendar['id'],
				'notLoaded' => $notLoaded
				);
		}
		return array(
			'tasks' => $tasks,
			'lists' => $lists
		);
	}

	/**
	 * get task by id
	 * 
	 * @param  string $taskID
	 * @return array
	 * @throws \Exception
	 */
	public function get($taskID){
		$object = \OC_Calendar_App::getEventObject($taskID);
		$user_timezone = \OC_Calendar_App::getTimezone();
		$task = array();
		if($object['objecttype']=='VTODO' && !is_null($object['summary'])) {
			if($vtodo = Helper::parseVTODO($object)){
				$task_data = Helper::arrayForJSON($object['id'], $vtodo, $user_timezone, $object['calendarid']);
				$task[] = $task_data;
			}
		}
		return array(
			'tasks' => $task
		);
	}

	/**
	 * create new task
	 * 
	 * @param  string $taskName
	 * @param  int    $calendarId
	 * @param  bool   $starred
	 * @param  mixed  $due
	 * @param  mixed  $start
	 * @param  int    $tmpID
	 * @return array
	 */
	public function add($taskName, $related, $calendarId, $starred, $due, $start, $tmpID){
		$user_timezone = \OC_Calendar_App::getTimezone();
		$request = array(
				'summary'			=> $taskName,
				'related'			=> $related,
				'categories'		=> null,
				'priority'			=> $starred,
				'location' 			=> null,
				'due'				=> $due,
				'start'				=> $start,
				'description'		=> null
			);
		$vcalendar = Helper::createVCalendarFromRequest($request);
		$taskID = \OC_Calendar_Object::add($calendarId, $vcalendar->serialize());

		$task = Helper::arrayForJSON($taskID, $vcalendar->VTODO, $user_timezone, $calendarId);

		$task['tmpID'] = $tmpID;
		return $task;
	}

	/**
	 * delete task by id
	 * 
	 * @param  int   $taskID
	 * @return bool
	 */
	public function delete($taskID) {
		return \OC_Calendar_Object::delete($taskID);
	}

	/**
	 * set name of task by id
	 * @param  int    $taskID
	 * @param  string $name
	 * @return bool
	 * @throws \Exception
	 */
	public function setName($taskID, $name) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$vtodo->SUMMARY = $name;
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	/**
	 * set calendar id of task by id
	 * 
	 * @param  int    $taskID
	 * @param  int    $calendarID
	 * @return bool
	 * @throws \Exception
	 */
	public function setCalendarId($taskID, $calendarID) {
		$data = \OC_Calendar_App::getEventObject($taskID);
		if ($data['calendarid'] != $calendarID) {
			return \OC_Calendar_Object::moveToCalendar($taskID, $calendarID);
		} else {
			return true;
		}
	}

	/**
	 * set completeness of task in percent by id
	 * 
	 * @param  int    $taskID
	 * @param  int    $percent_complete
	 * @return bool
	 * @throws \Exception
	 */
	public function setPercentComplete($taskID, $percent_complete) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		if (!empty($percent_complete)) {
			$vtodo->{'PERCENT-COMPLETE'} = $percent_complete;
		}else{
			unset($vtodo->{'PERCENT-COMPLETE'});
		}
		if ($percent_complete == 100) {
			$vtodo->STATUS = 'COMPLETED';
			$vtodo->COMPLETED = new \DateTime('now', new \DateTimeZone('UTC'));
		} elseif ($percent_complete != 0) {
			$vtodo->STATUS = 'IN-PROCESS';
			unset($vtodo->COMPLETED);
		} else{
			$vtodo->STATUS = 'NEEDS-ACTION';
			unset($vtodo->COMPLETED);
		}
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	/**
	 * set priority of task by id
	 * 
	 * @param  int    $taskID
	 * @param  int    $priority
	 * @return bool
	 * @throws \Exception
	 */
	public function priority($taskID, $priority){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		if($priority){
			$vtodo->PRIORITY = (10 - $priority) % 10;
		}else{
			unset($vtodo->{'PRIORITY'});
		}
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	/**
	 * set view state of subtasks
	 * 
	 * @param  int    $taskID
	 * @param  int    $show
	 * @return bool
	 */
	public function showSubtasks($taskID, $show){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		if($show){
			$vtodo->{'X-OC-SHOWSUBTASKS'} = 1;
		}else{
			$vtodo->{'X-OC-SHOWSUBTASKS'} = 0;
		}
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	/**
	 * set parent of subtask
	 * 
	 * @param  int    $taskID
	 * @param  int    $related
	 * @return bool
	 */
	public function parent($taskID, $related){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		if($related){
			$vtodo->{'RELATED-TO'} = $related;
		} else {
			unset($vtodo->{'RELATED-TO'});
		}
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	/**
	 * set due date of task by id
	 * 
	 * @param  int    $taskID
	 * @param  mixed  $dueDate
	 * @return bool
	 * @throws \Exception
	 */
	public function setDueDate($taskID, $dueDate) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		if ($dueDate != false) {
			$timezone = \OC_Calendar_App::getTimezone();
			$timezone = new \DateTimeZone($timezone);

			$dueDate = new \DateTime('@'.$dueDate);
			$dueDate->setTimezone($timezone);
			$vtodo->DUE = $dueDate;
		} else {
			unset($vtodo->DUE);
		}
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	public function setStartDate($taskID, $startDate) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		if ($startDate != false) {
			$timezone = \OC_Calendar_App::getTimezone();
			$timezone = new \DateTimeZone($timezone);

			$startDate = new \DateTime('@'.$startDate);
			$startDate->setTimezone($timezone);
			$vtodo->DTSTART = $startDate;
		} else {
			unset($vtodo->DTSTART);
		}
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	/**
	 * set reminder date of task by id
	 * @param  int    $taskID
	 * @param  string $type
	 * @param  mixed  $action
	 * @param  mixed  $date
	 * @param  bool   $invert
	 * @param  string $related
	 * @param  mixed  $week
	 * @param  mixed  $day
	 * @param  mixed  $hour
	 * @param  mixed  $minute
	 * @param  mixed  $second
	 * @return bool
	 * @throws \Exception
	 */
	public function setReminderDate($taskID, $type, $action, $date, $invert, $related = null, $week, $day, $hour, $minute, $second){
		$types = array('DATE-TIME','DURATION');

		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$valarm = $vtodo->VALARM;

		if ($type == false){
			unset($vtodo->VALARM);
			$vtodo->{'LAST-MODIFIED'}->setValue(new \DateTime('now', new \DateTimeZone('UTC')));
			$vtodo->DTSTAMP = new \DateTime('now', new \DateTimeZone('UTC'));
			return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		}
		elseif (in_array($type,$types)) {
			if($valarm == null) {
				$valarm = $vcalendar->createComponent('VALARM');
				$valarm->ACTION = $action;
				$valarm->DESCRIPTION = 'Default Event Notification';
				$vtodo->add($valarm);
			} else {
				unset($valarm->TRIGGER);
			}
			$tv = '';
			if ($type == 'DATE-TIME') {
				$date = new \DateTime('@'.$date);
				$tv = $date->format('Ymd\THis\Z');
			} elseif ($type == 'DURATION') {
				// Create duration string
				if($week || $day || $hour || $minute || $second) {
					if ($invert){
						$tv.='-';
					}
					$tv.='P';
					if ($week){
						$tv.=$week.'W';
					}
					if ($day){
						$tv.=$day.'D';
					}
					$tv.='T';
					if ($hour){
						$tv.=$hour.'H';
					}
					if ($minute){
						$tv.=$minute.'M';
					}
					if ($second){
						$tv.=$second.'S';
					}
				}else{
					$tv = 'PT0S';
				}
			}
			if($related == 'END'){
				$valarm->add('TRIGGER', $tv, array('VALUE' => $type, 'RELATED' => $related));
			} else {
				$valarm->add('TRIGGER', $tv, array('VALUE' => $type));
			}
			$vtodo->{'LAST-MODIFIED'}->setValue(new \DateTime('now', new \DateTimeZone('UTC')));
			$vtodo->DTSTAMP = new \DateTime('now', new \DateTimeZone('UTC'));
			return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		}
	}

	/**
	 * add category to task by id
	 * @param  int    $taskID
	 * @param  string $category
	 * @return bool
	 * @throws \Exception
	 */
	public function addCategory($taskID, $category){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		// fetch categories from TODO
		$categories = $vtodo->CATEGORIES;
		$taskcategories = array();
		if ($categories){
			$taskcategories = $categories->getParts();
		}
		// add category
		if (!in_array($category, $taskcategories)){
			$taskcategories[] = $category;
			$vtodo->CATEGORIES = $taskcategories;
			return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		} else {
			return true;
		}
	}

	/**
	 * remove category from task by id
	 * @param  int    $taskID
	 * @param  string $category
	 * @return bool
	 * @throws \Exception
	 */
	public function removeCategory($taskID, $category){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		// fetch categories from TODO
		$categories = $vtodo->CATEGORIES;
		if ($categories){
			$taskcategories = $categories->getParts();
		}
		// remove category
		$key = array_search($category, $taskcategories);
		if ($key !== null && $key !== false){
			unset($taskcategories[$key]);
			if(count($taskcategories)){
				$vtodo->CATEGORIES = $taskcategories;
			} else{
				unset($vtodo->{'CATEGORIES'});
			}
			return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		}
	}

	/**
	 * set location of task by id
	 * @param  int    $taskID
	 * @param  string $location
	 * @return bool
	 * @throws \Exception
	 */
	public function setLocation($taskID, $location){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$vtodo->LOCATION = $location;
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	/**
	 * set description of task by id
	 * 
	 * @param  int    $taskID
	 * @param  string $description
	 * @return bool
	 * @throws \Exception
	 */
	public function setDescription($taskID, $description){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$vtodo->DESCRIPTION = $description;
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	/**
	 * add comment to task by id
	 * @param  int    $taskID
	 * @param  string $comment
	 * @param  int    $tmpID
	 * @return array
	 * @throws \Exception
	 */
	public function addComment($taskID, $comment, $tmpID){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;

		if($vtodo->COMMENT == "") {
			// if this is the first comment set the id to 0
			$commentId = 0;
		} else {
			// Determine new commentId by looping through all comments
			$commentIds = array();
			foreach($vtodo->COMMENT as $com) {
				$commentIds[] = (int)$com['X-OC-ID']->getValue();
			}
			$commentId = 1+max($commentIds);
		}

		$now = 	new \DateTime();
		$vtodo->add('COMMENT',$comment,
			array(
				'X-OC-ID' => $commentId,
				'X-OC-USERID' => $this->userId,
				'X-OC-DATE-TIME' => $now->format('Ymd\THis\Z')
				)
			);
		\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		$user_timezone = \OC_Calendar_App::getTimezone();
		$now->setTimezone(new \DateTimeZone($user_timezone));
		$comment = array(
			'taskID' => $taskID,
			'id' => $commentId,
			'tmpID' => $tmpID,
			'name' => \OC::$server->getUserManager()->get($this->userId)->getDisplayName(),
			'userID' => $this->userId,
			'comment' => $comment,
			'time' => $now->format('Ymd\THis')
			);
		return $comment;
	}

	/**
	 * delete comment of task by id
	 * @param  int   $taskID
	 * @param  int   $commentID
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteComment($taskID, $commentID){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$commentIndex = $this->getCommentById($vtodo,$commentID);
		$comment = $vtodo->children[$commentIndex];
		if($comment['X-OC-USERID']->getValue() == $this->userId){
			unset($vtodo->children[$commentIndex]);
			return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		} else {
			throw new \Exception('Not allowed.');
		}
	}


	private static function sort_completed($a, $b) {
		$t1 = \DateTime::createFromFormat('Ymd\THis', $a['completed_date']);
		$t2 = \DateTime::createFromFormat('Ymd\THis', $b['completed_date']);
		if ($t1 == $t2) {
			return 0;
		}
		return $t1 < $t2 ? 1 : -1;
	}

	private function getCommentById($vtodo,$commentId) {
		$idx = 0;
		foreach ($vtodo->children as $i => &$property) {
			if ( $property->name == 'COMMENT' && $property['X-OC-ID']->getValue() == $commentId ) {
				return $idx;
			}
			$idx += 1;
		}
		throw new \Exception('Commment not found.');
	}

}
