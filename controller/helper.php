<?php
/**
 * ownCloud - Utility class for VObject properties
 *
 * @author Thomas Tanghus
 * @copyright 2013-2014 Thomas Tanghus (thomas@tanghus.net)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Tasks\Controller;

use Sabre\VObject;
// use OCA\Tasks\App;

Class Helper {

	public static function parseVTODO($task) {
		$object = \Sabre\VObject\Reader::read($task['calendardata']);
		if(!$object) {
			return false;
		}
		$sharedAccessClassPermissions = \OC_Calendar_Object::getAccessClassPermissions($object);
		if(\OC_Calendar_Object::getowner($task['id']) !== \OC::$server->getUserSession()->getUser()->getUID()){
			if (!($sharedAccessClassPermissions & \OCP\Constants::PERMISSION_READ)) {
				return false;
			}
		}
		$object = \OC_Calendar_Object::cleanByAccessClass($task['id'], $object);
			
		$vtodo = $object->VTODO;
		return $vtodo;
	}

	public static function arrayForJSON($id, $vtodo, $user_timezone, $calendarId){
		$task = array( 'id' => $id );
		$task['calendarid'] = $calendarId;
		$task['type'] = 'task';
		$task['name'] = (string) $vtodo->SUMMARY;
		$task['created'] = (string) $vtodo->CREATED;
		$task['note'] = (string) $vtodo->DESCRIPTION;
		$task['location'] = (string) $vtodo->LOCATION;
		$task['uid'] = (string) $vtodo->UID;
		$task['related'] = (string) $vtodo->__get('RELATED-TO');
		$showsubtasks = (string) $vtodo->__get('X-OC-SHOWSUBTASKS');
		// show subtasks on default
		if ($showsubtasks == '0'){
			$task['showsubtasks'] = false;
		} else {
			$task['showsubtasks'] = true;
		}
		$categories = $vtodo->CATEGORIES;
		if ($categories){
			$task['categories'] = $categories->getParts();
		}
		$start = $vtodo->DTSTART;
		if ($start) {
			try {
				$start = $start->getDateTime();
				$start->setTimezone(new \DateTimeZone($user_timezone));
				$task['start'] = $start->format('Ymd\THis');
			} catch(\Exception $e) {
				$task['start'] = null;
				\OCP\Util::writeLog('tasks', $e->getMessage(), \OCP\Util::ERROR);
			}
		} else {
			$task['start'] = null;
		}
		$due = $vtodo->DUE;
		if ($due) {
			try {
				$due = $due->getDateTime();
				$due->setTimezone(new \DateTimeZone($user_timezone));
				$task['due'] = $due->format('Ymd\THis');
			} catch(\Exception $e) {
				$task['due'] = null;
				\OCP\Util::writeLog('tasks', $e->getMessage(), \OCP\Util::ERROR);
			}
		} else {
			$task['due'] = null;
		}
		$reminder = $vtodo->VALARM;
		if($reminder) {
			try {

				$reminderType = $reminder->TRIGGER['VALUE']->getValue();
				$reminderAction = $reminder->ACTION->getValue();
				$reminderDate = null;
				$reminderDuration = null;


				if($reminderType == 'DATE-TIME'){
					$reminderDate = $reminder->TRIGGER->getDateTime();
					$reminderDate->setTimezone(new \DateTimeZone($user_timezone));
					$reminderDate = $reminderDate->format('Ymd\THis');
				} elseif ($reminderType == 'DURATION' && ($start || $due)) {

					$parsed = VObject\DateTimeParser::parseDuration($reminder->TRIGGER,true);
					// Calculate the reminder date from duration and start date
					$related = null;
					if(is_object($reminder->TRIGGER['RELATED'])){
						$related = $reminder->TRIGGER['RELATED']->getValue();
						if($related == 'END' && $due){
							$reminderDate = $due->modify($parsed)->format('Ymd\THis');
						} else {
							throw new \Exception('Reminder duration related to not available date.');
						}
					} elseif ($start) {
						$reminderDate = $start->modify($parsed)->format('Ymd\THis');
					} else{
						throw new \Exception('Reminder duration related to not available date.');
					}
					preg_match('/^(?P<plusminus>\+|-)?P((?P<week>\d+)W)?((?P<day>\d+)D)?(T((?P<hour>\d+)H)?((?P<minute>\d+)M)?((?P<second>\d+)S)?)?$/', $reminder->TRIGGER, $matches);
		            $invert = false;
		            if ($matches['plusminus']==='-') {
		                $invert = true;
		            }

		            $parts = array(
		                'week',
		                'day',
		                'hour',
		                'minute',
		                'second',
		            );

		            $reminderDuration = array(
		            	'token' => null
		            	);
		            foreach($parts as $part) {
		                $matches[$part] = isset($matches[$part])&&$matches[$part]?(int)$matches[$part]:0;
		                $reminderDuration[$part] = $matches[$part];
		                if($matches[$part] && !$reminderDuration['token']){
		                	$reminderDuration['token'] = $part;
		                }
		            }
		            if($reminderDuration['token'] == null){
		            	$reminderDuration['token'] = $parts[0];
		            }

					$reminderDuration['params'] = array(
							'id'	=> (int)$invert.(int)($related == 'END'),
							'related'=> $related?$related:'START',
							'invert'=>	$invert
							);

				} else {
					$reminderDate = null;
					$reminderDuration = null;
				}
				
				$task['reminder'] = array(
					'type' 		=> $reminderType,
					'action'	=> $reminderAction,
					'date'		=> $reminderDate,
					'duration'	=> $reminderDuration
					);

			} catch(\Exception $e) {
				$task['reminder'] = null;
				\OCP\Util::writeLog('tasks', $e->getMessage(), \OCP\Util::ERROR);
			}
		} else {
			$task['reminder'] = null;
		}
		$priority = $vtodo->PRIORITY;
		if(isset($priority)){
			$priority = (10 - $priority->getValue()) % 10;
			$task['priority'] = (string) $priority;
			if($priority > 5){
				$task['starred'] = true;
			}
		} else {
			$task['priority'] = '0';
			$task['starred'] = false;
		}
		$completed = $vtodo->COMPLETED;
		if ($completed) {
			try {
				$completed = $completed->getDateTime();
				$completed->setTimezone(new \DateTimeZone($user_timezone));
				$task['completed_date'] = $completed->format('Ymd\THis');
				$task['completed'] = true;
			} catch(\Exception $e) {
				$task['completed'] = false;
				\OCP\Util::writeLog('tasks', $e->getMessage(), \OCP\Util::ERROR);
			}
		} else {
			$task['completed'] = false;
		}


		$percentComplete = $vtodo->{'PERCENT-COMPLETE'};
		if($percentComplete){
			$task['complete'] = $percentComplete->getValue();
		} else {
			$task['complete'] = '0';
		}


		$comments = $vtodo->COMMENT;
		if($comments){
			$comments_parsed = array();
			foreach($comments as $com) {
				// parse time
				$time = $com['X-OC-DATE-TIME'];
				if ($time) {
					$time = new \DateTime($time);
					$time->setTimezone(new \DateTimeZone($user_timezone));
					$time = $time->format('Ymd\THis');
				}
				// parse comment ID
				$comID = $com['X-OC-ID'];
				if ($comID) {
					$comID = $com['X-OC-ID']->getValue();
				}
				// parse user ID
				$userID = $com['X-OC-USERID'];
				if ($userID) {
					$userID = (string) $com['X-OC-USERID']->getValue();
				}
				$user = \OC::$server->getUserManager()->get($userID);
				$userName = $userID;
				if ($user){
					$userName = $user->getDisplayName();
				}
				$comments_parsed[] = array(
					'id' => $comID,
					'userID' => $userID,
					'name' => $userName,
					'comment' => $com->getValue(),
					'time' => $time
					);
			}
			$task['comments'] = $comments_parsed;
		}
		return $task;
	}


	public static function createVCalendarFromRequest($request){
		$vcalendar = new \Sabre\VObject\Component\VCalendar();
		$vcalendar->PRODID = 'ownCloud Calendar';
		$vcalendar->VERSION = '2.0';

		$vtodo = $vcalendar->createComponent('VTODO');
		$vcalendar->add($vtodo);

		$vtodo->CREATED = new \DateTime('now', new \DateTimeZone('UTC'));

		$vtodo->UID = \Sabre\VObject\UUIDUtil::getUUID();
		return self::updateVCalendarFromRequest($request, $vcalendar);
	}

	public static function updateVCalendarFromRequest($request, $vcalendar){
		$vtodo = $vcalendar->VTODO;

		$lastModified = $vtodo->{'LAST-MODIFIED'};
		if(is_null($lastModified)) {
			$lastModified = $vtodo->add('LAST-MODIFIED');
		}
		$lastModified->setValue(new \DateTime('now', new \DateTimeZone('UTC')));
		$vtodo->DTSTAMP = new \DateTime('now', new \DateTimeZone('UTC'));
		$vtodo->SUMMARY = $request['summary'];

		if($request['location']){
			$vtodo->LOCATION = $request['location'];
		}
		if ($request['description']){
			$vtodo->DESCRIPTION = $request['description'];
		}
		if($request["categories"]){
			$vtodo->CATEGORIES = $request["categories"];
		}
		if($request["related"]){
			$vtodo->{'RELATED-TO'} = $request["related"];
		}
		if($request['priority']) {
			$vtodo->PRIORITY = 5; // prio: medium
		} else {
			$vtodo->PRIORITY = 0; // prio: undefined
		}
		$percentComplete = $vtodo->{'PERCENT-COMPLETE'};
		if (is_null($percentComplete)) {
			$percentComplete = $vtodo->add('PERCENT-COMPLETE');
		}
		if (isset($request['complete'])) {
			$percentComplete->setValue($request['complete']);
		} else {
			$percentComplete->setValue('0');
		}

		$due = $request['due'];
		if ($due) {
			$timezone = \OC_Calendar_App::getTimezone();
			$timezone = new \DateTimeZone($timezone);
			$due = new \DateTime($due, $timezone);
			$vtodo->DUE = $due;
		} else {
			unset($vtodo->DUE);
		}
		$start = $request['start'];
		if ($start) {
			$timezone = \OC_Calendar_App::getTimezone();
			$timezone = new \DateTimeZone($timezone);
			$start = new \DateTime($start, $timezone);
			$vtodo->DTSTART = $start;
		} else {
			unset($vtodo->DTSTART);
		}

		return $vcalendar;
	}
}
