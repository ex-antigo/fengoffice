<?php

/**
 * ApplicationLog class
 * Generated on Tue, 07 Mar 2006 12:19:49 +0100 by DataObject generation tool
 *
 * @author Ilija Studen <ilija.studen@gmail.com>
 */
class ApplicationLog extends BaseApplicationLog {

	
	/**
	 * Return user who made this acction
	 *
	 * @access public
	 * @param void
	 * @return Contact
	 */
	function getTakenBy() {
		return Contacts::instance()->findById($this->getTakenById());
	} // getTakenBy

	
	/**
	 * Return taken by display name
	 *
	 * @access public
	 * @param void
	 * @return string
	 */
	function getTakenByDisplayName() {
		$taken_by = $this->getTakenBy();
		return $taken_by instanceof Contact ? $taken_by->getObjectName() : lang('n/a');
	} // getTakenByDisplayName

	
	/**
	 * Returns true if this application log is made today
	 *
	 * @access public
	 * @param void
	 * @return boolean
	 */
	function isToday() {
		$now = DateTimeValueLib::now();
		$created_on = $this->getCreatedOn();

		// getCreatedOn and similar functions can return NULL
		if(!($created_on instanceof DateTimeValue)) return false;

		return $now->getDay() == $created_on->getDay() &&
		$now->getMonth() == $created_on->getMonth() &&
		$now->getYear() == $created_on->getYear();
	} // isToday

	
	/**
	 * Returnst true if this application log was made yesterday
	 *
	 * @param void
	 * @return boolean
	 */
	function isYesterday() {
		$created_on = $this->getCreatedOn();
		if(!($created_on instanceof DateTimeValue)) return false;

		$day_after = $created_on->advance(24 * 60 * 60, false);
		$now = DateTimeValueLib::now();

		return $now->getDay() == $day_after->getDay() &&
		$now->getMonth() == $day_after->getMonth() &&
		$now->getYear() == $day_after->getYear();
	} // isYesterday


	/**
	 * @return true if there are details associated to this log, saved in application_log_details table
	 */
	function hasDetails() {
		if (in_array($this->getAction(), array(ApplicationLogs::ACTION_LINK, ApplicationLogs::ACTION_UNLINK))) {
			return false;
		}
		return ApplicationLogDetails::countLogDetails($this->getId()) > 0;
	}

	/**
	 * @return array the details associated to this log
	 */
	function getDetails() {
		return ApplicationLogDetails::getLogDetails($this->getId());
	}

