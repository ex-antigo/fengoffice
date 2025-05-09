<?php

/**
 * Controller that is responsible for handling objects linking related requests
 *
 * @version 1.0
 * @author Ilija Studen <ilija.studen@gmail.com>
 */
class ObjectController extends ApplicationController {

	function index(){
		$this->setLayout('html');

	}
	/**
	 * Construct the ObjectController
	 *
	 * @access public
	 * @param void
	 * @return ObjectController
	 */
	function __construct() {
		parent::__construct();
		prepare_company_website_controller($this, 'website');
	} // __construct

	function popup_member_chooser() {
		tpl_assign('content_object_type_id', array_var($_GET, 'obj_type'));
		tpl_assign('genid', array_var($_GET, 'genid'));
		tpl_assign('selected', array_var($_GET, 'selected'));
		$this->setLayout("html");
	}

	function render_cps() {
		ajx_current("empty");
		$object = Objects::findObject(get_id());

		// if object not found, use a new object with the same object type
		if (!$object instanceof ContentDataObject) {
			$object_type = ObjectTypes::instance()->findById(get_id('ot_id'));
			if ($object_type instanceof ObjectType && class_exists($object_type->getHandlerClass()) ) {
				eval('$ot_manager = '.$object_type->getHandlerClass().'::instance();');
				if ($ot_manager) {
					eval('$object = new '.$ot_manager->getItemClass().'();');
					if ($object instanceof ContentDataObject) {
						$object->setObjectTypeId($object_type->getId());
					}
				}
			}
		}

		$visibility = array_var($_REQUEST, 'visibility', 'all');

		// get custom properties html to render
		$html = "";
		if ($object instanceof ContentDataObject) {
			ob_start();
			render_object_custom_properties($object, null, null, $visibility);
			$html = ob_get_clean();
		}
		ajx_extra_data(array('html' => $html));
	}

    function render_bootstrap_cps() {
        ajx_current("empty");
        $object = Objects::findObject(get_id());

        // if object not found, use a new object with the same object type
        if (!$object instanceof ContentDataObject) {
            $object_type = ObjectTypes::instance()->findById(get_id('ot_id'));
            if ($object_type instanceof ObjectType && class_exists($object_type->getHandlerClass()) ) {
                eval('$ot_manager = '.$object_type->getHandlerClass().'::instance();');
                if ($ot_manager) {
                    eval('$object = new '.$ot_manager->getItemClass().'();');
                    if ($object instanceof ContentDataObject) {
                        $object->setObjectTypeId($object_type->getId());
                    }
                }
            }
        }

        $visibility = array_var($_REQUEST, 'visibility', 'all');
        $selector = array_var($_REQUEST,'selector',null);
        $prefix = array_var($_REQUEST,'prefix',null);
        // get custom properties html to render
        $html = "";
        if ($object instanceof ContentDataObject) {
            ob_start();
            render_object_custom_properties_bootstrap($object, null, null, $visibility,0,$prefix);
            $html = ob_get_clean();
        }
        ajx_extra_data(array('html' => $html,'selector'=>$selector));
    }