	/**
	 * @return string the formatted text of the request channel value
	 */
	function getRequestChannelText() {
		$text = "";

		if ($this->getRequestChannel() != '') {
			if (Localization::instance()->lang_exists($this->getRequestChannel())) {
				$text = lang($this->getRequestChannel());
			} else {
				$exploded = explode('-', $this->getRequestChannel());
				$parts = array();
				foreach ($exploded as $exp) {
					$parts[] = ucfirst(trim($exp));
				}
				$text = implode(' - ', $parts);
			}
		}

		return $text;
	}

	
	/**
	 * Return text message for this entry. If is lang formed as 'log' + action + manager name
	 *
	 * 'log add projectmessages'
	 *
	 * Object name is passed as a first param so it can be used in a message
	 *
	 * @access public
	 * @param void
	 * @return string
	 */
	function getText() {
		$code = strtolower('log ' . ($this->getAction()) . ' ' . $this->getObject()->getObjectTypeName());
		$data = $this->getActionData();
		if ($data)
			$code = $code . ' data';
		return lang($code, clean($this->getObjectName()), $this->getActionData());
	} // getText
	
	
	function getActionData() {
		$result = $this->getLogData();
		
		if ($this->getLogData() != ''){
			if($this->getAction()== ApplicationLogs::ACTION_LINK || $this->getAction()== ApplicationLogs::ACTION_UNLINK){
					$split = explode(':',$this->getLogData());
					$obj = Objects::findObject($split[1]);
					if ($obj && $obj->canView(logged_user())){
						$ico_class = '';
						$result = '<a class="internalLink coViewAction ' . $obj->getObjectType()->getIconClass() . '" href="' . $obj->getViewUrl() . '">' .  clean($obj->getObjectName()) . '</a>';
					}
			}
		}
		
		return $result;
	}

	
	/**
	 * Return object connected with this action
	 *
	 * @access public
	 * @param void
	 * @return ApplicationDataObject
	 */
	function getObject() {
		return Objects::findObject($this->getRelObjectId());
	} // getObject

	
	/**
	 * This function will try load related object and return its YRL. If object is not found '' is retuned
	 *
	 * @access public
	 * @param void
	 * @return string
	 */
	function getObjectUrl() {
		$object = $this->getObject();
		return $object instanceof ApplicationDataObject ? $object->getObjectUrl() : null;
	} // getObjectMessage

	
	/**
	 * Return object type name
	 *
	 * @param void
	 * @return string
	 */
	function getObjectTypeName() {
		$object = $this->getObject();
		return $object instanceof ApplicationDataObject ? $object->getObjectTypeName() : null;
	} // getObjectTypeName

	
	function getActivityData($caller = null) {
		$user = Contacts::instance()->findById($this->getCreatedById());
		$object = Objects::findObject($this->getRelObjectId());
		if($caller === 'hideSystemActivity' && !$user) return false;
		
		if ($this->getIsMailRule()) {
			$ruleData = json_decode($this->getLogData());
		
			$ruleDataName = htmlspecialchars($ruleData->rule_name, ENT_QUOTES);
			$ruleDataDescription = htmlspecialchars($ruleData->rule_detail, ENT_QUOTES);
		
			$userName = lang('mail rule') . ' <a href="#" onclick="og.showMailRuleModal(\'' 
            			. '<strong>' . lang('rule name') . ' </strong><br>' . addslashes($ruleDataName) . '<br><br>'
            			. '<strong>' . lang('rule detail') . ' </strong><br>' . addslashes($ruleDataDescription) 
           				. '\'); return false;">'
            			. htmlspecialchars($ruleDataName, ENT_QUOTES) 
            			. '</a>';


		} else {
			$userName = htmlspecialchars($user->getDisplayName());
		}
		
		$icon_class = "";
		if ($object instanceof ProjectFile) {
			$path = explode("-", str_replace(".", "_", str_replace("/", "-", $object->getTypeString())));
			$acc = "";
			foreach ($path as $p) {
				$acc .= $p;
				$icon_class .= ' ico-' . $acc;
				$acc .= "-";
			}			
		}
		// Build data depending on type
		if ($object){
			if ($object instanceof Contact && $object->isUser()) {
				$type  = "user" ;
			}else{
				$type = $object->getObjectTypeName() ;
			}
			
			$object_link = '<a href="' . $object->getObjectUrl() . '">&nbsp;'.
			'<span style="padding: 2px 0 3px 24px;" class="link-ico ico-unknown ico-' . $type . $icon_class . '"/>'.clean($object->getObjectName()).'</a>';
		} else{
			$type = null;
			$object_link = '"'.clean($this->getObjectName()).'"&nbsp;<span class="desc">'.lang('object is deleted').'</span>';
		}	
		switch ($this->getAction()) {
			case ApplicationLogs::ACTION_EDIT :
			case ApplicationLogs::ACTION_ADD :
			case ApplicationLogs::ACTION_DELETE :
			case ApplicationLogs::ACTION_TRASH :
			case ApplicationLogs::ACTION_UNTRASH :
			case ApplicationLogs::ACTION_OPEN :
			case ApplicationLogs::ACTION_CLOSE :
			case ApplicationLogs::ACTION_ARCHIVE :
			case ApplicationLogs::ACTION_UNARCHIVE :
			case ApplicationLogs::ACTION_READ :				
			case ApplicationLogs::ACTION_DOWNLOAD :				
			case ApplicationLogs::ACTION_UPLOAD :				
			case ApplicationLogs::ACTION_CHECKIN :
			case ApplicationLogs::ACTION_CHECKOUT :
			case ApplicationLogs::ACTION_PRINT :
			case ApplicationLogs::ACTION_MARK_AS_SPAM :
			case ApplicationLogs::ACTION_UNMARK_AS_SPAM :
				
				return lang('activity ' . $this->getAction(), ($type ? lang('the '.$type) : ""), $userName, $object_link);
				
			case ApplicationLogs::ACTION_SUBSCRIBE :
			case ApplicationLogs::ACTION_UNSUBSCRIBE :
				$user_ids = explode(",", $this->getLogData());
				if (count($user_ids) < 8) {
					$users_str = "";
					foreach ($user_ids as $usid) {
						$su = Contacts::instance()->findById($usid);
						if ($su instanceof Contact)
							$users_str .= '<a style="font-weight:bold" href="'.$su->getObjectUrl().'">&nbsp;<span style="padding: 0 0 3px 24px;" class="db-ico ico-unknown ico-user"/>'.clean($su->getObjectName()).'</a>, ';
					}
					if (count($user_ids) == 1) {
						$users_text = substr(trim($users_str), 0, -1);
					} else {
						$users_text = lang('x users', count($user_ids), ": $users_str");
					} 
				} else {
					$users_text = lang('x users', count($user_ids), "");
				}
				if ($object)
					return lang('activity ' . $this->getAction(), lang('the '.$object->getObjectTypeName()),$userName, $object_link, $users_text);
			case ApplicationLogs::ACTION_COMMENT :
				if ($object instanceof Comment) {
					$rel_object = $object->getRelObject();
					return lang('activity ' . $this->getAction(), lang('the '.$rel_object instanceof ContentDataObject ? $rel_object->getObjectTypeName() : 'object'),  $userName, $object_link, utf8_safe($this->getLogData()));
				} else {
					if ($object)
						return lang('activity ' . $this->getAction(), lang('the '.$object->getObjectTypeName()),  $userName, $object_link, utf8_safe($this->getLogData()));
				}
			case ApplicationLogs::ACTION_FORWARD :
			case ApplicationLogs::ACTION_REPLY :
			case ApplicationLogs::ACTION_REPLY_ALL :
			case ApplicationLogs::ACTION_LINK :
			case ApplicationLogs::ACTION_UNLINK :
				$linked_object = Objects::findObject($this->getLogData());
				if ($linked_object instanceof ApplicationDataObject ) {
					$icon_class = "";
					if ($linked_object instanceof ProjectFile) {
						$path = explode("-", str_replace(".", "_", str_replace("/", "-", $linked_object->getTypeString())));
						$acc = "";
						foreach ($path as $p) {
							$acc .= $p;
							$icon_class .= ' ico-' . $acc;
							$acc .= "-";
						}			
					}
					$linked_object_link = '<a href="' . $linked_object->getObjectUrl() . '">&nbsp;<span style="padding: 1px 0 3px 24px;" class="db-ico ico-unknown ico-'.$linked_object->getObjectTypeName() . $icon_class . '"/>'.clean($linked_object->getObjectName()).'</a>';
				} else $linked_object_link = '';
				if ($object) {
					return lang('activity ' . $this->getAction(), lang('the '.$object->getObjectTypeName()),  $userName, $object_link, $linked_object instanceof ApplicationDataObject ? lang('the '.$linked_object->getObjectTypeName()) : '', $linked_object_link);
				}
				break;

			case ApplicationLogs::ACTION_RELATION_ADDED :
			case ApplicationLogs::ACTION_RELATION_EDITED :
			case ApplicationLogs::ACTION_RELATION_REMOVED :

				$related_log = ApplicationLogs::instance()->findById($this->getLogData());
				if ($related_log instanceof ApplicationLog) {

					$related_object = $related_log->getObject();					
					if ($related_object instanceof ContentDataObject) {
	
						$related_object_url = $related_object->getObjectUrl();
						if ($related_object instanceof Timeslot) {
							// as we don't have a view for timeslots then put the link to the history log of it
							$related_object_url = "javascript:og.render_modal_form('', {c:'object', a:'view_history', params:{id:".$related_object->getId()."}});";
						}
						
						$related_object_link = '<a href="' . $related_object_url . '">&nbsp;<span style="padding: 1px 0 3px 24px;" class="db-ico ico-unknown ico-'. $related_object->getObjectTypeName() .'"/>'. clean($related_object->getObjectName()) .'</a>';
	
						return lang('activity ' . $this->getAction(), lang('the '.$object->getObjectTypeName()) . $object_link,  $userName, lang('the '.$related_object->getObjectTypeName()) . $related_object_link);
					}
				}

				break;
				
			case ApplicationLogs::ACTION_MOVE :
			case ApplicationLogs::ACTION_COPY :
				$exploded = explode(";", $this->getLogData());
				$from_names = array();
				$to_names = array();
				foreach ($exploded as $str) {
					if (str_starts_with($str, "from:")) {
						$from_ids_csv = str_replace("from:", "", $str);
						if ($from_ids_csv != '') {
							$from_rows = DB::executeAll("SELECT name FROM ".TABLE_PREFIX."members WHERE id IN ($from_ids_csv)");
							foreach ($from_rows as $r) {
								$from_names[] = $r['name'];
							}
						}
					} else if (str_starts_with($str, "to:")) {
						$to_ids_csv = str_replace("to:", "", $str);
						if ($to_ids_csv != '') {
							$to_rows = DB::executeAll("SELECT name FROM ".TABLE_PREFIX."members WHERE id IN ($to_ids_csv)");
							foreach ($to_rows as $r) {
								$to_names[] = $r['name'];
							}
						}
					}
				}
				if($object){
					if ($object instanceof Timeslot) {
						$object_link = '';
					}
					$from_str = '<span class="bold">'.implode(', ', $from_names).'</span>';
					$to_str = '<span class="bold">'.implode(', ', $to_names).'</span>';
					if (count($from_names) > 0 && count($to_names) > 0) {
						return lang('activity ' . $this->getAction() . ' from to', lang('the '.$object->getObjectTypeName()),  $userName, $object_link, $from_str, $to_str);
					} else if (count($from_names) > 0) {
						return lang('activity ' . $this->getAction() . ' from', lang('the '.$object->getObjectTypeName()),  $userName, $object_link, $from_str);
					} else if (count($to_names) > 0) {
						return lang('activity ' . $this->getAction() . ' to', lang('the '.$object->getObjectTypeName()),  $userName, $object_link, $to_str);
					} else {
						return lang('activity ' . $this->getAction() . ' no ws', lang('the '.$object->getObjectTypeName()),  $userName, $object_link);
					}
				}
				
			case ApplicationLogs::ACTION_LOGIN :
			case ApplicationLogs::ACTION_LOGOUT :
				return lang('activity ' . $this->getAction(),  $userName);					
			/*FIXME when D&D is implemented case ApplicationLogs::ACTION_MOVE :
				$exploded = explode(";", $this->getLogData());
				$to_str = "";
				$from_str = "";
				foreach ($exploded as $str) {
					if (str_starts_with($str, "from:")) {
						$wsids_csv = str_replace("from:", "", $str);
						$wsids = array_intersect(explode(",", logged_user()->getActiveProjectIdsCSV()), explode(",", $wsids_csv));
						if (is_array($wsids) && count($wsids) > 0) {
							$from_str = '<span class="project-replace">' . implode(",", $wsids) . '</span>';
						}
					} else if (str_starts_with($str, "to:")) {
						$wsids_csv = str_replace("to:", "", $str);
						$wsids = array_intersect(explode(",", logged_user()->getActiveProjectIdsCSV()), explode(",", $wsids_csv));
						if (is_array($wsids) && count($wsids) > 0) {
							$to_str = '<span class="project-replace">' . implode(",", $wsids) . '</span>';
						}						
					}
				}
				if($object){
					if ($from_str != "" && $to_str != "") {						
						return lang('activity ' . $this->getAction() . ' from to', lang('the '.$object->getObjectTypeName()), $user->getDisplayName(), $object_link, $from_str, $to_str);
					} else if ($from_str != "") {
						return lang('activity ' . $this->getAction() . ' from', lang('the '.$object->getObjectTypeName()), $user->getDisplayName(), $object_link, $from_str);
					} else if ($to_str != "") {
						return lang('activity ' . $this->getAction() . ' to', lang('the '.$object->getObjectTypeName()), $user->getDisplayName(), $object_link, $to_str);
					} else {
						return lang('activity ' . $this->getAction() . ' no ws', lang('the '.$object->getObjectTypeName()), $user->getDisplayName(), $object_link);
					}
				}		
			case ApplicationLogs::ACTION_COPY :				
				$to_str = "";
				$wsids_csv = str_replace("to:", "", $this->getLogData());
				$wsids = array_intersect(explode(",", logged_user()->getActiveProjectIdsCSV()), explode(",", $wsids_csv));
				if (is_array($wsids) && count($wsids) > 0) {
					$to_str = '<span class="project-replace">' . implode(",", $wsids) . '</span>';
				}
				if($object){
					if ($to_str != "") {
						return lang('activity ' . $this->getAction() . ' to', lang('the '.$object->getObjectTypeName()), $user->getDisplayName(), $object_link, $to_str);
					} 
				}else{
					if ($to_str != "") {
						return lang('activity ' . $this->getAction() . ' to', lang('the '.$this->getRelObjectManager()), $user->getDisplayName(), $object_link, $to_str);
					}
				}	*/		
			default: return false;
		}
		return false;
	}
        