	function add_subscribers(ContentDataObject $object, $subscribers = null, $check_permissions = true, $send_notification = true) {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$log_info = "";
		$log_info_unsubscribe = "";
		if ($subscribers == null) {
			$subscribers = array_var($_POST, 'subscribers');
		}
		$subscribers_ids = array();

		if (is_array($subscribers)) {
			$user_ids = array();
			$subscribers_to_remove = array();
			//add new subscribers
			foreach ($subscribers as $key => $checked) {
				$user_id = substr($key, 5);
				$subscribers_ids[] = $user_id;
				if ($checked == "1" && !in_array($user_id, $object->getSubscriberIds())) {
					$user = Contacts::instance()->findById($user_id);
					if ($user instanceof Contact) {
						$object->subscribeUser($user);
						$log_info .= ($log_info == "" ? "" : ",") . $user->getId();
						$user_ids[] = $user_id;
					}
				} else {
					if ((!$checked || $checked=='0') && in_array($user_id, $object->getSubscriberIds())) $subscribers_to_remove[] = $user_id;
				}
			}

			
			foreach ($subscribers_to_remove as $subs_remove) {
				$user = Contacts::instance()->findById($subs_remove);
				if ($user instanceof Contact) {
					$object->unsubscribeUser($user);
					$log_info_unsubscribe .= ($log_info_unsubscribe == "" ? "" : ",") . $user->getId();
				}
			}

			Hook::fire ('after_add_subscribers', array('object' => $object, 'user_ids' => $user_ids), $null);

			if ($log_info != "") {
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_SUBSCRIBE, false, !$send_notification, true, $log_info);
			}
			if ($log_info_unsubscribe != "") {
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_UNSUBSCRIBE, false, !$send_notification, true, $log_info_unsubscribe);
			}
		}else{
			// logger::log(' aver por aca!');
			$subscribers_to_remove = $object->getSubscriberIds();
			foreach ($subscribers_to_remove as $user_id_remove) {
				$log_info_unsubscribe.= ($log_info_unsubscribe == "" ? "" : ",") . $user_id_remove;
			}

			$object->clearSubscriptions();

			if ($log_info_unsubscribe != "") {
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_UNSUBSCRIBE, false, true, true, $log_info_unsubscribe);
			}
		}

		if($check_permissions){
			// remove subscribers without permissions
			$subscribed_users = $object->getSubscribers();
			foreach ($subscribed_users as $user) {
				if (!$object->canView($user)) {
					$object->unsubscribeUser($user);
				}
			}
		}

	}

	function redraw_subscribers_list() {
		$object = Objects::findObject(array_var($_GET, 'id'));
		if (!$object) {
			ajx_current("empty");
			return;
		}
		tpl_assign('object', $object);
		$this->setLayout("html");
		$this->setTemplate("list_subscribers");
	}

	function add_subscribers_list() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$genid = array_var($_GET,'genid');
		$obj_id = array_var($_GET,'obj_id');

		$object = Objects::findObject($obj_id);

		if (!isset($genid)) {
			$genid = gen_id();
		}
		$subscriberIds = array();
		if ($object->isNew()) {
			$subscriberIds[] = logged_user()->getId();
		} else {
			foreach ($object->getSubscribers() as $u) {
				$subscriberIds[] = $u->getId();
			}
		}
		if($object instanceof TemplateTask){
			$objectTypeId = ProjectTasks::instance()->getObjectTypeId();
		}else{
			$objectTypeId = $object->getObjectTypeId();
		}


		tpl_assign('object', $object);
		tpl_assign('objectTypeId', $objectTypeId);
		tpl_assign('subscriberIds', $subscriberIds);
		tpl_assign('genid', $genid);
	}

	function add_subscribers_from_object_view() {
		ajx_current("empty");
		$objectId = array_var($_GET, 'object_id');
		$object = Objects::findObject($objectId);
		$old_users = $object->getSubscriberIds();
		$this->add_subscribers($object);
		/* Unnecessary addition to the ApplicationLogs
		*
		$users = $object->getSubscriberIds();
		$new = array();
		foreach ($users as $user) {
			if (!in_array($user, $old_users)) {
				$new[] = $user;
			}
		}
		
		if(count($new) > 0){
			ApplicationLogs::createLog($object, ApplicationLogs::ACTION_SUBSCRIBE, false, false, true, implode(",", $new));
		}
		*/
		flash_success(lang('subscription modified successfully'));
	}

	function init_trash() {
		require_javascript("og/TrashCan.js");
		ajx_current("panel", "trashcan", null, null, true);
		ajx_replace(true);
	}

	function init_archivedobjs() {
		require_javascript("og/ArchivedObjects.js");
		ajx_current("panel", "archivedobjects", null, null, true);
		ajx_replace(true);
	}

	function render_add_subscribers() {
		$context = build_context_array(array_var($_GET, 'context', ''));
		$uids = array_var($_GET, 'users', '');
		$genid = array_var($_GET, 'genid', '');
		$otype = array_var($_GET, 'otype', '');
		$assigned_to = array_var($_GET, 'assigned_to', '');
		$subscriberIds = explode(",", $uids);

		// dont allow non numeric parameters for otype and subscriber ids
		$subscriberIds = array_filter($subscriberIds, 'is_numeric');
		if (!is_numeric($otype)) $otype = 0;

		tpl_assign('object_type_id', $otype);
		tpl_assign('assigned_to', $assigned_to);
		tpl_assign('context', $context);
		tpl_assign('subscriberIds', $subscriberIds);
		tpl_assign('genid', $genid);
		$this->setLayout("html");
		$this->setTemplate("add_subscribers");
	}


	function add_to_members($object, $member_ids, $user = null, $check_allowed_members = true, $is_multiple_classification = false, $add_related_mem = true) {
		if (!$user instanceof Contact) $user = logged_user();

		// clean member_ids
		$tmp_mids = array();
		foreach ($member_ids as $mid) {
			if (!is_null($mid) && trim($mid) != "") $tmp_mids[] = $mid;
		}
		$member_ids = $tmp_mids;

		if ($user->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}

		if (isset($_POST['trees_not_loaded']) && $_POST['trees_not_loaded'] > 0) return;

		$required_dimension_ids = array();
		$dimension_object_types = $object->getDimensionObjectTypes();
		foreach($dimension_object_types as $dot){
			if ($dot->getIsRequired()){
				$required_dimension_ids[] = $dot->getDimensionId();
			}
		}		
		$required_dimensions = Dimensions::instance()->findAll(array("conditions" => "id IN (".implode(",",$required_dimension_ids).") OR is_required=1"));

		if (count($member_ids) > 0) {
			$enteredMembers = Members::instance()->findAll(array('conditions' => 'id IN ('.implode(",", $member_ids).')'));
		} else {
			$enteredMembers = array();
		}

		$object_type_id = $object->getObjectTypeId();
		$required_dimensions_ids = DimensionObjectTypeContents::getRequiredDimensions($object_type_id);
		$manageable_members = array();
		foreach ($enteredMembers as $ent_mem) {
			if ($ent_mem->getDimension()->getIsManageable() && ($ent_mem->getDimension()->getDefinesPermissions() || in_array($ent_mem->getDimension()->getId(), $required_dimensions_ids))){
				$manageable_members[] = $ent_mem;
			}
		}

		$notAllowedMember = '';
		if ($check_allowed_members) {
		  if ((!can_add($user, $check_allowed_members ? $object->getAllowedMembersToAdd($user, $manageable_members):$manageable_members, $object->getObjectTypeId(), $notAllowedMember))
			&& !($object instanceof TemplateTask || $object instanceof TemplateMilestone || ($object instanceof Contact && $object->isUser()))) {

				if (str_starts_with($notAllowedMember, '-- req dim --')){
					$msg = lang('must choose at least one member of', str_replace_first('-- req dim --', '', $notAllowedMember, $in));
					throw new Exception($msg);
				} else {
					$mem_names = array();
					$ot_name = $object->getObjectTypeNameLang();
					foreach ($manageable_members as $man_mem) $mem_names[] = $man_mem->getName();
					throw new Exception(lang('you dont have permissions to add this object in members', $ot_name, implode(', ',$mem_names)));
				}
			
			ajx_current("empty");
			return;
		  }
		}

		$continue = true;
		Hook::fire('before_changing_classification', array('object' => $object, 'members' => $enteredMembers), $continue);
		if (!$continue) return;

		$removedMemebersIds = $object->removeFromAllMembers($user, $enteredMembers);
		
		$not_valid_members = array();
		/* @var $object ContentDataObject */
		$validMembers = $check_allowed_members ? $object->getAllowedMembersToAdd($user, $enteredMembers, $not_valid_members) : $enteredMembers;

		
		foreach($required_dimensions as $rdim){		    
			$exists = false;
			foreach ($validMembers as $m){
				if ($m->getDimensionId() == $rdim->getId()) {
					$exists = true;
					break;
				}
			}
			if (!$exists && !($object instanceof TemplateTask || $object instanceof TemplateMilestone || ($object instanceof Contact && $object->isUser()))){
				throw new Exception(lang('must choose at least one member of',$rdim->getName()));
			}
		}
		
		// hook to add more validations before classifying an object
		
		$continue = true;
		Hook::fire('before_add_to_members', array('object' => $object, 'members' => $validMembers), $continue);
		
		if (!$continue) return;
		
		// add object to members selected in form
		$object->addToMembers($validMembers, true, $is_multiple_classification);
		
		// add object to related members
		if($add_related_mem){
			$object->addToRelatedMembers($validMembers, true);
		}
		
		Hook::fire ('after_add_to_members', $object, $validMembers);
		
		Hook::fire ('after_remove_members_from_object', $object, $removedMemebersIds);
		
		$save_sharing_table = true;
		// performance issue hack -----------------
		// don't add to sharing table if object is an user and is classified in more than 1000 members
		if ($object instanceof Contact && $object->getUserType() > 0) {
			$object_members_count = ObjectMembers::instance()->count("is_optimization=0 AND object_id=".$object->getId());
			$save_sharing_table = $object_members_count < 1000;
		}
		// end performance issue hack -------------
		
		if ($save_sharing_table) {
			//add_object_to_sharing_table($object, logged_user()); // do it in background
			$object->addToSharingTable();
		}
		
		//add to the object instance the members only if members value of the object is not null
		//because in that case when we ask for the members of the object we load them from db
		if ( !is_null($object->members) ) {
			$object->members = $validMembers;
		}

		// show the user the members where the object could not be classifed
		if (count($not_valid_members) > 0) {
			$not_valid_mem_names_array = array();
			$person_dim = Dimensions::findByCode('feng_persons');
			foreach ($not_valid_members as $m) {
				if ($person_dim->getId() != $m->getDimensionId()) $not_valid_mem_names_array[] = $m->getName();
			}
			if (count($not_valid_mem_names_array) > 0) {
				$not_valid_mem_names = implode(', ', $not_valid_mem_names_array);

				$ot = ObjectTypes::instance()->findById($object->getObjectTypeId());
				$ot_name = $ot instanceof ObjectType ? $ot->getPluralObjectTypeName() : '';
				
				evt_add("popup", array(
					'title' => lang("information"),
					'message' => lang('object could not be classfied in due to permissions', lang('the '.$object->getObjectTypeName()), $not_valid_mem_names, strtolower($ot_name)),
				));
			}
		}
		

		return $validMembers;
	}


	function tmp_file_upload() {
		ajx_current("empty");
		$input_name = array_var($_GET, 'input_name', 'file_data');

		$uploaded_file = array_var($_FILES, $input_name);

		$id = gen_id() . "_tmp_uploaded_file";
		$fname = ROOT . "/tmp/$id";

		if (!empty($uploaded_file['tmp_name'])) {
			// copy to tmp dir
			copy($uploaded_file['tmp_name'], $fname);

			// put file properties in session
			$_SESSION[$id] = array(
				'name' => $uploaded_file['name'],
				'size' => $uploaded_file['size'],
				'type' => $uploaded_file['type'],
				'tmp_name' => $fname,
				'error' => $uploaded_file['error']
			);
			// return some file info
			ajx_extra_data(array('url' => ROOT_URL . "/tmp/$id", 'id' => $id));
		}
	}

	/**
	 * Add a single custom property of an object into the database.
	 *
	 * @param ContentDataObject $object
	 * @param integer $cp_id the Custom Property ID
	 * @param $cp_value string This will be of different type, d
	 * 
	 */
	function add_custom_property($object_original, $cp_id, $cp_value) {
	    $cp_data = array(
	        $cp_id => $cp_value,
	    );
	    $this->add_custom_properties($object_original, $cp_data);
	}
	
	
	/**
	 * Check if there are visible custom property inputs with errors on the form
	 *
	 * @return array|bool
	 */
	function hasVisibleCpErrors() {

		$cp_error_ids = array_var($_REQUEST, 'error_ids', []); // Get error IDs from request
		$cp_inputs_with_errors = CustomProperties::instance()->get_cp_names_from_cp_inputsId_on_forms($cp_error_ids);
		return !empty($cp_inputs_with_errors) ? $cp_inputs_with_errors : false;
	}
	/**
	 * Adds the custom properties of an object into the database.
	 *
	 * @param $object
	 * 
	 */
	function add_custom_properties($object_original, $cp_data=null) {

		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object = $object_original;


		if ($visible_cp_errors = $this->hasVisibleCpErrors()) {
			$visible_cp_errors = implode(', ', $visible_cp_errors);
			flash_error(lang('wrong_cp_values', $visible_cp_errors));
			ajx_current('empty'); // Detener la acción si hay errores
			throw new Exception(lang('wrong_cp_values', $visible_cp_errors));
			return;
		}

		if (!is_null($cp_data)) {
			$obj_custom_properties = $cp_data;
		} else {
			$obj_custom_properties = array_var($_POST, 'object_custom_properties');
		}

		$time_cp_values = array_var($obj_custom_properties, "time");

		if (is_array($obj_custom_properties)) {
			foreach ($obj_custom_properties as $id => &$val) {
				$val = remove_scripts($val);
			}
		}

		$date_format = user_config_option('date_format');
		$date_format_tip = date_format_tip($date_format);

		$required_custom_props = array();
		$object_type_id = $object instanceof TemplateTask ? ProjectTasks::instance()->getObjectTypeId() : $object->getObjectTypeId();

		$extra_conditions = "";
		Hook::fire('object_form_custom_prop_extra_conditions', array('ot_id' => $object->getObjectTypeId(), 'object' => $object), $extra_conditions);

		$customProps = CustomProperties::getAllCustomPropertiesByObjectType($object_type_id, 'all', $extra_conditions, true, null, $object);

		//Sets all boolean custom properties to 0. If any boolean properties are returned, they are subsequently set to 1.
		foreach($customProps as $cp){
			if($cp->getType() == 'boolean'){
				$custom_property_value = CustomPropertyValues::getCustomPropertyValue($object->getId(), $cp->getId());
				if(!$custom_property_value instanceof CustomPropertyValue){
					$custom_property_value = new CustomPropertyValue();
				}
				$custom_property_value->setObjectId($object->getId());
				$custom_property_value->setCustomPropertyId($cp->getId());
				$custom_property_value->setValue(0);
				$custom_property_value->save();
			}
			if ($cp->getIsRequired()) {
				$required_custom_props[] = $cp;
			}
		}

		$check_required_cps_disabled = array_var($_SESSION, 'dont_check_required_cps');
		if (!$check_required_cps_disabled) {
			foreach ($required_custom_props as $req_cp) {/* @var $req_cp CustomProperty */
				$not_set = false;
				if ($req_cp->getIsMultipleValues()) {
					
					if (($req_cp->getType() == 'user' || $req_cp->getType() == 'contact')) {
						// remove anything besides numbers, we are looking for contact ids
						$obj_custom_properties[$req_cp->getId()] = array_filter(array_var($obj_custom_properties, $req_cp->getId(), array()), "is_numeric");
					}
					
					$not_set = !isset($obj_custom_properties[$req_cp->getId()]) || count(array_filter($obj_custom_properties[$req_cp->getId()])) == 0;
					
				} else {
					if ($req_cp->getType() == 'address') {
						
						$not_set = !isset($obj_custom_properties[$req_cp->getId()]) || count($obj_custom_properties[$req_cp->getId()]) == 0;
					
					} else if ($req_cp->getType() == 'amount') {

						$amount = clean_formatted_money_amount_for_sql($obj_custom_properties[$req_cp->getId()]['amount']);
						$not_set = $amount == 0;

					} else {
						if ($req_cp->getType() == 'date') {
							
							if (array_var($obj_custom_properties, $req_cp->getId()) == $date_format_tip) {
								$obj_custom_properties[$req_cp->getId()] = "";
							}
							
						} else if ($req_cp->getType() == 'user' || $req_cp->getType() == 'contact') {
							$cp_val_contact_id = array_var($obj_custom_properties, $req_cp->getId());
							if (!is_numeric($cp_val_contact_id) || $cp_val_contact_id == 0) {
								$obj_custom_properties[$req_cp->getId()] = "";
							}
						}
						$not_set = !isset($obj_custom_properties[$req_cp->getId()]) || trim($obj_custom_properties[$req_cp->getId()]) == "";
					}
				}
				if ($not_set) {
					throw new Exception(lang('custom property value required', $req_cp->getName()));
				}
			}
		}

		if (is_array($obj_custom_properties)){
			// check required custom properties
			foreach($obj_custom_properties as $id => $value){
				if (!is_numeric($id)) continue;

				//Get the custom property
				$custom_property = null;
				foreach ($customProps as $cp){
					if ($cp->getId() == $id){
						$custom_property = $cp;
						break;
					}
				}

				$object = $object_original;
				// if custom property does not belong to the object, look for an associated object for current cp
				if (is_null($custom_property)) {
					$custom_property = CustomProperties::instance()->findById($id);
					$object = $object_original->getAdditionalCustomPropertyAssociatedObject($custom_property);

					if (!$custom_property instanceof CustomProperty || !$object instanceof ContentDataObject) {
						$object = $object_original;
						continue;
					}
				}

				if ($custom_property instanceof CustomProperty){
					// save dates in standard format "Y-m-d H:i:s", because the column type is string
					if ($custom_property->getType() == 'date' || $custom_property->getType() == 'datetime') {
						$d_format = $custom_property->getType() == 'datetime' ? "Y-m-d H:i:s" : "Y-m-d";
						if(is_array($value)){
							$newValues = array();
							foreach ($value as $idx => $val) {
								$val = str_replace($date_format_tip, "", $val);
								if (trim($val) != '' && trim($val) != $date_format_tip ) {
									$dtv = DateTimeValueLib::dateFromFormatAndString($date_format, $val);
									if ($custom_property->getType() == 'datetime') {
										$time_val = getTimeValue($time_cp_values[$id][$idx]);
										if ($time_val) {
											$dtv->setHour(array_var($time_val, 'hours', 0));
											$dtv->setMinute(array_var($time_val, 'mins', 0));
										}
									}
									$newValues[] = $dtv->format($d_format);
								}
							}
							$value = $newValues;
						} else {
							$value = str_replace($date_format_tip, "", $value);
							if (trim($value) != '' && trim($value) != $date_format_tip) {

								$dtv = DateTimeValueLib::dateFromFormatAndString($date_format, $value);
								if ($custom_property->getType() == 'datetime') {
									$time_val = getTimeValue($time_cp_values[$id]);
									if ($time_val) {
										$dtv->setHour(array_var($time_val, 'hours', 0));
										$dtv->setMinute(array_var($time_val, 'mins', 0));
									}
								}
								$value = $dtv->format($d_format);
							} else {
								$value = '';
							}
						}
					}

					foreach (array_var($_REQUEST, 'remove_custom_properties',array()) as $cpropid => $remove) {
						if ($remove) {
							CustomPropertyValues::deleteCustomPropertyValues($object->getId(), $cpropid);
						}
					}

					Hook::fire('before_save_custom_property_value', array('object' => $object, 'custom_prop' => $custom_property), $value);

					if(is_array($value)){
						if ($custom_property->getType() == 'address') {
							if ($custom_property->getIsRequired()) {
								if (array_var($value, 'street') == '' && array_var($value, 'city') == '' && array_var($value, 'state') == '' && array_var($value, 'country') == '' && array_var($value, 'zip_code') == '') {
									throw new Exception(lang('custom property value required', $custom_property->getName()));
								}
								$errors = array(lang('error form validation'));
								Env::useHelper('form');
								$ok = checkAddressInputMandatoryFields($value, $custom_property->getName(), $errors);
								if (!$ok) {
									throw new Exception(implode("\n - ", $errors));
								}
							}
							// Address custom property
							$address_val = array_var($value, 'type') .'|'. array_var($value, 'street') .'|'. array_var($value, 'city') .'|'. array_var($value, 'state') .'|'. array_var($value, 'country') .'|'. array_var($value, 'zip_code');
							CustomPropertyValues::deleteCustomPropertyValues($object->getId(), $id);
							$custom_property_value = new CustomPropertyValue();
							$custom_property_value->setObjectId($object->getId());
							$custom_property_value->setCustomPropertyId($id);
							$custom_property_value->setValue($address_val);
							$custom_property_value->save();

						} else if ($custom_property->getType() == 'amount') {

							CustomPropertyValues::deleteCustomPropertyValues($object->getId(), $id);
				
							$custom_property_value = new CustomPropertyValue();
							$custom_property_value->setObjectId($object->getId());
							$custom_property_value->setCustomPropertyId($id);
							$custom_property_value->setValue(clean_formatted_money_amount_for_sql($value['amount']));
							$custom_property_value->setCurrencyId($value['currency_id']);
							$custom_property_value->save();
							
						} else if ($custom_property->getType() == 'list') {
							CustomPropertyValues::deleteCustomPropertyValues($object->getId(), $id);
							foreach($value as $list_key => $list_val){
								if($list_val){
									$custom_property_value = new CustomPropertyValue();
									$custom_property_value->setObjectId($object->getId());
									$custom_property_value->setCustomPropertyId($id);
									$custom_property_value->setValue($list_key);
									$custom_property_value->save();
								}
							}

						} else if ($custom_property->getType() == 'contact') {
          
                            CustomPropertyValues::deleteCustomPropertyValues($object->getId(), $id);
                            foreach($value as $list_key => $list_val){
                                if($list_val){
                                    $custom_property_value = new CustomPropertyValue();
                                    $custom_property_value->setObjectId($object->getId());
                                    $custom_property_value->setCustomPropertyId($id);
                                    $custom_property_value->setValue($list_val);
                                    $custom_property_value->save();
                                    $contact = Contacts::instance()->findById($list_val);
                                    $member = Members::findOneByObjectId($object->getObjectId());
                                    if($member instanceof Member && $contact instanceof Contact) {
                                        $object_controller = new ObjectController();
                                        $object_controller->add_to_members($contact, array($member->getId()),null,false);
                                    }
                                }
                            }
                        
                        } else {
							//Save multiple values
							CustomPropertyValues::deleteCustomPropertyValues($object->getId(), $id);
							foreach($value as &$val){
								if (is_array($val) && $custom_property->getType() == 'table') {
									// CP type == table
									$str_val = '';
									foreach ($val as $col_val) {
										$col_val = str_replace("|", "\|", $col_val);
										$str_val .= ($str_val == '' ? '' : '|') . $col_val;
									}
									$val = $str_val;
								}
								if($val != ''){
									$custom_property_value = new CustomPropertyValue();
									$custom_property_value->setObjectId($object->getId());
									$custom_property_value->setCustomPropertyId($id);
									$custom_property_value->setValue($val);
									$custom_property_value->save();
								}
							}
						}

					} else if ($custom_property->getType() == 'contact') {
                        CustomPropertyValues::deleteCustomPropertyValues($object->getId(), $id);
                        if (trim($value) != "") {
                            $custom_property_value = new CustomPropertyValue();
                            $custom_property_value->setObjectId($object->getId());
                            $custom_property_value->setCustomPropertyId($id);
                            $custom_property_value->setValue($value);
                            $custom_property_value->save();

                            $contact = Contacts::instance()->findById($value);
                            $member = Members::findOneByObjectId($object->getObjectId());
							
							if($member instanceof Member && $contact instanceof Contact && !$contact->isUser()) {
                                
								$object_controller = new ObjectController();																
								$new_member_id = $member->getId();																
								$mems_arr = array($new_member_id);								
								$old_mems = $contact->getMembers();
								
								foreach($old_mems as $om) {
									array_push($mems_arr, $om->getId());
								}																

                                $object_controller->add_to_members($contact, $mems_arr,null,false, false, false);
                            }                            
                        }
                        
                    } else if($custom_property->getType() == 'image') {

						if (trim($value) != "") {
							if (file_exists(ROOT."/tmp/$value")) {
								$info = array_var($_SESSION, $value);
								$type = array_var($info, "type");

								$repo_id = FileRepository::addFile(ROOT."/tmp/$value", array('type' => $type, 'public' => true));

								$object_to_save = array(
									'repository_id' => $repo_id,
									'size' => array_var($info, "size"),
									'type' => $type,
								);

								$cpv = CustomPropertyValues::getCustomPropertyValue($object->getId(), $id);
								if (!$cpv instanceof CustomPropertyValue) {
									$cpv = new CustomPropertyValue();
									$cpv->setObjectId($object->getId());
									$cpv->setCustomPropertyId($id);
								}
								$cpv->setValue(json_encode($object_to_save));
								$cpv->save();
							}

						} else {
							CustomPropertyValues::deleteCustomPropertyValues($object->getId(), $id);
						}

					}else{
					    if($custom_property->getType() == 'boolean'){
						    $value = in_array($value, array(0, '')) ? false : $value;
						}
						
						$cpv = CustomPropertyValues::getCustomPropertyValue($object->getId(), $id);
						if ($cpv instanceof CustomPropertyValue) {
							DB::execute("
								UPDATE ".TABLE_PREFIX."custom_property_values 
								SET `value`=".DB::escape($value)."
								WHERE `id` = ".$cpv->getId().";
							");
						} else {
							DB::execute("
								INSERT INTO ".TABLE_PREFIX."custom_property_values (`object_id`, `custom_property_id`, `value`) VALUES
								(".$object->getId().", $id, ".DB::escape($value).")
								ON DUPLICATE KEY UPDATE `value`=".DB::escape($value).";
							");
						}
					}

					$not_searchable_types = CustomProperties::instance()->getNonSearchableColumnTypes();

					//Add to searchable objects
					if ($object->isSearchable() && !in_array($custom_property->getType(), $not_searchable_types)){

						$name = str_replace("'", "\'", $custom_property->getName());
						if (is_array($value)) {
							$value = implode(', ', $value);
						}
						
						// this function escapes special characters and adds the string quotes for the query
						$value = DB::escape($value);

						$sql = "INSERT INTO ".TABLE_PREFIX."searchable_objects (rel_object_id, column_name, content)
						VALUES ('".$object->getId()."', '".$name."', ".$value.")
						ON DUPLICATE KEY UPDATE content=".$value."";

						DB::execute($sql);
					}
				}
			}
		}

		//Save the key - value pair custom properties (object_properties table)
		$object->clearObjectProperties();
		$names = array_var($_POST, 'custom_prop_names');
		$values = array_var($_POST, 'custom_prop_values');
		if (!is_array($names)) return;
		for ($i=0; $i < count($names); $i++) {
			$name = trim($names[$i]);
			$value = trim($values[$i]);
			if ($name != '' && $value != '') {
				$property = new ObjectProperty();
				$property->setObject($object);
				$property->setPropertyName($name);
				$property->setPropertyValue($value);
				$property->save();
				if ($object->isSearchable()) {
					$object->addPropertyToSearchableObject($property);
				}
			}
		}

	}

	function add_reminders($object) {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object->clearReminders(logged_user(), true);
		$typesC = array_var($_POST, 'reminder_type');
		if (!is_array($typesC)) return;

		$durationsC = array_var($_POST, 'reminder_duration');
		$duration_typesC = array_var($_POST, 'reminder_duration_type');
		$subscribersC = array_var($_POST, 'reminder_subscribers');
		$contextC = array_var($_POST, 'reminder_context'); 

		foreach ($typesC as $context => $types) {
			$durations = $durationsC[$context];
			$duration_types = $duration_typesC[$context];
			$subscribers = $subscribersC[$context];
			$context_row = $contextC[$context]; 

			for ($i=0; $i < count($types); $i++) {
				$type = $types[$i];
				$duration = $durations[$i];
				$duration_type = $duration_types[$i];
				$minutes = $duration * $duration_type;

				Hook::fire('validate_reminder_minutes', array('context' => $context, 'i' => $i, 'request'=>$_POST), $minutes);

				$reminder = new ObjectReminder();
				$reminder->setMinutesBefore($minutes);
				$reminder->setType($type);
				$reminder->setContext($context_row[$i]);
				$reminder->setObject($object);
				if (isset($subscribers[$i]) && $subscribers[$i]) {
					$reminder->setUserId(0);
				} else {
					$reminder->setUser(logged_user());
				}
				$date = $object->getColumnValue($context);
				if ($date instanceof DateTimeValue) {
					$rdate = new DateTimeValue($date->getTimestamp() - $minutes * 60);
					$reminder->setDate($rdate);
				}
				$reminder->save();
			}
		}
	}

	function update_reminders($object, $reminders) {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		if($object->getObjectTypeName() == "task"){
			$new_date = $object->getDueDate();
		}else if($object->getObjectTypeName() == "event"){
			$new_date = $object->getStart();
		}
		if (isset($new_date)) {
			foreach($reminders as $reminder){
				$reminder->setDate($new_date);
				$reminder->save();
			}
		}
	}


	// ---------------------------------------------------
	//  Link / Unlink
	// ---------------------------------------------------

	function redraw_linked_object_list() {
		$object = Objects::findObject(array_var($_GET, 'id'));
		if (!$object) {
			ajx_current("empty");
			return;
		}

		tpl_assign('linked_objects_object', $object);
		tpl_assign('shortDisplay', false);
		tpl_assign('enableAdding', true);
		tpl_assign('linked_objects', $object->getLinkedObjects());
		$this->setLayout("html");
		$this->setTemplate("list_linked_objects");
	}

	function link_object() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		ajx_current("empty");
		$object_id = get_id('object_id');

		$object = Objects::findObject($object_id);
		if(!($object instanceof ApplicationDataObject)) {
			flash_error(lang('no access permissions'));
			return;
		} // if
		if(!($object->canLinkObject(logged_user()))){
			flash_error(lang('no access permissions'));
			return;
		} // if
		$str_obj = array_var($_GET, 'objects');
		if ($str_obj == null) return;
		try {
			$err_message_list = '';
			DB::beginWork();
			$split = explode(",", $str_obj);
			$succ = 0; $err = 0; $permission_err = false; $object_dnx_err = false;
			foreach ($split as $objid) {
				if ($objid == $object_id){
					$err++;
					$err_message_list .= ' - ' . lang('error cannot link object to self') . "\n";
					continue;
				}
				$rel_object = Objects::findObject($objid);
				if (!($rel_object instanceof ApplicationDataObject)) {
					$err++;
					if (!$object_dnx_err)
						$err_message_list .= ' - ' . lang('object dnx') . "\n";
					$object_dnx_err = true;
					continue;
				} // if
				if (!($rel_object->canLinkObject(logged_user()))) {
					$err++;
					if (!$permission_err)
						$err_message_list .= ' - ' . lang('no access permissions') . "\n";
					$permission_err = true;
					continue;
				} // if
				try {
					$object->linkObject($rel_object);
					if (config_option('updateOnLinkedObjects')){
						$object->save();
						$rel_object->save();
					}
					if ($object instanceof ContentDataObject) {
						ApplicationLogs::createLog($object, ApplicationLogs::ACTION_LINK, false, null, true, $objid);
					}
					if ($rel_object instanceof ContentDataObject) {
						ApplicationLogs::createLog($rel_object, ApplicationLogs::ACTION_LINK, false, null, true, $object->getId());
					}
					$succ++;
				} catch(Exception $e){
					$err++;
				}
			}
			DB::commit();
			$message = "";
			if ($err > 0) {
				$message .= lang("error link object", $err) . "\n" . $err_message_list;
			}
			if ($succ > 0) {
				$message .= lang("success link objects", $succ) . "\n";
			}
			if ($succ == 0 && $err > 0) {
				flash_error($message);
				ajx_current("empty");
			} else if ($succ > 0) {
				flash_success($message);
				if (array_var($_GET, 'reload')) {
					ajx_current("reload");
				}
			}
		} catch (Exception $e) {
			DB::rollback();
			flash_error($e->getMessage());
			ajx_current("empty");
		}
	}

	/**
	 * Function called from other controllers when creating a new object an linking objects to it
	 *
	 * @param void
	 * @return null
	 */
	function link_to_new_object($the_object, $check_permissions=true){
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}

		$objects = array_var($_POST, 'linked_objects');
		
		$can_link_object = !$check_permissions || $the_object->canLinkObject(logged_user());

		if (is_array($objects) && count($objects) > 0 && !$the_object->isNew() && !$can_link_object) {
			flash_error(lang("user cannot link objects"));
			return;
		}

		$the_object->clearLinkedObjects();
		if (is_array($objects)) {
			$err = 0;
			foreach ($objects as $objid) {
				$split = explode(":", $objid);
				if ($split[0] == $the_object->getId()) continue;
				if(count($split) == 1){
					$object = Objects::findObject($split[0]);
				}else if (count($split) == 3 && $split[2] == 'isName'){
					$object = ProjectFiles::getByFilename($split[1]);
				} else continue;

				if (!$check_permissions || $object->canLinkObject(logged_user())) {
					$the_object->linkObject($object);
					if ($the_object instanceof ContentDataObject)
						ApplicationLogs::createLog($the_object, ApplicationLogs::ACTION_LINK,false,null,true, $object->getId());
					if ($object instanceof ContentDataObject)
						ApplicationLogs::createLog($object, ApplicationLogs::ACTION_LINK,false,null,true, $the_object->getId());
				} else {
					$err++;
				}
			}
			if ($err > 0) {
				flash_error(lang('some objects could not be linked', $err));
			}
		}
	}

	/**
	 * Unlink object from related object
	 *
	 * @param void
	 * @return null
	 */
	function unlink_from_object() { // ex detach_from_object() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object_id = get_id('object_id');
		$object1 = Objects::findObject($object_id);

		$dont_reload = array_var($_GET, 'dont_reload');
		if (array_var($_GET, 'rel_objects')) {
			$objects_to_unlink = explode(",", array_var($_GET, 'rel_objects'));
		} else {
			$objects_to_unlink = array(get_id('rel_object_id'));
		}
		try {
			DB::beginWork();
			$err = 0; $succ = 0;
			foreach ($objects_to_unlink as $rel_object_id) {

				$object2 = Objects::findObject($rel_object_id);
				if(!($object1 instanceof ApplicationDataObject)|| !($object2 instanceof ApplicationDataObject)) {
					flash_error(lang('object not found'));
					ajx_current("empty");
					return;
				} // if

				$linked_object = LinkedObjects::instance()->findById(array(
					'rel_object_id' => $object_id,
					'object_id' => $rel_object_id,
				)); // findById
				if(!($linked_object instanceof LinkedObject ))
				{ //search for reverse link
					$linked_object = LinkedObjects::instance()->findById(array(
						'rel_object_id' => $rel_object_id,
						'object_id' => $object_id,
					)); // findById
				}

				if(!($linked_object instanceof LinkedObject )) {
					$err++;
					continue;
				} // if

				$linked_object->delete();
				if (config_option('updateOnLinkedObjects')){
					$object1->save();
					$object2->save();
				}
				if ($object1 instanceof ContentDataObject)
					ApplicationLogs::createLog($object1, ApplicationLogs::ACTION_UNLINK, false, null, true, $object2->getId());
				if ($object2 instanceof ContentDataObject)
					ApplicationLogs::createLog($object2, ApplicationLogs::ACTION_UNLINK, false, null, true, $object1->getId());

				$succ++;
			}
			DB::commit();
			$message = "";
			if ($err > 0) {
				$message .= lang("error unlink object", $err) . "\n";
			}
			if ($succ > 0) {
				$message .= lang("success unlink object", $succ) . "\n";
			}
			if ($succ == 0 && $err > 0) {
				flash_error($message);
			} else if ($succ > 0) {
				flash_success($message);
			}

			flash_success(lang('success unlink object'));

			if ($dont_reload) ajx_current("empty");
			else ajx_current("reload");
		} catch(Exception $e) {
			flash_error(lang('error unlink object'));
			DB::rollback();
			ajx_current("empty");
		} // try
	} // unlink_from_object


	/**
	 * Show property list
	 *
	 * @param
	 * @return ObjectProperties
	 */
	function view_properties()
	{
		$manager_class = array_var($_GET, 'manager');
		$object_id = get_id('object_id');
		$obj = Objects::findObject ($object_id);

		if (!($obj instanceof ContentDataObject ))
		{
			flash_error(lang('object dnx'));
			ajx_current("empty");
			return;
		}
		$properties = ObjectProperties::getAllPropertiesByObject($obj);
		if(!($properties instanceof ObjectProperties ))
		{
			flash_error(lang('properties dnx'));
			ajx_current("empty");
			return;
		}
		tpl_assign('properties', $properties);
	} // view_properties

	function show_all_linked_objects() {

		require_javascript("og/LinkedObjectsManager.js");
		ajx_current("panel", "linkedobject", null, array(
			'linked_object' => array_var($_GET, 'linked_object'),
			'linked_object_name' => array_var($_GET, 'linked_object_name'),
			'linked_object_ico' => array_var($_GET, 'linked_object_ico'),
		));
		ajx_replace(true);
	}

	/**
	 * Update, delete and add new properties
	 *
	 * @access public
	 * @param void
	 * @return null
	 */
	function update_properties() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$this->setTemplate('add_properties');

		$manager_class = array_var($_GET, 'manager');
		$object_id = get_id('object_id');
		$obj = Objects::findObject ($object_id);
		if(!($obj instanceof ContentDataObject )) {
			flash_error(lang('object dnx'));
			ajx_current("empty");
			return;
		} // if

		if(! logged_user()->getCanManageProperties()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		} // if

		$new_properties = array_var($_POST, 'new_properties');
		$update_properties = array_var($_POST, 'update_properties');
		$delete_properties = array_var($_POST, 'delete_properties');
		if(is_array(array_var($_POST, 'new_properties')) || is_array(array_var($_POST, 'update_properties'))) {

			try {
				DB::beginWork();
				//add new properties
				foreach ($new_properties as $prop) {
					$property = new ObjectProperty();
					$property->setFromAttributes($prop);
					$property->setRelObjectId($object_id);
					$property->save();
				}
				foreach ($update_properties as $prop) {
					$property = ObjectProperties::getProperty(array_var($prop,'id')); //ObjectProperties::getPropertyByName($obj, array_var($prop,'name'));
					$property->setPropertyValue(array_var($prop,'value'));
					$property->save();
				}
				foreach ($delete_properties as $prop)
				{
					$property = ObjectProperties::getProperty(array_var($prop,'id')); //ObjectProperties::getPropertyByName($obj, array_var($prop,'name'));
					$prop->delete();
				}
				tpl_assign('properties',ObjectProperties::getAllPropertiesByObject($obj));

				DB::commit();
				ApplicationLogs::createLog($obj, ApplicationLogs::ACTION_EDIT);

				flash_success(lang('success add properties'));
				$this->redirectToReferer($obj->getObjectUrl());
			} catch(Exception $e) {
				DB::rollback();
				flash_error($e->getMessage());
				ajx_current("empty");
			} //
		} // if
	} // update_properties

	function mark_as_read() {
		ajx_current('empty');
		$csvids = array_var($_GET, 'ids');
		$ids = explode(",", $csvids);
		$this->do_mark_as_read_unread_objects($ids, true);
	}

	function mark_as_unread() {
		ajx_current('empty');
		$csvids = array_var($_GET, 'ids');
		$ids = explode(",", $csvids);
		$this->do_mark_as_read_unread_objects($ids, false);
	}

	static function reloadPersonsDimension() {
		if (Plugins::instance()->isActivePlugin('core_dimensions')) {
			$person_dim = Dimensions::findByCode('feng_persons');
			if ($person_dim instanceof Dimension) {
				evt_add('reload dimension tree', $person_dim->getId());
			}
		}
	}


	function view(){
		$id = array_var($_GET,'id');
		$obj = Objects::findObject($id);

		if(!$obj){
			$obj = Members::getMemberById($id);
		}

		if(!($obj instanceof DataObject )) {
			flash_error(lang('object dnx'));
			ajx_current("empty");
			return;
		} // if

		if(! $obj->canView( logged_user())) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		} // if

		$object_type = ObjectTypes::instance()->findById($obj->getObjectTypeId());
		if($object_type->getType() == 'dimension_object'){
			ajx_current("empty");
		}elseif($object_type->getType() == 'dimension_group'){
			ajx_current("empty");
		}else{
			redirect_to($obj->getObjectUrl(),true);
		}
	}

	function do_delete_objects($objects, $permanent = false, &$deleted_object_ids, $raw_data=false, $check_permissions=true) { 
		$err = 0; // count errors
		$succ = 0; // count files deleted
		foreach ($objects as $object) {
			try {
				$obj = Objects::findObject($raw_data ? $object['id'] : $object->getId());
				// do not delete users from here
				if ($obj instanceof Contact && $obj->isUser()) continue;
				$allowed_to_delete = $check_permissions ? $obj->canDelete(logged_user()) : true;
				
				if ($obj instanceof ContentDataObject && $allowed_to_delete) {
					if ($permanent) {
						if (Plugins::instance()->isActivePlugin('mail') && $obj instanceof MailContent) {
							$obj->delete(false);
						} elseif (Plugins::instance()->isActivePlugin('income') && $obj instanceof IncomeInvoice) {
							$obj->delete(false);
						} else {
							$obj->delete();
						}
						$deleted_object_ids[] = $obj->getId();
						ApplicationLogs::createLog($obj, ApplicationLogs::ACTION_DELETE);
						$succ++;
					} else if ($obj->isTrashable()) {
						$obj->trash();
						ApplicationLogs::createLog($obj, ApplicationLogs::ACTION_TRASH);
						$succ++;
					}
				}
			} catch(Exception $e) {
				$err ++;
			}
		}
		return array($succ, $err);
	}

	function do_archive_unarchive_objects($ids, $action='archive') {
		$err = 0; // count errors
		$succ = 0;
		foreach ($ids as $id) {
			try {
				if (trim($id)!=''){
					$obj = Objects::findObject($id);
					if (!$obj instanceof ApplicationDataObject) {
						continue;
					}
					if ($obj->canEdit(logged_user())) {
						if (method_exists($obj, 'setDontMakeCalculations')) $obj->setDontMakeCalculations(true);
						if ($action == 'archive') {
							$obj->archive();
							$succ++;
							ApplicationLogs::createLog($obj, ApplicationLogs::ACTION_ARCHIVE);
						} else if ($action == 'unarchive') {
							$obj->unarchive();
							$succ++;
							ApplicationLogs::createLog($obj, ApplicationLogs::ACTION_UNARCHIVE);
						}
					} else {
						$err ++;
					}
				}
			} catch(Exception $e) {
				$err ++;
			} // try
		}
		return array($succ, $err);
	}

	/**
	 * Unarchive multiple members
	 * @param string|array $ids Member IDs to unarchive
	 * @return array [success_count, error_count]
	 */
	function do_unarchive_members($ids) {
		if (is_string($ids)) $ids = explode(',', $ids);
		
		// use the member controller to unarchive multiple members
		$member_controller = new MemberController();
		list($succ, $err) = $member_controller->unarchive_multiple($ids, logged_user());
		
		return array($succ, $err);
	}

	function do_mark_as_read_unread_objects($ids, $read) {
		$err = 0; // count errors
		$succ = 0; // count updated objects
		$ids_to_mark = array();

		foreach ($ids as $id) {
			try {
				$obj = Objects::findObject($id);

				if ($obj instanceof ContentDataObject && logged_user() instanceof Contact) {
					$ret = $obj->setIsRead(logged_user()->getId(), $read);
					if($ret){
						$ids_to_mark[] = $id;
					}
				}
				$succ++;
			} catch(Exception $e) {
				$err ++;
			} // try
		}

		Hook::fire('do_mark_as_read_unread_objects', $ids_to_mark, $read);
		return array($succ, $err);
	}

	function move() {
		//	TODO implement again this function
	}

	function view_history(){
		$id = array_var($_GET,'id');
		$obj = Objects::findObject($id);

		$page_size = 20;
		$limit = $page_size;

		// get submitted modification logs page
		$mod_page_submitted = array_var($_REQUEST, 'mod_page');
		$mod_page = $mod_page_submitted ? $mod_page_submitted : 1;
		$mod_offset = $page_size * ($mod_page - 1);

		// get submitted read logs page
		$view_page_submitted = array_var($_REQUEST, 'view_page');
		$view_page = $view_page_submitted ? $view_page_submitted : 1;
		$view_offset = $page_size * ($view_page - 1);

		$isUser = $obj instanceof Contact && $obj->isUser() ? true : false;
		if(!($obj instanceof ApplicationDataObject )) {
			flash_error(lang('object dnx'));
			ajx_current("empty");
			return;
		} // if
		if($isUser && (logged_user()->getId() != $id && logged_user()->getUserType() > $obj->getUserType())) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		} // if
		if(!$isUser && !$obj->canView(logged_user())){
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}

		// if logged user is guest don't show other users logs
		$extra_conditions = "";
		if (logged_user()->isGuest()) {
			$extra_conditions = " AND `created_by_id` = ".logged_user()->getId();
		}

		$logs = ApplicationLogs::getObjectLogs($obj, false, true, $limit, $mod_offset, $extra_conditions);
		$logs_read = ApplicationReadLogs::getObjectLogs($obj, $limit, $view_offset, $extra_conditions);

		// build modification logs pagination object
		$total_logs = ApplicationLogs::countObjectLogs($obj, false, true, $extra_conditions);
		$mod_logs_pagination = array(
			'total_pages' => ceil($total_logs / $page_size),
			'current_page' => $mod_page
		);

		// build read logs pagination object
		$total_read_logs = ApplicationReadLogs::countObjectLogs($obj, $extra_conditions);
		$view_logs_pagination = array(
			'total_pages' => ceil($total_read_logs / $page_size),
			'current_page' => $view_page
		);

		if ($mod_page_submitted || $view_page_submitted) {
			ajx_replace(true);
		}

		tpl_assign('object',$obj);
		tpl_assign('logs',$logs);
		tpl_assign('logs_read',$logs_read);

		tpl_assign('mod_logs_pagination', $mod_logs_pagination);
		tpl_assign('view_logs_pagination', $view_logs_pagination);

		$more_view_history_tabs = array();
		Hook::fire('more_view_history_tabs', array('object' => $obj, 'logs' => $logs, 'logs_read' => $logs_read), $more_view_history_tabs);
		tpl_assign('more_view_history_tabs', $more_view_history_tabs);

		tpl_assign('curtab', array_var($_REQUEST, 'curtab', ''));
	}

	// ---------------------------------------------------
	//  Subscriptions
	// ---------------------------------------------------

	/**
	 * Subscribe to object
	 *
	 * @param void
	 * @return null
	 */
	function subscribe() {
		ajx_current("reload");

		$id = array_var($_GET,'id');
		$object = Objects::findObject($id);
		if(!($object instanceof ApplicationDataObject)) {
			flash_error(lang('message dnx'));
			return;
		} // if

		if(!$object->canView(logged_user())) {
			flash_error(lang('no access permissions'));
			return ;
		} // if

		try {
			$object->subscribeUser(logged_user());
			ApplicationLogs::createLog($object, ApplicationLogs::ACTION_SUBSCRIBE, false, true, true, logged_user()->getId());
			flash_success(lang('success subscribe to object'));
		} catch (Exception $e) {
			flash_error(lang('error subscribe to object'));
		}
	} // subscribe

	/**
	 * Unsubscribe from object
	 *
	 * @param void
	 * @return null
	 */
	function unsubscribe() {
		ajx_current("reload");

		$id = array_var($_GET,'id');
		$object = Objects::findObject($id);
		if(!($object instanceof ApplicationDataObject)) {
			flash_error(lang('message dnx'));
			return;
		} // if

		if(!$object->canView(logged_user())) {
			flash_error(lang('no access permissions'));
			return;
		} // if

		try {
			$object->unsubscribeUser(logged_user());
			ApplicationLogs::createLog($object,ApplicationLogs::ACTION_UNSUBSCRIBE, false, null, true, logged_user()->getId());
			flash_success(lang('success unsubscribe to object'));
		} catch (Exception $e) {
			flash_error(lang('error unsubscribe to object'));
		}
	} // unsubscribe

	function send_reminders() {
		ajx_current("empty");
		try {
			$sent = Notifier::sendReminders();
			flash_success("success sending reminders", $sent);
		} catch (Exception $e) {
			flash_error($e->getMessage());
		}
	}

	/**
	 * Properties are sent as POST name:values
	 *
	 */
	function save_properties() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		ajx_current("empty");
		$id = array_var($_GET,'id');
		$manager = array_var($_GET,'manager');
		$object = Objects::findObject($id);
		if (!$object->canEdit(logged_user())) {
			return ;
		}
		try {
			$count = 0;
			foreach ($_POST as $n => $v) {
				$object->setProperty($n, $v);
				$count++;
			}
		} catch (Exception $e) {

		}
	}

	function untrash() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object_id = get_id('object_id');
		$object = Objects::findObject($object_id);
		if ($object instanceof ApplicationDataObject && $object->canDelete(logged_user())) {
			try {
				$errorMessage = null;
				DB::beginWork();
				$object->untrash();
				DB::commit();
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_UNTRASH);
				flash_success(lang("success untrash object"));
				if ($object instanceof Contact) self::reloadPersonsDimension();
				else if ($object instanceof MailContent) {
					evt_add("update email list", array('ids' => array($object->getId())));
				}
			} catch (Exception $e) {
				$errorString = is_null($errorMessage) ? lang("error untrash objects", $error) : $errorMessage;
				flash_error($errorString);
				DB::rollback();
			}
		} else {
			flash_error(lang("no access permissions"));
		}
		ajx_current("back");
	}

	function delete_permanently() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object_id = get_id('object_id');
		$dont_reload = array_var($_GET, 'dont_reload');
		$object = Objects::findObject($object_id);
		if (($object instanceof ContentDataObject && $object->canDelete(logged_user()) && (!$object instanceof Contact || !$object->isUser()))) {
			try {
				$errorMessage = null;
				DB::beginWork();
				$object->delete($errorMessage);
				flash_success(lang("success delete object"));
				Hook::fire('after_object_delete_permanently', array($object_id), $ignored);
				DB::commit();
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_DELETE);
			} catch (Exception $e) {
				DB::rollback();
				if (is_null($errorMessage)) Logger::log($e->getMessage());
				$errorString = is_null($errorMessage)? lang("error delete object") : $errorMessage;
				flash_error($errorString);
			}
		} else {
			flash_error(lang("no access permissions"));
		}

		if($dont_reload){
			ajx_current("empty");
		}else{
			ajx_current("back");
		}
	}

	function trash() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		ajx_current("empty");
		$csvids = array_var($_REQUEST, 'ids');
		if (!$csvids && array_var($_REQUEST, 'object_id')) {
			$csvids = array_var($_REQUEST, 'object_id');
			
			if (array_var($_REQUEST, 'reload')) {
				ajx_current("reload");
			} else {
				ajx_current("back");
			}
		}
		$ids = explode(",", $csvids);

		$prompt_user_already_done = array_var($_REQUEST, 'prompt_confirmed');
		if (!$prompt_user_already_done) {
			$can_trash_result = $this->check_if_can_trash_directly($ids);
			if (!$can_trash_result['can_trash']) {
				evt_add('prompt user trash objects', array(
					'message' => $can_trash_result['prompt_message'],
					'ids' => $ids,
					'from_view' => array_var($_REQUEST, 'object_id', 0) > 0,
					'req_channel' => array_var($_REQUEST, 'req_channel', ''),
				));
				ajx_current("empty");
				return;
			}
		}

		$count_persons = 0;
		$count = 0;
		$err = 0;
		$errorMessage = null;
		$error_details = "";
		foreach ($ids as $id) {
			try {
				DB::beginWork();
				$object = Objects::findObject($id);
				if ($object instanceof ContentDataObject && $object->canDelete(logged_user())) {
					if ($object instanceof ProjectTask) {
						// set flag to skip calculations in this step, they will be done later
						$object->dont_calculate_project_financials = true;
					}
					$object->trash(null, false);
					Hook::fire('after_object_trash', $object, $null );
					ApplicationLogs::createLog($object, ApplicationLogs::ACTION_TRASH, null, true);
					$count++;
					if ($object instanceof Contact) $count_persons++;
				} else {
					$err++;
				}
				DB::commit();
			} catch (Exception $e) {
				DB::rollback();
				$err++;
				$error_details .= "\n" . $e->getMessage();
			}
		}
		if ($err > 0) {
			$errorString = is_null($errorMessage)? lang("error delete objects", $err) . $error_details : $errorMessage;
			flash_error($errorString);
		} else {
			if (array_var($_REQUEST, 'reload')) {
				ajx_current("reload");
			}
			flash_success(lang("success trash objects", $count));
			if ($count_persons > 0) self::reloadPersonsDimension();
			Hook::fire('after_object_controller_trash', array_var($_REQUEST, 'ids', array_var($_REQUEST, 'object_id')), $ignored);
		}
	}

	/**
	 * Call this function before trashing so we can check if the objects are associated to other objects
	 * If so, we can prompt the user for confirmation and explain what will be done
	 * Example: when trashing a task that has timeslots
	 */
	function check_if_can_trash_directly($ids) {
		$result = array('can_trash' => true);
		if (count($ids) == 0) {
			return $result;
		}

		// get the tasks among the objects to be deleted
		$task_ids = Objects::instance()->findAll(array(
			"conditions" => array("id IN (?) AND object_type_id = ?", implode(',',$ids), ProjectTasks::instance()->getObjectTypeId()),
			"id" => true
		));
		// check if the tasks found have any time or expense associated
		if (count($task_ids) > 0) {
			$times_count = Timeslots::instance()->count("rel_object_id IN (".implode(',',$task_ids).")");
			$times_associated = $times_count > 0;
			$expenses_associated = false;
			if (PLugins::instance()->isActivePlugin('expenses2')) {
				$b_expenses_count = Expenses::instance()->count("task_id IN (".implode(',',$task_ids).")");
				$a_expenses_count = PaymentReceipts::instance()->count("task_id IN (".implode(',',$task_ids).")");
				$expenses_associated = ($b_expenses_count + $a_expenses_count) > 0;
			}

			if ($times_associated || $expenses_associated) {
				$result = array(
					'can_trash' => false,
					'prompt_message' => lang('task is linked to time expenses are you sure you want to delete'),
				);
			}
		}


		return $result;
	}

	/**
	 * Clears old objects in trash according to config option days_on_trash
	 *
	 */
	function purge_trash() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		ajx_current("empty");
		try {
			$deleted = Trash::purge_trash();
			flash_success("success purging trash", $deleted);
		} catch (Exception $e) {
			flash_error($e->getMessage());
		}
	}

	function archive() {
		ajx_current("empty");
		$csvids = array_var($_GET, 'ids');
		if (!$csvids && array_var($_GET, 'object_id')) {
			$csvids = array_var($_GET, 'object_id');
			ajx_current("back");
		}
		$ids = explode(",", $csvids);
		$count_persons = 0;
		$count = 0;
		$err = 0;
		foreach ($ids as $id) {
			try {
				$object = Objects::findObject($id);
				if ($object instanceof ContentDataObject && $object->canEdit(logged_user())) {
					$object->archive();
					ApplicationLogs::createLog($object, ApplicationLogs::ACTION_ARCHIVE);
					$count++;
					if ($object instanceof Contact) $count_persons++;
				} else {
					$err++;
				}
			} catch (Exception $e) {
				$err++;
			}
		}
		if ($err > 0) {
			flash_error(lang("error archive objects", $err));
		} else {
			flash_success(lang("success archive objects", $count));
			if ($count_persons > 0) self::reloadPersonsDimension();
			Hook::fire('after_object_controller_archive', array_var($_GET, 'ids', array_var($_GET, 'object_id')), $ignored);
		}
	}

	function unarchive() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object_id = get_id('object_id');
		$object = Objects::findObject($object_id);
		if ($object instanceof ApplicationDataObject && $object->canEdit(logged_user())) {
			try {
				DB::beginWork();
				$object->unarchive();
				DB::commit();
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_UNARCHIVE);
				flash_success(lang("success unarchive objects", 1));
				if ($object instanceof Contact) self::reloadPersonsDimension();
				else if ($object instanceof MailContent) {
					evt_add("update email list", array('ids' => array($object->getId())));
				}
			} catch (Exception $e) {
				DB::rollback();
				flash_error(lang("error unarchive objects", 1));
			}
		} else {
			flash_error(lang("no access permissions"));
		}
		ajx_current("back");
	}


	function popup_reminders() {
		ajx_current("empty");

		// extra data to send to interface
		$extra_data = array();

		// if no new popup reminders don't make useless queries
		if (GlobalCache::isAvailable()) {
			$check = GlobalCache::get('check_for_popup_reminders_'.logged_user()->getId(), $success);
			if ($success && $check == 0) return;
		}

		$reminders = ObjectReminders::getDueReminders("reminder_popup");
		$popups = array();
		foreach ($reminders as $reminder) {
			$context = $reminder->getContext();

			if(str_starts_with($context, "mails_in_outbox")){
				if ($reminder->getUserId() > 0 && $reminder->getUserId() != logged_user()->getId()) {
					continue;
				}

				preg_match('!\d+!', $context, $matches);
				evt_add("popup", array(
					'title' => lang("mails_in_outbox reminder"),
					'message' => lang("mails_in_outbox reminder desc", $matches[0]),
					'type' => 'reminder',
					'sound' => 'info'
				));
				$reminder->delete();
				continue;
			}

			if(str_starts_with($context, "eauthfail")){
				if ($reminder->getUserId() == logged_user()->getId()) {
					$acc = trim(substr($context, strrpos($context, " ")));
					evt_add("popup", array(
						'title' => lang("failed to authenticate email account"),
						'message' => lang("failed to authenticate email account desc", $acc),
						'type' => 'reminder',
						'sound' => 'info'
					));
					$reminder->delete();
				}
				continue;
			}

			$object = $reminder->getObject();
			$type = $object->getObjectTypeName();
			if (in_array($context, $object->manager()->getColumns())) {
				$date = $object->getColumnValue($reminder->getContext());
			} else {
				$date = $reminder->getDate();
			}
			if (!$date instanceof DateTimeValue) continue;
			if ($object->isTrashed()) {
				$reminder->delete();
				continue;
			}

			if ($date->getTimestamp() + 5*60 < DateTimeValueLib::now()->getTimestamp()) {
				// don't show popups older than 5 minutes
				//$reminder->delete();
				//continue;
			}
			if ($reminder->getUserId() == 0) {
				if (!$object->isSubscriber(logged_user())) {
					// reminder for subscribers and user is not subscriber
					continue;
				}
			} else if ($reminder->getUserId() != logged_user()->getId()) {
				continue;
			}
			if ($context == "due_date" && $object instanceof ProjectTask) {
				if ($object->isCompleted()) {
					// don't show popups for completed tasks
					$reminder->delete();
					continue;
				}
			}

			$url = $object->getViewUrl();
			$link = '<a href="#" onclick="og.openLink(\''.$url.'\');return false;">'.clean($object->getObjectName()).'</a>';
			evt_add("popup", array(
				'title' => lang("$context $type reminder", clean($object->getObjectName())),
				'message' => lang("$context $type reminder desc", $link, format_datetime($date)),
				'type' => 'reminder',
				'sound' => 'info'
				));
			if ($reminder->getUserId() == 0) {
				// reminder is for all subscribers, so change it for one reminder per user (except logged_user)
				// otherwise if deleted it won't notify other subscribers and if not deleted it will keep notifying
				// logged user
				$subscribers = $object->getSubscribers();
				foreach ($subscribers as $subscriber) {
					if ($subscriber->getId() != logged_user()->getId()) {
						$new = new ObjectReminder();
						$new->setContext($reminder->getContext());
						$new->setDate($reminder->getDate());
						$new->setMinutesBefore($reminder->getMinutesBefore());
						$new->setObject($object);
						$new->setUser($subscriber);
						$new->setType($reminder->getType());
						$new->save();
					}
				}
			}
			$reminder->delete();
		}

		// popup reminders already checked for logged user
		if (GlobalCache::isAvailable()) {
			$today_next_reminders = ObjectReminders::instance()->findAll(array(
				'conditions' => array("`date` > ? AND `date` < ?", DateTimeValueLib::now(), DateTimeValueLib::now()->endOfDay()),
				'limit' => config_option('cron reminder limit', 100)
			));

			if (count($today_next_reminders) == 0) {
				GlobalCache::update('check_for_popup_reminders_'.logged_user()->getId(), 0, 60*30);
			}
		}

		// check for member modifications
		if (isset($_POST['dims_check_date'])) {
			$dims_check_date = new DateTimeValue($_POST['dims_check_date']);
			$dims_check_date_sql = $dims_check_date->toMySQL();
			$members_log_count = ApplicationLogs::instance()->count("member_id>0 AND created_on>'$dims_check_date_sql'");
			if ($members_log_count > 0) {
				$extra_data['reload_dims'] = 1;
			}
		}

		Hook::fire('after_check_for_popup_reminders', array(), $extra_data);

		ajx_extra_data($extra_data);
	}

	function createMinimumUser($email, $compId) {
		$contact = Contacts::getByEmail($email);
		$posArr = strpos_utf($email, '@') === FALSE ? null : strpos($email, '@');
		$user_data = array(
			'username' => $email,
			'display_name' => $posArr != null ? substr_utf($email, 0, $posArr) : $email,
			'email' => $email,
			'contact_id' => isset($contact) ? $contact->getId() : null,
			'password_generator' => 'random',
			'create_contact' => !isset($contact),
			'company_id' => $compId,
			'send_email_notification' => true,
		); // array

		$user = null;
		$user = create_user($user_data, false, '');

		return $user;
	}

	function get_co_types() {
		$object_type = array_var($_GET, 'object_type', '');
		if($object_type != ''){
			$types = ProjectCoTypes::instance()->findAll(array("conditions" => "`object_manager` = ".DB::escape($object_type)));
			$co_types = array();
			foreach($types as $type){
				$t = array();
				$t['id'] = $type->getId();
				$t['name'] = $type->getName();
				$co_types[] = $t;
			}
			ajx_current("empty");
			ajx_extra_data(array("co_types" => $co_types));
		}
	}

	function re_render_custom_properties() {

		$object = Objects::findObject(array_var($_GET, 'id'));
		if (!$object) {
			// if id == 0 object is new, then a dummy object is created to render the properties.
			$object = new ProjectMessage();
		}

		$html = render_object_custom_properties($object, array_var($_GET, 'req'), array_var($_GET, 'co_type'));

		$scripts = array();
		$initag = "<script>";
		$endtag = "</script>";

		$pos = strpos($html, $initag);
		while ($pos !== FALSE) {
			$end_pos = strpos($html, $endtag, $pos);
			if ($end_pos === FALSE) break;
			$ini = $pos + strlen($initag);
			$sc = substr($html, $ini, $end_pos - $ini);
			if (!str_starts_with(trim($sc), "og.addTableCustomPropertyRow")) {// do not add repeated functions
				$scripts[] = $sc;
			}
			$pos = strpos($html, $initag, $end_pos);
		}
		foreach ($scripts as $sc) {
			$html = str_replace("$initag$sc$endtag", "", $html);
		}

		ajx_current("empty");
		ajx_extra_data(array("html" => $html, 'scripts' => implode("", $scripts)));
	}



	function get_cusotm_property_columns() {
		$grouped = array();
		$extra_conditions = '';
		Hook::fire('add_custom_property_condition', array('user'=>logged_user()), $extra_conditions);
		$cp_rows = DB::executeAll("SELECT cp.id, cp.name as cp_name, cp.code as cp_code, ot.name as obj_type, cp.visible_by_default as visible_def, cp.type as cp_type, cp.values as cp_values, cp.default_value as cp_default_value, cp.is_special as cp_special, cp.show_in_lists
				FROM ".TABLE_PREFIX."custom_properties cp INNER JOIN ".TABLE_PREFIX."object_types ot on ot.id=cp.object_type_id 
				WHERE cp.is_disabled=0 ".$extra_conditions."
				ORDER BY ot.name");

		if (is_array($cp_rows)) {
			foreach ($cp_rows as $row) {
				if (!isset($grouped[$row['obj_type']])) $grouped[$row['obj_type']] = array();
				$cp_name = $row['cp_name'];
				if ($row['cp_special']) {
					$label_code = str_replace("_special", "", $row['cp_code']);
					$label_value = Localization::instance()->lang($label_code);
					if (is_null($label_value)) {
						$label_value = Localization::instance()->lang(str_replace('_', ' ', $label_code));
					}
					if (!is_null($label_value)) $cp_name = $label_value;
				}

				if ($row['cp_type'] == 'list') {
					$cp_values = $row['cp_values'];
					$cp = CustomProperties::getCustomProperty($row['id']);
					Hook::fire('override_list_custom_property_values', array('cp' => $cp), $cp_values);
					$row['cp_values'] = $cp_values;
				}
				
				$cp_info = array('id' => $row['id'], 'name' => $cp_name, 'code' => $row['cp_code'], 'visible_def' => $row['visible_def'], 'cp_type' => $row['cp_type'], 'cp_values' => $row['cp_values'], 'cp_default_value' => $row['cp_default_value'], 'show_in_lists' => $row['show_in_lists']);

				$ot = ObjectTypes::findByName($row['obj_type']);
				if ($ot->getType() == 'dimension_object') {
					$cp_info['member_cp'] = 1;
				}

				$grouped[$row['obj_type']][] = $cp_info;
			}
		}
		Hook::fire("get_cusotm_property_columns", array(), $grouped);

		ajx_current("empty");
		ajx_extra_data(array('properties' => $grouped));
	}

	//set user config option value
	function set_user_config_option_value() {
		ajx_current("empty");
		if(!logged_user() instanceof Contact) return;
		$name = array_var($_GET,'config_option_name');
		$value = array_var($_GET,'config_option_value');
		set_user_config_option($name, $value, logged_user()->getId());
	}


	private function processListActions() {

		$linkedObject = null;
		if (array_var($_GET, 'action') == 'delete') {
			$ids = array();
			$exploded = explode(',', array_var($_GET, 'objects'));
			foreach ($exploded as $exp) {
				if (is_numeric($exp)) $ids[] = $exp;
			}

			// let ObjectController::trash() function handle the trash operations
			$_REQUEST['ids'] = implode(',', $ids);
			$this->trash();
			return;
/*
			$result = ContentDataObjects::listing(array(
					"extra_conditions" => " AND o.id IN (".implode(",",$ids).") ",
					"include_deleted" => true
			));

			$objects = $result->objects;
			foreach ($objects as $object) {
				if (method_exists($object, 'setDontMakeCalculations')) $object->setDontMakeCalculations(true);
			}

			$real_deleted_ids = array();
			list($succ, $err) = $this->do_delete_objects($objects, false, $real_deleted_ids);

			if ($err > 0) {
				flash_error(lang('error delete objects', $err));
			} else {
				Hook::fire('after_object_delete_permanently', $real_deleted_ids, $ignored);
				flash_success(lang('success delete objects', $succ));
			}*/
		} else if (array_var($_GET, 'action') == 'delete_permanently') {
			$ids = array();
			$exploded = explode(',', array_var($_GET, 'objects'));
			foreach ($exploded as $exp) {
				if (is_numeric($exp)) $ids[] = $exp;
			}

			$objects = Objects::instance()->findAll(array("conditions" => "id IN (".implode(",",$ids).")"));

			$real_deleted_ids = array();
			list($succ, $err) = $this->do_delete_objects($objects, true, $real_deleted_ids); 

			if ($err > 0) {
				flash_error(lang('error delete objects', $err));
			}
			if ($succ > 0) {
				Hook::fire('after_object_delete_permanently', $real_deleted_ids, $ignored);
				flash_success(lang('success delete objects', $succ));
			}
		}else if (array_var($_GET, 'action') == 'markasread') {
			$ids = explode(',', array_var($_GET, 'objects'));
			list($succ, $err) = $this->do_mark_as_read_unread_objects($ids, true);

		}else if (array_var($_GET, 'action') == 'markasunread') {
			$ids = explode(',', array_var($_GET, 'objects'));
			list($succ, $err) = $this->do_mark_as_read_unread_objects($ids, false);

		}else if (array_var($_GET, 'action') == 'empty_trash_can') {

			$result = ContentDataObjects::listing(array(
					"select_columns" => array('o.id'),
					"raw_data" => true,
					"trashed" => true,
			));
			$objects = $result->objects;
			foreach ($objects as $object) {
				if (method_exists($object, 'setDontMakeCalculations')) $object->setDontMakeCalculations(true);
			}

			if (count($objects) > 0) {
				$obj_ids_str = implode(',', array_flat($objects));
				$extra_conds = "AND o.id IN ($obj_ids_str)";

				$count = Trash::purge_trash(0, 1000, $extra_conds);
				flash_success(lang('success delete objects', $count));
			}

		} else if (array_var($_GET, 'action') == 'archive') {
			$ids = explode(',', array_var($_GET, 'objects'));
			list($succ, $err) = $this->do_archive_unarchive_objects($ids, 'archive');
			if ($err > 0) {
				flash_error(lang('error archive objects', $err));
			} else {
				flash_success(lang('success archive objects', $succ));
			}
		} else if (array_var($_GET, 'action') == 'unarchive') {
			$ids = explode(',', array_var($_GET, 'objects'));
			$member_ids = explode(',', array_var($_GET, 'members'));
			list($succ, $err) = $this->do_archive_unarchive_objects($ids, 'unarchive');
			if (!empty($member_ids)) {
				list($succ2, $err2) = $this->do_unarchive_members($member_ids);
				$succ += $succ2;
				$err += $err2;
			}
			if ($err > 0) {
				flash_error(lang('error unarchive objects', $err));
			} else {
				flash_success(lang('success unarchive objects', $succ));
			}
		}
		else if (array_var($_GET, 'action') == 'unclassify') {
			$ids = explode(',', array_var($_GET, 'objects'));
			$err = 0;
			$succ = 0;
			foreach ($ids as $id) {
				$split = explode(":", $id);
				$type = $split[0];
				if (Plugins::instance()->isActivePlugin('mail') && $type == 'MailContents') {
					$email = MailContents::instance()->findById($split[1]);
					if ($email instanceof MailContent && !$email->isDeleted() && $email->canEdit(logged_user())){
						if (MailController::do_unclassify($email)) $succ++;
						else $err++;
					} else $err++;
				}
			}
			if ($err > 0) {
				flash_error(lang('error unclassify emails', $err));
			} else {
				flash_success(lang('success unclassify emails', $succ));
			}
		}
		else if (array_var($_GET, 'action') == 'restore') {
			$errorMessage = null;
			$ids = explode(',', array_var($_GET, 'objects'));
			$success = 0; $error = 0;
			$hook_result = array('allowed_to_restore' => true, 'error' => 0, 'errorMessage' => null);
			Hook::fire('check_objects_to_get_restored', array('ids' => $ids), $hook_result);
			if($hook_result['allowed_to_restore']){
				foreach ($ids as $id) {
					$obj = Objects::findObject($id);
					if (!$obj instanceof ContentDataObject) continue;
					if (method_exists($obj, 'setDontMakeCalculations')) $obj->setDontMakeCalculations(true);
					if ($obj->canDelete(logged_user())) {
						try {
							$obj->untrash();

							if($obj->getObjectTypeId() == 11){
								$event = ProjectEvents::instance()->findById($obj->getId());
								if($event->getExtCalId() != ""){
									$this->created_event_google_calendar($obj,$event);
								}
							}

							ApplicationLogs::createLog($obj, ApplicationLogs::ACTION_UNTRASH);
							$success++;
						} catch (Exception $e) {
							$error++;
						}
					} else {
						$error++;
					}
				}
			} else {
				$error = $hook_result['error'];
				$errorMessage = $hook_result['errorMessage'];
			}
			if ($success > 0) {
				flash_success(lang("success untrash objects", $success));
			}
			if ($error > 0) {
				$errorString = is_null($errorMessage) ? lang("error untrash objects", $error) : $errorMessage;
				flash_error($errorString);
			}
		}

		if (!array_var($_GET, 'only_result')) {
			$ignored = null;
			Hook::fire('after_multi_object_action', array('object_ids' => explode(',', array_var($_GET, 'objects')), 'action' => array_var($_GET, 'action')), $ignored);
		}

	}


	function list_objects() {

		$params = $this->get_list_objects_params();
		$listing = $this->get_objects_list($params);

		ajx_extra_data($listing);
		tpl_assign("listing", $listing);

		if (isset($reload) && $reload) ajx_current("reload");
		else ajx_current("empty");
	}

	function get_list_objects_params() {

		$params = array();
		$filesPerPage = config_option('files_per_page');
		$typeCSV = array_var($_GET, 'type');
		$types = null;
		if ($typeCSV) {
			$types = explode(",", $typeCSV);
		}

		$params['start'] = array_var($_GET,'start') ? (integer)array_var($_GET,'start') : 0;
		$params['limit'] = array_var($_GET,'limit') ? array_var($_GET,'limit') : $filesPerPage;
		$params['order'] = array_var($_GET,'sort');
		$params['filesPerPage'] = $filesPerPage;
		$params['id_no_select'] = array_var($_GET,'id_no_select',"undefined");
		$params['ignore_context'] = (bool) array_var($_GET, 'ignore_context');
		$params['member_ids'] = json_decode(array_var($_GET, 'member_ids'));
		$params['extra_member_ids'] = json_decode(array_var($_GET, 'extra_member_ids'));
		$params['type_filter'] = array_var($_GET, 'type_filter', 0);
		$params['orderdir'] = array_var($_GET,'dir');
		$params['extra_list_params'] = json_decode(array_var($_GET,'extra_list_params'));
		$params['page'] = (integer) ($params['start'] / $params['limit']) + 1;
		$params['hide_private'] = !logged_user()->isMemberOfOwnerCompany();
		$params['types'] = $types;
		$params['user'] = array_var($_GET,'user');
		$params['trashed'] = array_var($_GET, 'trashed', false);
		$params['archived'] = array_var($_GET, 'archived', false);
		$params['filters'] = array();
		$params['use_definition'] = array_var($_GET, 'use_definition', false);

		if (is_array($params['member_ids'])) {
			$params['member_ids'] = array_filter($params['member_ids'], 'is_numeric');
		}
		if (is_array($params['extra_member_ids'])) {
			$params['extra_member_ids'] = array_filter($params['extra_member_ids'], 'is_numeric');
		}
		
		if (!in_array(strtoupper($params['orderdir']), array('ASC', 'DESC'))) $params['orderdir'] = 'ASC';

		if ($params['order'] == "dateUpdated") {
			$params['order'] = "updated_on";
		}elseif ($params['order'] == "dateArchived") {
			$params['order'] = "archived_on";
		}elseif ($params['order'] == "dateDeleted") {
			$params['order'] = "trashed_on";
		}elseif ($params['order'] == "name") {
			$params['order'] = "name";
		} else {
			$params['order'] = "";
			$params['orderdir'] = "";
		}

		$name_filter = mysqli_real_escape_string(DB::connection()->getLink(), array_var($_GET, 'name') );
		$linked_obj_filter = array_var($_GET, 'linkedobject');
		$object_ids_filter = '';
		$show_all_linked_objects = false;
		if (!is_null($linked_obj_filter)) {
			$show_all_linked_objects = true;
			$linkedObject = Objects::findObject($linked_obj_filter);
			$objs = $linkedObject->getLinkedObjects();
			foreach ($objs as $obj) $object_ids_filter .= ($object_ids_filter == '' ? '' : ',') . $obj->getId();
		}

		$params['filters']['types'] = false;
		$params['filters']['name'] = false;
		$params['filters']['object_ids'] = false;
		$params['filters']['contact_type_filter'] = false;

		if (!is_null($types)) $params['filters']['types'] = $types;
		if (!is_null($name_filter)) $params['filters']['name'] = $name_filter;
		if ($object_ids_filter != '') $params['filters']['object_ids'] = $object_ids_filter;

		$contact_type_filter_json = array_var($_REQUEST, 'contact_type_filter',false);
		if($contact_type_filter_json){
			$params['filters']['contact_type_filter'] = json_decode($contact_type_filter_json, true);
		}

		$params['show_all_linked_objects'] = $show_all_linked_objects;

		return $params;
	}

	function get_objects_list($params) {
		/*params*/
		$only_ids = array_var($params, 'only_ids', false);
		$start = $params['start'];
		$limit = $params['limit'];
		$order = $params['order'];
		$id_no_select = $params['id_no_select'];
		$ignore_context = $params['ignore_context'];
		$member_ids = $params['member_ids'];
		$extra_member_ids = $params['extra_member_ids'];
		$type_filter = $params['type_filter'];
		$orderdir = $params['orderdir'];
		$extra_list_params = $params['extra_list_params'];
		$page = $params['page'];
		$hide_private = $params['hide_private'];
		$types = $params['types'];
		$user = $params['user'];
		$trashed = $params['trashed'];
		$archived = $params['archived'];
		$filters = $params['filters'];
		$show_all_linked_objects = $params['show_all_linked_objects'];
		$use_definition = $params['use_definition'];
		//$use_object_class_for_get_array_info = (is_null($params['use_object_class_for_get_array_info'])?false:true);
		$use_object_class_for_get_array_info = isset($params['use_object_class_for_get_array_info']) ? $params['use_object_class_for_get_array_info'] : false;
		
		//variable to search in searchable_objects table
		//$text_search = isset($params['text_search']);
		$text_search = isset($params['text_search']) ? $params['text_search'] : '';
        //$text_search_key = $params['text_search_key'];
		$text_search_key = isset($params['text_search_key']) ? $params['text_search_key'] : '';

		$filesPerPage = $params['filesPerPage'];
		$name_filter = $filters['name'];
		$object_ids_filter = $filters['object_ids'];

        $only_count_result = array_var($_GET, 'only_result', false);
        $count_results = array_var($_GET, 'count_results', false);
        if(isset($params['count_results'])){
            $count_results = $params['count_results'];
		}

		/* if there's an action to execute, do so */
		if (!$show_all_linked_objects){
			$this->processListActions();
		}

		if ($archived && $type_filter > 0) {
			$ot = ObjectTypes::instance()->findById($type_filter);
			if ($ot instanceof ObjectType && $ot->getType() == 'dimension_group') {
				// call member controller to get list of members
				$member_controller = new MemberController();
				$dim_ids = DimensionObjectTypes::getDimensionIdsByObjectTypeId($type_filter);
				
				$mem_controller_params = $params;
				$mem_controller_params['archived'] = $archived;
				$mem_controller_params['dim_id'] = $dim_ids[0];
				$mem_controller_params['type_id'] = $type_filter;
				$mem_controller_params['exclude_associations_data'] = true;

				$members_list = $member_controller->listing($mem_controller_params);

				$listing = array(
					"totalCount" => $members_list['totalCount'],
					"start" => $start,
					"objects" => $members_list['members'],
					"filters" => $this->get_available_object_filter_types($archived),
				);
		
				return $listing;
			}
		}
		
		$template_object_names = "";
		$template_extra_condition = "true";

		$template_objects = false;
		//if(in_array("template_task", array_var($filters, 'types', array())) || in_array("template_milestone", array_var($filters, 'types', array()))){
		if ($filters['types'] && ( in_array("template_task", $filters['types']) || in_array("template_milestone", $filters['types']))){
			$template_id = 0;
			$template_objects = true;
			if(isset($extra_list_params->template_id)){
				$template_id = $extra_list_params->template_id;
			}
			if(isset($extra_list_params->id_no_select)) {
				$id_no_select = $extra_list_params->id_no_select;
			}
			
			$tmpl_task = TemplateTasks::instance()->findById(intval($id_no_select));
			if($tmpl_task instanceof TemplateTask){
				$template_extra_condition = "o.id IN (SELECT object_id from ".TABLE_PREFIX."template_tasks WHERE `template_id`=".$tmpl_task->getTemplateId()." OR `template_id`=0 AND `session_id`=".logged_user()->getId()." )";
			}else{
				$template_extra_condition = "o.id IN (SELECT object_id from ".TABLE_PREFIX."template_tasks WHERE `template_id`=".intval($template_id)." OR `template_id`=0 AND `session_id`=".logged_user()->getId()." )";
			}
		}else{
			$template_object_names = "AND ot.name <> 'template_task' AND ot.name <> 'template_milestone'" ;
		}
		$result = null;

        $select_fields = "*";
        if($only_ids){
            $select_fields = "o.id";
        }

		$context = active_context();

		// select only content objects if asked to, otherwise select all listable types
		$obj_type_types = array('content_object', 'located');
		
		// for the archived objects list, include dimension member types
		$show_dim_members = array_var($params, 'archived');
		if ($show_dim_members) {
			// select dimension group and dimension objects if asked to
			$obj_type_types[] = 'dimension_group';
			$obj_type_types[] = 'dimension_object';
		}

		if (array_var($_GET, 'only_content_objects')) {
			// select only content objects if asked to
			$obj_type_types = array('content_object');
		}
		if (array_var($_GET, 'include_comments')) {
			// select comments if asked to
			$obj_type_types[] = 'comment';
		}

		// generate the condition for the object type
		$type_condition = "";
		if ($types) {
			// select only the object types in the list if one is provided
			$type_condition = " AND ot.name IN ('".implode("','",$types) ."')";
		}
		if ($type_filter > 0) {
			// select only the object type with the given id if one is provided
			$type_condition .= " AND ot.id=$type_filter";
		}

		if (count($obj_type_types) > 0) {
			// select only the object types in the list
			$type_condition .= " AND ot.type IN ('". implode("','", $obj_type_types) ."')";
		}

		$extra_conditions = array();
		if (array_var($filters, 'contact_type_filter')) {
			$joins[] = " LEFT JOIN ".TABLE_PREFIX."contacts c on c.object_id=o.id";

			$contact_type_filter = $filters['contact_type_filter'];
			$show_contacts = array_var($contact_type_filter, 'contact');
			$show_users = array_var($contact_type_filter, 'user');
			$show_companies = array_var($contact_type_filter, 'company');

			if(!$show_companies){
				$extra_conditions[] = ' c.`is_company` = 0 ';
			}
			if(!$show_contacts){
				if($show_companies){
					if($show_users){
						$extra_conditions[] = ' (c.`is_company` = 1  OR c.`user_type` != 0) ';
					} else {
						$extra_conditions[] = ' c.`is_company` = 1 ';
					}
				}else{
					$extra_conditions[] = ' c.`user_type` != 0  ';
				}
			}
			if(!$show_users){
				$extra_conditions[] = ' c.`user_type` < 1 ';
			}

		}
		// user filter
		if ($filters['types'] && in_array("contact", $filters['types']) && isset($extra_list_params->is_user)) {
			$joins[] = " LEFT JOIN ".TABLE_PREFIX."contacts c on c.object_id=o.id";
			
			$extra_conditions[] = "
				c.user_type ".($extra_list_params->is_user == 1 ? ">" : "=" )." 0";

			if (isset($extra_list_params->has_permissions) && $extra_list_params->has_permissions > 0) {
				$mem_id = $extra_list_params->has_permissions;
				$extra_conditions[] = " EXISTS (
					SELECT cmp.permission_group_id FROM ".TABLE_PREFIX."contact_member_permissions cmp
					WHERE cmp.permission_group_id IN (SELECT x.permission_group_id FROM ".TABLE_PREFIX."contact_permission_groups x WHERE x.contact_id=o.id)
							AND cmp.member_id=".DB::escape($mem_id)."
							AND cmp.object_type_id NOT IN (SELECT tp.object_type_id FROM ".TABLE_PREFIX."tab_panels tp WHERE tp.enabled=0)
					AND cmp.object_type_id NOT IN (SELECT oott.id FROM ".TABLE_PREFIX."object_types oott WHERE oott.name IN ('comment','template'))
					AND cmp.object_type_id IN (SELECT oott2.id FROM ".TABLE_PREFIX."object_types oott2 WHERE oott2.type IN ('content_object'))
				)";
			}
		}
		
		//text search with searchable_objects
        if(!empty($text_search) and !is_null($text_search)){
		    $joins[] = "INNER JOIN ".TABLE_PREFIX."searchable_objects so ON so.rel_object_id=o.id";
            $extra_conditions[]= " so.`content` like '%{$text_search}%'";
            $select_fields=" DISTINCT(o.id),o.* ";
            if(!empty($text_search_key) and !is_null($text_search_key)){
                $extra_conditions[]= " so.`column_name` like '{$text_search_key}' ";    
            }
        }


		// Object type filter - exclude template types (if not template picker), filter by required type names (if specified) and match value with objects table
		// ORG $extra_object_type_conditions = "
		//AND ot.name <> 'file revision' $template_object_names $type_condition AND o.object_type_id = ot.id";

		$extra_object_type_conditions = "
		AND ot.name <> 'file revision' $template_object_names $type_condition";

		// this will filter out object types that are not listable and also filters out dimension members if $show_dim_members is false
		// this is used in the object picker to filter out object types that should not be available for selection
		$extra_conditions[] = ObjectTypes::getListableObjectsSqlCondition($extra_object_type_conditions, $show_dim_members);
		// --


		// logged user permission group ids
		$logged_user_pg_ids = implode(',', logged_user()->getPermissionGroupIds());

		// used in template object picker
		$extra_conditions[] = $template_extra_condition;

		// when filtering by name
		if ($name_filter) {
			$extra_conditions[] = "
			o.name LIKE '%$name_filter%'";
		}

		// when excluding some object in particular
		if ($id_no_select != "undefined") {
			$extra_conditions[] = "
			o.id <> '$id_no_select'";
		}

		// when filtering by some group of objects, for example in the linked objects view
		if($object_ids_filter != ""){
			$extra_conditions[] = "
			o.id in ($object_ids_filter)";
		}

		$joins[] = "
			LEFT JOIN ".TABLE_PREFIX."project_tasks pt on pt.object_id=o.id";

		if (!SystemPermissions::userHasSystemPermission(logged_user(), 'can_see_assigned_to_other_tasks')) {
			// exclude other users' tasks if cannot see them
			$extra_conditions[] = "
				( pt.assigned_to_contact_id IS NULL OR pt.assigned_to_contact_id= ".logged_user()->getId().")";
		}
		// don't include tasks which have is_template=1
		$extra_conditions[] = "
			( pt.is_template IS NULL OR pt.is_template=0)";

		// trashed conditions
		$extra_conditions[] = "
			o.trashed_on".($trashed ? "<>" : "=")."0";
		
		// only show objects trashed by the logged user (if not superadmin)
		if ($trashed && logged_user() instanceof Contact && !logged_user()->isAdministrator()) {
			$extra_conditions[] = "
				o.trashed_by_id=".logged_user()->getId();
		}
		
		// archived conditions
		if (!$trashed) {
			$extra_conditions[] = "
			o.archived_on".($archived ? "<>" : "=")."0";
		}

		// don't include unclassified mails from other accounts
		if (Plugins::instance()->isActivePlugin('mail')) {
			$accounts_of_loggued_user = MailAccountContacts::getByContact(logged_user());
			$account_ids = array(0);
			foreach ($accounts_of_loggued_user as $acc) {
				$account_ids[] = $acc->getAccountId();
			}

			$joins[] = "
				LEFT JOIN ".TABLE_PREFIX."mail_contents mc on mc.object_id=o.id
			";

			$extra_conditions[] = " (mc.is_deleted=0 OR mc.is_deleted IS NULL) ";

			$extra_conditions[] = "
				IF( mc.account_id IS NULL, true, mc.account_id IN (".implode(',', $account_ids).") OR EXISTS (
					SELECT om1.object_id FROM ".TABLE_PREFIX."object_members om1
						INNER JOIN ".TABLE_PREFIX."members m1 ON m1.id=om1.member_id
						INNER JOIN ".TABLE_PREFIX."dimensions d1 ON d1.id=m1.dimension_id
					WHERE om1.object_id=o.id AND d1.is_manageable=1)
				)";
		}

		// don't show attached files of emails that cannot be viewed
		if (logged_user()->isAdministrator() && Plugins::instance()->isActivePlugin('mail')) {
			$joins[] = "LEFT JOIN ".TABLE_PREFIX."project_files pf on pf.object_id=o.id";
			$extra_conditions[] = "IF(pf.mail_id IS NULL OR pf.mail_id = 0, true,
				pf.mail_id IN (SELECT sh.object_id FROM ".TABLE_PREFIX."sharing_table sh WHERE pf.mail_id = sh.object_id AND sh.group_id  IN ($logged_user_pg_ids))
				OR o.id IN (SELECT sh.object_id FROM ".TABLE_PREFIX."sharing_table sh WHERE o.id= sh.object_id AND sh.group_id  IN ($logged_user_pg_ids))
			)";
		}

		// Members filter
		$sql_members = "";
		if (!$ignore_context && !$member_ids) {
			$members = active_context_members(false); // Context Members Ids
		} elseif ( !is_null($member_ids) && count($member_ids) ) {
			$members = $member_ids;
		} else {
			// get members from context
			if (!$ignore_context) {
				$members = active_context_members(false);
			}
		}
		if  (is_array($extra_member_ids)) {
			if (isset($members)) {
				$members = array_merge($members, $extra_member_ids);
			} else {
				$members = $extra_member_ids;
			}
		}
		if (isset($members) && is_array($members) && count($members) > 0 && !isset($template_id) ) {
			$sql_members = "
				AND (EXISTS (SELECT om.object_id
					FROM  ".TABLE_PREFIX."object_members om
					WHERE om.member_id IN (" . implode ( ',', $members ) . ") AND o.id = om.object_id
					GROUP BY object_id
					HAVING count(member_id) = ".count($members)."
				))
			";
		}
		// --
		// External extra conditions
		Hook::fire("get_objects_list_extra_conditions", $params, $extra_conditions);
		// --

		// Permissions filter
		if (logged_user()->isAdministrator() || isset($template_id)) {
			// editing template items do not check permissions
			$sql_permissions = "";
		} else {
			$sql_permissions = "
				AND EXISTS (SELECT sh.object_id FROM ".TABLE_PREFIX."sharing_table sh WHERE sh.object_id=o.id AND sh.group_id IN ($logged_user_pg_ids))
			";
		}

		// Main select
		$sql_select = "SELECT ".$select_fields." FROM ".TABLE_PREFIX."objects o ";

		// Joins
		$sql_joins = implode(" ", $joins);

		// Where
		$sql_where = "
			WHERE " . implode(" AND ", $extra_conditions) . $sql_permissions . $sql_members;

		// Order
		$sql_order = "";
		if ($order) {
			$sql_order = "
			ORDER BY $order $orderdir
			";
		}

		// Limit
		$sql_limit = "";
		if ($start >= 0 && $limit > 0) {
			$sql_limit = " LIMIT $start, $limit";
		}

		// Full SQL
		$sql = "$sql_select $sql_joins $sql_where $sql_order $sql_limit";
		
		// Execute query
		if (!$only_count_result) {
			$rows = DB::executeAll($sql);
		}
		if ($rows) {
			foreach ($rows as &$row) {
				$res = DB::executeOne(
					"SELECT al.is_mail_rule 
					 FROM " . TABLE_PREFIX . "application_logs al 
					 WHERE al.rel_object_id = ? 
					   AND al.is_silent != 1 
					 ORDER BY al.id DESC 
					 LIMIT 1",
					$row['id']
				);
		
				// Assign the more recent is_mail_rule log value that is not silent
				$row['is_mail_rule'] = isset($res['is_mail_rule']) ? $res['is_mail_rule'] : null;
			}
			unset($row);
		}
	

		// get total items
		if ($count_results) {
            if(!empty($text_search) and !is_null($text_search)){
                $sql_count = "SELECT count(DISTINCT(o.id)) as total_items FROM ".TABLE_PREFIX."objects o $sql_joins $sql_where";
            }else{
                $sql_count = "SELECT count(o.id) as total_items FROM ".TABLE_PREFIX."objects o $sql_joins $sql_where";
            }
			$rows_count = DB::executeAll($sql_count);
			$total_items = $rows_count[0]['total_items'];
		} else {
			if (isset($rows) && is_array($rows)) {
				$total_items = count($rows) < $filesPerPage ? count($rows) : 1000000;
			} else {
				$total_items = 0;
			}
		}

		// prepare response object
		$info = array();

		// get objects
		if (isset($rows) && is_array($rows)) {
			foreach ($rows as $row) {
				if(!$only_ids){
					$instance = Objects::findObject($row['id']);

					if (!$instance instanceof ContentDataObject) continue;

					if ($use_definition) {
						$info_elem = $instance->getObjectData();

					} else {
						if($use_object_class_for_get_array_info==false){
                            $info_elem = $instance->getObject()->getArrayInfo($trashed, $archived);
                        }else{
                            $info_elem = $instance->getArrayInfo($trashed, $archived);
                        }

						$info_elem['url'] = $instance->getViewUrl();
						$info_elem['isRead'] = $instance->getIsRead(logged_user()->getId()) ;
						$info_elem['manager'] = get_class($instance->manager()) ;
						$info_elem['memPath'] = json_encode($instance->getMembersIdsToDisplayPath());
						$info_elem['is_mail_rule'] = $row['is_mail_rule'];

						if ($instance instanceof Contact) {
							if( $instance->isCompany() ) {
								$info_elem['icon'] = 'ico-company';
								$info_elem['type'] = 'company';
							}else{
								// cut users and contacts amount of members to show in the object picker, they can lead to performance issues
								if ($instance->isUser()) {
									$max_members_to_show = 5; // to avoid performance issues when lisiting users 
								} else {
									$max_members_to_show = 20; // to avoid performance issues when lisiting contacts, some may be classified in many projects 
								}
								$members_path = $instance->getMembersIdsToDisplayPath(false, array(
									"max_members_per_dimension" => $max_members_to_show, // to avoid performance issues when lisiting users 
								));
								$info_elem['memPath'] = json_encode($members_path);
							}
						} else if ($instance instanceof ProjectFile) {
							$info_elem['mimeType'] = $instance->getTypeString();
						} else if ($instance instanceof ProjectTask) {
							$info_elem['startDate'] = format_datetime($instance->getStartDate(), null, logged_user()->getUserTimezoneHoursOffset());
							$info_elem['dueDate'] = format_datetime($instance->getDueDate(), null, logged_user()->getUserTimezoneHoursOffset());
							$info_elem['completedBy'] = $instance->getCompletedById();
							$info_elem['dateCompleted'] = $instance->getCompletedOn() instanceof DateTimeValue ? format_datetime($instance->getCompletedOn()) : '';
							$info_elem['assignedTo'] = $instance->getAssignedToName();
						}
					}

					Hook::fire('get_objects_list_more_element_data', array('object'=>$instance), $info_elem);

					$info[] = $info_elem;
				}else{
					$info[] = $row['id'];
				}
			}
		}

		$listing = array(
				"totalCount" => $total_items,
				"start" => $start,
				"objects" => $info
		);

		// We need to provide the object types that can be selected
		// so that the UI can present them to the user.
		// The object types that can be selected are all the content objects
		// and dimension objects, except for the following:
		// - template_task
		// - template_milestone
		// - project_folder
		// - customer_folder
		// - file revision
		// - company
		// - person
		// In addition, only the object types that have an activated plugin
		// are shown, unless the plugin is not installed, in which case
		// the object type is shown anyway.
		
		$listing['filters'] = array(
				'types' => $this->get_available_object_filter_types($show_dim_members),
		);

		return $listing;
	}

	private function get_available_object_filter_types($show_dim_members = false) {
		$filter_types = "'content_object'";
		if ($show_dim_members) {
			$filter_types .= ", 'dimension_group', 'dimension_object'";
		}

		$object_types = ObjectTypes::instance()->findAll(array(
			'conditions' => "type IN ($filter_types) AND 
							(plugin_id = 0 OR plugin_id IS NULL OR (SELECT is_activated FROM ".TABLE_PREFIX."plugins WHERE id=plugin_id) = 1)"
		));
		$object_types_info = array();
		foreach ($object_types as $ot) {
			if (in_array($ot->getName(), array('template_task','template_milestone','project_folder','customer_folder','file revision','company','person'))) {
				continue;
			}
			$object_types_info[] = array('id' => $ot->getId(), 'name' => clean($ot->getPluralObjectTypeName()));
		}

		return $object_types_info;
	}

	function save_selected_objects() {
		$this->addHelper('object_selector');

		$ids_to_add = json_decode(array_var($_REQUEST, 'ids_to_add'), true);
		$ids_to_remove = json_decode(array_var($_REQUEST, 'ids_to_remove'), true);

		save_selected_objects_ids($ids_to_add, $ids_to_remove);

		ajx_current("empty");
	}

	function remove_all_selected_objects() {
		$this->addHelper('object_selector');

		save_tmp_objects_ids(array(), true);

		ajx_current("empty");
	}

	function revert_object_selector_changes() {
		$this->addHelper('object_selector');

		$identifier = get_selector_identifier();
		$original_object_ids = array_var($_SESSION['object_selector'], $identifier, array());

		save_tmp_objects_ids($original_object_ids, true);

		unset($_SESSION['object_selector'][$identifier]);

		ajx_current("empty");
	}

	function clean_temp_object_selector_vars() {
		$this->addHelper('object_selector');

		unset($_SESSION['object_selector'][get_selector_identifier()]);

		ajx_current("empty");
	}

	function list_all_selected_objects() {
		$this->addHelper('object_selector');

		$listing = get_selected_objects();
		ajx_extra_data($listing);
		tpl_assign("listing", $listing);

		if (isset($reload) && $reload) ajx_current("reload");
		else ajx_current("empty");
	}

	function list_objects_to_select() {
		$this->addHelper('object_selector');

		$params = $this->get_list_objects_params();
		$listing = $this->get_objects_list($params);

		$selected_ids = get_selected_objects_ids();
		foreach ($listing['objects'] as &$object) {
			$object['checked'] = in_array($object['object_id'], $selected_ids);
		}

		ajx_extra_data($listing);
		tpl_assign("listing", $listing);

		if (isset($reload) && $reload) ajx_current("reload");
		else ajx_current("empty");
	}




	/**
	 * Removes a task from a timeslot or expense.
	 *
	 * This function is called when the user wants to remove a task from a timeslot/expense.
	 * It first checks if the user has permission to edit the timeslot/expense and the task.
	 * If not, it will display an error message and return. If the user has permission,
	 * it will remove the task from the timeslot/expense and recalculate the financials for the task.
	 * It also generates a log entry for the action.
	 *
	 * @return void
	 * @throws Exception if an error occurs during the process
	 */
	function remove_task_from_object() {
		// Return ajax response immediately
		ajx_current("empty");

		// Get the ids of the selected timeslots
		$object_id = array_var($_REQUEST, "object_id");

		// Get the time entry or expense object
		$object = Objects::findObject($object_id);
		$object_type_name = $object->getObjectTypeName();

		// If no timeslots are selected, display an error message and return
		if (!$object instanceof ContentDataObject) {
			flash_error(lang("object dnx"));
			return;
		}

		// If the user does not have permission to edit the timeslot, display an error message and return
		if (!$object->canEdit(logged_user())) {
			flash_error(lang("no edit permissions for object", $object->getName()));
			return;
		}

		if ($object->getColumnValue('invoicing_status') == 'invoiced'){
			flash_error(lang('you cannot edit invoiced '.($object_type_name == "timeslot" ? 'time entry' : 'actual expense')));
			return;
        }

		// Get the task object
		$task = null;
		if ($object_type_name == "timeslot") {
			/** @var Timeslot $object */
			$task = $object->getRelObject();
		} else if ($object_type_name == "payment_receipt" || $object_type_name == "expense") {
			/** @var PaymentReceipt $object */
			$task = $object->getTask();
		}

		// If the task object does not exist, display an error message and return
		if ($task instanceof ProjectTask) {
			
			// If the user does not have permission to edit the task, display an error message and return
			if (!$task->canEdit(logged_user())) {
				flash_error(lang("no edit permissions for object", $task->getName()));
				return;
			}

			try {
				DB::beginWork();

				// Generate old object for logs
				$old_content_object = $object->generateOldContentObjectData();

				if ($object_type_name == "timeslot") {
					/** @var Timeslot $object */
					$object->setRelObjectId(0);
				} else if ($object_type_name == "payment_receipt" || $object_type_name == "expense") {
					/** @var PaymentReceipt $object */
					$object->setTaskId(0);
				}
				
				$object->save();

				// Recalculate financials for new and old related tasks
				Hook::fire('calculate_executed_cost_and_price', array(), $task);
				Hook::fire('calculate_estimated_and_executed_financials', array(), $task);

				DB::commit();

				// Create log for the object edition
				ApplicationLogs::instance()->createLog($object, ApplicationLogs::ACTION_EDIT, false, true, true, "");

				// Return ajax response with success message
				ajx_current("empty");
				ajx_extra_data(["object" => $object->getObjectData()]);
				flash_success(lang("success removing task from object", $task->getName()));

			} catch (Exception $e) {
				// Rollback the database transaction if an exception occurs
				DB::rollback();
				ajx_current("empty");
				flash_error($e->getMessage());
				return;
			}

		}

	}

}