	function getActivityDataView($user,$object,$made_several_changes = false) {
		if (!$user) return false;
		$userName = "<b>".$user->getObjectName()."</b>";
		$icon_class = "";
		if ($object instanceof ProjectFile) {
			$path = explode("-", str_replace(".", "_", str_replace("/", "-", $object->getTypeString())));
			$acc = "";
			foreach ($path as $p) {
				$acc .= $p;
				$icon_class .= ' ico-' . $acc;
				$acc .= "-";
			}			
		}
		// Build data depending on type
		if ($object instanceof ContentDataObject){
			if ($object instanceof Contact && $object->isUser()) {
				$type = "user" ;
			}else{
				$type = $object->getObjectTypeName() ;
			}
			if (($type != 'Time') || ($type == 'Time' && $object->getRelObjectId() != 0)){
				$object_link = '<br /><a href="' . $object->getObjectUrl() . '">'.
				'<span style="padding: 2px 0 3px 24px;" class="link-ico ico-unknown ico-' . $type . $icon_class . '"/>'.clean($object->getObjectName()).'</a>';
			} else {
				//if it is a general timeslot
				$object_link = '<span style="padding: 1px 0 3px 24px;" class="db-ico ico-unknown ico-' . $type . $icon_class . '"/>'.clean($object->getObjectName());
			}
		} elseif ($object instanceof Member){
			$object_type = ObjectTypes::instance()->findById($object->getObjectTypeId());
			$type = $object_type->getName();
			$object_url = "";
			
			$onclick = "";
			switch ($type)
			{
				case "folder":
					$onclick = "og.crpm.onFolderClick(".$object->getId().");";
					break;
				case "project":
					$onclick = "og.projects.onProjectClick(".$object->getId().");";
					break;
				case "customer":
					$onclick = "og.customers.onCustomerClick(".$object->getId().");";
					break;
				case "workspace":
					$onclick = "og.workspaces.onWorkspaceClick(".$object->getId().");";
					break;
				default:
					$onclick = "";
			}
			
			$icon_class .= " ico-color" . $object->getColor();
			
			$object_link =  '<br /><a class="internalLink" href="javascript:void(0);" onclick="'.$onclick.'">'.
					'<span style="padding: 1px 0 3px 24px;" class="db-ico ico-unknown ico-' . $type . $icon_class . '"/>'.clean($this->getObjectName()).'</a>';
			
			return lang('activity ' . $this->getAction(), lang('the '.$type," "), $userName , $object_link);
		} else {
			$object_link = '<br />'. clean($this->getObjectName()).'&nbsp;'.lang('object is deleted');
			return lang('activity ' . $this->getAction(), "", $userName , $object_link);
		}
		if($made_several_changes){
			$this->setAction(ApplicationLogs::ACTION_MADE_SEVERAL_CHANGES);
		}
		switch ($this->getAction()) {
            case ApplicationLogs::ACTION_MADE_SEVERAL_CHANGES :
            	/*$object_history = '<a style="font-weight:bold" href="' . $object->getViewHistoryUrl() . '">&nbsp;'.
				'<span style="padding: 1px 0 3px 18px;" class="db-ico ico-unknown ico-history"/>'.lang('view history').'</a>';*/
            	return lang('activity ' . $this->getAction(), lang('the ' .$type. ' activity', $object_link), $userName,"");
			case ApplicationLogs::ACTION_EDIT :
			case ApplicationLogs::ACTION_ADD :
			case ApplicationLogs::ACTION_DELETE :
			case ApplicationLogs::ACTION_TRASH :
			case ApplicationLogs::ACTION_UNTRASH :
			case ApplicationLogs::ACTION_OPEN :
			case ApplicationLogs::ACTION_CLOSE :
			case ApplicationLogs::ACTION_ARCHIVE :
			case ApplicationLogs::ACTION_UNARCHIVE :
			case ApplicationLogs::ACTION_READ :
			case ApplicationLogs::ACTION_DOWNLOAD :
			case ApplicationLogs::ACTION_UPLOAD :
			case ApplicationLogs::ACTION_CHECKIN :
			case ApplicationLogs::ACTION_CHECKOUT :
			case ApplicationLogs::ACTION_PRINT :
			case ApplicationLogs::ACTION_MARK_AS_SPAM :
			case ApplicationLogs::ACTION_UNMARK_AS_SPAM :
				if ($object instanceof ContentDataObject) {
					return lang('activity ' . $this->getAction(), lang('the '.$type," "), $userName, $object_link);
				}
			case ApplicationLogs::ACTION_SUBSCRIBE :
			case ApplicationLogs::ACTION_UNSUBSCRIBE :
				$user_ids = explode(",", $this->getLogData());
				if (count($user_ids) < 8) {
					$users_str = "";
					foreach ($user_ids as $usid) {
						$su = Contacts::instance()->findById($usid);
						if ($su instanceof Contact) {
							$users_str .= '<a style="font-weight:bold" href="'.$su->getObjectUrl().'">&nbsp;<span style="padding: 0 0 3px 24px;" class="db-ico ico-unknown ico-user"/>'.clean($su->getObjectName()).'</a>, ';
						}
					}
					if (count($user_ids) == 1) {
						$users_text = substr(trim($users_str), 0, -1);
					} else {
						$users_text = lang('x users', count($user_ids), ": $users_str");
					} 
				} else {
					$users_text = lang('x users', count($user_ids), "");
				}
				if ($object instanceof ContentDataObject) {
					return lang('activity ' . $this->getAction(), lang('the '.$object->getObjectTypeName()," "), $userName , $object_link, $users_text);
				}
			case ApplicationLogs::ACTION_COMMENT :
				if ($object instanceof ContentDataObject) {
					$rel_object = Objects::findObject($this->getRelObjectId());
					$commented_object = null;
					if ($rel_object instanceof Comment) {
						$commented_object = $rel_object->getRelObject();
					}
					$obj_type_name = $commented_object instanceof ContentDataObject ? $commented_object->getObjectTypeName() : $rel_object->getObjectTypeName();
					$comentText = $this->getLogData();
					return lang('activity ' . $this->getAction(), lang('the '.$obj_type_name," "), $userName, $object_link, $comentText);
					
				}
			case ApplicationLogs::ACTION_FORWARD :
			case ApplicationLogs::ACTION_REPLY :
			case ApplicationLogs::ACTION_REPLY_ALL :
			case ApplicationLogs::ACTION_LINK :
			case ApplicationLogs::ACTION_UNLINK :
				$linked_object_link = '';
				$linked_object = Objects::findObject($this->getLogData());
				if ($linked_object instanceof ApplicationDataObject ) {
					$icon_class = "";
					if ($linked_object instanceof ProjectFile) {
						$path = explode("-", str_replace(".", "_", str_replace("/", "-", $linked_object->getTypeString())));
						$acc = "";
						foreach ($path as $p) {
							$acc .= $p;
							$icon_class .= ' ico-' . $acc;
							$acc .= "-";
						}
					}
					$linked_object_link = '<a href="' . $linked_object->getObjectUrl() . '">&nbsp;<span style="padding: 1px 0 3px 24px;" class="db-ico ico-unknown ico-'.$linked_object->getObjectTypeName() . $icon_class . '"/>'.clean($linked_object->getObjectName()).'</a>';
				}
				if ($object instanceof ContentDataObject) {
					return lang('activity ' . $this->getAction(), lang('the '.$object->getObjectTypeName()," "), $userName, $object_link, $linked_object instanceof ApplicationDataObject ? lang('the '.$linked_object->getObjectTypeName()) : '', $linked_object_link);
				}
				break;

			case ApplicationLogs::ACTION_RELATION_ADDED :
			case ApplicationLogs::ACTION_RELATION_EDITED :
			case ApplicationLogs::ACTION_RELATION_REMOVED :

				$related_log = ApplicationLogs::instance()->findById($this->getLogData());
				if ($related_log instanceof ApplicationLog) {

					$related_object = $related_log->getObject();					
					if ($related_object instanceof ContentDataObject) {
	
						$related_object_link = '<a href="' . $related_object->getObjectUrl() . '">&nbsp;<span style="padding: 1px 0 3px 24px;" class="db-ico ico-unknown ico-'. $related_object->getObjectTypeName() .'"/>'. clean($related_object->getObjectName()) .'</a>';
	
						return lang('activity ' . $this->getAction(), lang('the '.$object->getObjectTypeName()) . $object_link, $user->getDisplayName(), lang('the '.$related_object->getObjectTypeName()) . $related_object_link);
					}
				}

				break;

			case ApplicationLogs::ACTION_LOGIN :
			case ApplicationLogs::ACTION_LOGOUT :
				return lang('activity ' . $this->getAction(), $userName);
			case ApplicationLogs::ACTION_MOVE :
				$from_to = explode(";", $this->getLogData());
				$to = "";
				$from = "";
				if (is_array($from_to) && count($from_to) > 0) {
					foreach($from_to as $fr_to){
						if(strpos($fr_to, 'from:') !== FALSE){
							$from = $fr_to;
						}elseif (strpos($fr_to, 'to:') !== FALSE){
							$to = $fr_to;
						}						
					}
				}
				
				//to
				$to_str = "";
				$to_str_member = "";
				$members_ids_csv = str_replace("to:", "", $to);
				$mem_ids = explode(",", $members_ids_csv);
				if (is_array($mem_ids) && count($mem_ids) > 0) {
					foreach($mem_ids as $mem_id){
						$member = Members::instance()->findById($mem_id);
						if($member){
							$to_str_member .= $member->getName() . ", ";
						}
					}
					if($to_str_member != ""){
						$to_str_member = substr($to_str_member , 0, -2);
						$to_str .= $to_str_member;
					}
				}
				
				//from
				$from_str = "";
				$from_str_member = "";
				$members_ids_csv_from = str_replace("from:", "", $from);
				$mem_ids_from = explode(",", $members_ids_csv_from);
				
				if (is_array($mem_ids_from) && count($mem_ids_from) > 0) {
					foreach($mem_ids_from as $mem_id){
						$member = Members::instance()->findById($mem_id);
						if($member){
							$from_str_member .= $member->getName() . ", ";
						}
					}
					if($from_str_member != ""){
						$from_str_member = substr($from_str_member , 0, -2);
						$from_str .= $from_str_member;
					}
				}
				
				if($object instanceof ContentDataObject){
					if ($to_str != "") {
						return lang('activity ' . $this->getAction() . ' from to', lang('the '.$object->getObjectTypeName()), $userName, $object_link, $from_str,$to_str);
					}
				}else{
					if ($to_str != "") {
						return lang('activity ' . $this->getAction() . ' from to', lang('the '.$this->getRelObjectManager()), $userName, $object_link, $from_str,$to_str);
					}
				}
			case ApplicationLogs::ACTION_COPY :				
				$to_str = "";
				$to_str_member = "";
				$members_ids_csv = str_replace("to:", "", $this->getLogData());
				$mem_ids = explode(",", $members_ids_csv);
				if (is_array($mem_ids) && count($mem_ids) > 0) {
					foreach($mem_ids as $mem_id){
						$member = Members::instance()->findById($mem_id);
						if($member){
							$to_str_member .= $member->getName() . ", ";
						}
					}
					if($to_str_member != ""){
						$to_str_member = substr($to_str_member , 0, -2);
						$to_str .= $to_str_member;
					}                                    
				}
				if($object instanceof ContentDataObject){
					if ($to_str != "") {
						return lang('activity ' . $this->getAction() . ' to', lang('the '.$object->getObjectTypeName()), $userName, $object_link, $to_str);
					} 
				}else{
					if ($to_str != "") {
						return lang('activity ' . $this->getAction() . ' to', lang('the '.$this->getRelObjectManager()), $userName, $object_link, $to_str);
					}
				}
			default: return $this->getAction();false;
		}
		return $this->getAction();false;
	}
	
} // ApplicationLog

?>