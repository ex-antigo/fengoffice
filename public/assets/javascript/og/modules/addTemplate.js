og.templateParameters = [];
og.templateObjects = [];

if (!og.templates) og.templates = { datetypes: ['date'] };

og.loadTemplateVars = function(){
	og.templateParameters = [];
	og.templateObjects = [];
};

og.pickObjectForTemplate = function(before) {
	
//	if (! og.contextManager.hasCheckedMembers(13) ){
//		alert(lang("no template members selected"));
//		return ;
//	}
//	
//	og.contextManager.getCheckedMembers();
	//get_url('task', 'add_task', array('template_task' => "true"))?>
	
	var params = new Array();
	params["template_task"] = true;	
	og.openLink(og.getUrl('task','add_task'), {'post' : params});
	
	//obj={type:"task",object_id:"Doe",name:"blue"}; 
	//og.addObjectToTemplate(this, obj);
	
	
	/*og.ObjectPicker.show(function (objs) {
		if (objs) {
			for (var i=0; i < objs.length; i++) {
				var obj = objs[i].data;
				if (obj.type != 'task' && obj.type != 'milestone') {
					og.msg(lang("error"), lang("object type not supported"), 4, "err");
				} else {
					for(var k=0; k < og.templateObjects.length; k++){
						if(og.templateObjects[k].object_id == obj.object_id){
							alert(lang('object exists in template'));
							return;
						}
					}
					og.addObjectToTemplate(this, obj);
				}
			}
		}
	}, before, {
		types: ['task','milestone'],
		selected_type: 'task'
//		context: og.contextManager.plainCheckedMembers(13)
	});*/
	
};

og.drawTemplateObjectMilestonesCombo = function(object_div, object) {
	var combo = Ext.get(object_div.id + "-milestones-combo");
	if (combo) combo = combo.dom;
	
	var option_list = [];
	for (i=0; i<og.templateObjects.length; i++) {
		var m = og.templateObjects[i];
		if (m.type == 'milestone') {
			var option = document.createElement('option');
			option.value = m.object_id;
			option.text = og.clean(m.name);
			option_list.push(option);
		}
	}
	if (option_list.length > 0) {
		if (!combo) {
			combo = document.createElement('select');
			combo.id = object_div.id + "-milestones-combo";
			combo.name = 'milestones['+object.object_id+']';
			
			var combo_container_div = document.createElement('div');
			combo_container_div.setAttribute('style', 'padding: 10px 0 10px 30px;');
			object_div.appendChild(combo_container_div);
			
			combo_container_div.innerHTML = '<span class="bold">'+lang('milestone')+':&nbsp;</span>';
			combo_container_div.appendChild(combo);
		}

		var sel_id = object.milestone_id ? object.milestone_id : 0;
		var sel_index = 0;
		if (combo && combo.options && combo.options[combo.options.selectedIndex]) sel_id = combo.options[combo.options.selectedIndex].value;
		while (combo.options.length > 0) combo.remove(0);
		
		var none_option = document.createElement('option');
		none_option.value = 0;
		none_option.text = '--';
		combo.appendChild(none_option);
		for (j=0; j<option_list.length; j++) {
			if (option_list[j].value == sel_id) {
				sel_index = j + 1;
			}
			combo.appendChild(option_list[j]);
		}
		
		combo.options[sel_index].setAttribute('selected', true);
		combo.style.display = '';
		
	} else {
		if (combo) combo.parentNode.style.display = 'none';
	}
}
/*
og.toggleTemplateSubtasks = function(taskId){
	var subtasksDiv = document.getElementById('ogTasksPanelSubtasksT' + taskId);
	var expander = document.getElementById('ogTasksPanelFixedExpanderT' + taskId);
	var task = this.getTask(taskId);
	if (subtasksDiv){
		task.isExpanded = !task.isExpanded;
		subtasksDiv.style.display = (task.isExpanded)? 'block':'none';
		expander.className = "og-task-expander " + ((task.isExpanded)?'toggle_expanded':'toggle_collapsed');
	}
}
*/


og.askUserToFixRepetitiveTemplateTasks = function(data) {
	var template_id = data.template_id;
	var tasks = data.tasks;

	
	var info = lang('warning you have added some repetitive tasks for this template that do not have date variables for their date');

	var tasks_list_html = '<br /><div class="bold">'+lang('tasks')+':</div><ul>';
	for (var i=0; i<tasks.length; i++) {
		var t = tasks[i];
		if (!t) continue;
		tasks_list_html += '<li><span class="link-ico ico-task">'+ t.name +'</span></li>';
	}
	tasks_list_html += '</ul>';
	
	var div = document.createElement('div');
	div.style = "border-radius: 5px; background-color: #fff; padding: 10px; width: 500px;";
	var genid = Ext.id();
	
	div.innerHTML = '<div><label class="coInputTitle">'+lang('fix repetitive tasks')+'</label></div>'+
		'<div id="'+genid+'_question">'+ info + '<br />' + tasks_list_html + '</div>'+
		'<div id="'+genid+'_buttons">'+
		'<button class="yes submit blue" style="width:100%;">'+lang('yes let me add those variables')+'</button>'+
		'<button class="no submit blue" style="width:100%;">'+lang('no time now remove repetition')+'</button>'+
		'</div><div class="clear"></div>';

	var modal_params = {
		'escClose': false,
		'overlayClose': false,
		'closeHTML': '<a id="'+genid+'_close_link" class="modal-close" title="'+lang('close')+'" style="display:none;"></a>',
		'onShow': function (dialog) {
			$("#"+genid+"_close_link").addClass("modal-close-img");
			$("#"+genid+"_buttons").css('text-align', 'right').css('margin', '10px 0');
			$("#"+genid+"_question").css('margin', '10px 0');
			$("#"+genid+"_buttons button.yes").css('margin-right', '10px').click(function(){
				Ext.getCmp('tabs-panel').getActiveTab().back();
				og.openLink(og.getUrl('template', 'edit', {id:template_id}), {});
				$('.modal-close').click();
			});
			$("#"+genid+"_buttons button.no").css('margin-right', '10px').click(function(){
				og.openLink(og.getUrl('template', 'remove_repetition_from_inconsistent_template_tasks'), {post: {
					template_id: template_id
				}});
				$('.modal-close').click();
			});
	    }
	};
	setTimeout(function() {
		$.modal(div, modal_params);
	}, 100);
	
}

og.addObjectToTemplate = function(target, obj, dont_draw_milestone_combo) {
	target = $('#'+target);
	
	var count = 0;
	count = target.children('[id^= objectDiv]').length;
	
	
	var div = document.createElement('div');
	div.id = 'template-object' + obj.object_id;
	div.className = "template-add-template-object ico-" + obj.type;
	
	div.innerHTML =
		'<input class="objectID" type="hidden" name="objects[' + obj.object_id + ']" value="' + obj.object_id + '" />' +
		'<div style="float: left;" class="db-ico '+obj.ico+'"></div>' +
		'<div class="template-text-ellipsis">' +
			'<a id="template-object-name' + obj.object_id + '" href="#" class="internalLink" onclick="og.viewTempObj('+obj.object_id+',  \''+obj.type+'\')">'+og.clean(obj.name)+'</a>' +
			(parseInt(obj.is_repetitive) > 0 ? '&nbsp;<span class="link-ico ico-recurrent"></span>' : '') +
		'</div>';
	var divActions = document.createElement('div');
	divActions.className = "template-object-actions";

	var divhtml =
		'<a id="toggleTemplateProperties'+obj.object_id+'" href="#" onclick="og.toggleTemplateProperties('+obj.object_id+')" class="toggle_collapsed">'+ lang("edit variables") + '</a>'+
		"<a href='#' class='internalLink coViewAction ico-edit' onclick='"+"og.editTempObj("+obj.object_id+", \""+obj.type+"\")"+"'>" + lang('edit') + '</a>';
	if (obj.type == 'template_task') {
		divhtml += '<a href="#" onclick="og.render_modal_form(\'\', {c:\'task\', a:\'add_task\', params:{template_task:1, parent_id:'+ obj.object_id +', template_id:'+og.actual_template_id+'}})" class="internalLink ico-add coViewAction">'+lang("add sub task")+'</a>';
	} else if (obj.type == 'template_milestone') {
		divhtml += '<a href="#" onclick="og.openLink(og.getUrl(\'task\', \'add_task\', {template_task:1, milestone_id:'+ obj.object_id +', template_id:'+og.actual_template_id+'}), {caller:\'new_task_template\'})" class="internalLink ico-add coViewAction">'+lang("add task")+'</a>';
	}
	
	divhtml += '<a href="#" onclick="og.removeObjectFromTemplate(this.parentNode, ' + obj.object_id + ')" class="internalLink coViewAction ico-delete">'+lang('delete')+'</a>';
	
	divActions.innerHTML = divhtml;
	//if(obj.sub_tasks.length > 0){
		var subtasksExpander = document.createElement('div');
		subtasksExpander.id = 'subtasksExpander' + obj.object_id;
		subtasksExpander.className = "template-subtasksExpander";
		subtasksExpander.innerHTML = '<a href="#" onclick="og.toggleTemplateSubtasks('+obj.object_id+')" class="toggle_collapsed"></a>';
		subtasksExpander.style.display="none"; 
		div.insertBefore(subtasksExpander,div.firstChild);
	//}
	
	og.eventManager.fireEvent('more template object actions', {obj: obj, actions_div: divActions});
	
	var subTasksDiv = document.createElement('fieldset');
	subTasksDiv.id = 'subTasksDiv' + obj.object_id;
	subTasksDiv.className = "template-subtasks-div ";
	subTasksDiv.style.display = 'none';
	subTasksDiv.innerHTML =	'<legend class="template-text-ellipsis" style="max-width: 450px;">Subtasks of '+og.clean(obj.name)+'</legend>';	
	
	var editPropDiv = document.createElement('div');
	editPropDiv.id = 'propDiv' + obj.object_id;
	editPropDiv.style.paddingLeft = '30px';
	
	var addTempObjProp = document.createElement('a');
	if (obj.type == 'template_task') {
		addTempObjProp.innerHTML = '<a href="#" onclick="og.addTemplateObjectProperty(' + obj.object_id + ',' + obj.object_id + ',\'\', \'\')" class="link-ico ico-add">'+ lang('add a variable to this task') + '</a>';
	} else if (obj.type == 'template_milestone') {
		addTempObjProp.innerHTML = '<a href="#" onclick="og.addTemplateObjectProperty(' + obj.object_id + ',' + obj.object_id + ',\'\', \'\')" class="link-ico ico-add">'+ lang('add a variable to this milestone') + '</a>';
	}
	var propertyDiv = document.createElement('fieldset');
	propertyDiv.id = 'propertyDiv'+ obj.object_id;	
	propertyDiv.className = 'template-property-div';
	propertyDiv.innerHTML =	'<legend>Variables</legend>';	
	propertyDiv.style.display = 'none';
	propertyDiv.appendChild(editPropDiv);
	propertyDiv.appendChild(addTempObjProp);
	
	var objectDiv = document.createElement('div');
	objectDiv.id = 'objectDiv' + obj.object_id;
			
	// If the task is not a subtask of any other task and does not belong to any milestone, then it is a root task for the list
	let is_root = obj.parent_id == 0 && obj.milestone_id == 0;
	
	// this line adds the following classes to the object div:
	// - template-object-div
	// - odd or pair depending on the count (to alternate colors)
	// - root if the task is a root task (i.e. it is not a subtask of any other task and does not belong to any milestone)
	objectDiv.className = "template-object-div" + (count % 2 ? " odd" : " pair") + (is_root ? " root" : "");
		
	objectDiv.appendChild(div);
	objectDiv.appendChild(divActions);
	objectDiv.appendChild(propertyDiv);
	
	var params = new Array();
	params["template_task"] = 1;
			
	objectDiv.appendChild(subTasksDiv);
	og.templateObjects.push(obj);
	
	target.append( objectDiv );
	
	if (!dont_draw_milestone_combo) {
		// if new object is a task and template has milestones -> add milestones combo.
		if (obj.type == 'task') {
			og.drawTemplateObjectMilestonesCombo(objectDiv, obj);
		}
		
		// if new object is a milestone -> refresh milestones combo for all tasks.
		if (obj.type == 'milestone') {
			for (k=0; k<og.templateObjects.length; k++) {
				if (og.templateObjects[k].type == 'task') {
					og.drawTemplateObjectMilestonesCombo(Ext.get(og.add_template_input_divs[og.templateObjects[k].object_id]).dom, og.templateObjects[k]);
				}
			}
		}
	}
	
	//expand container if is subtask or is under a milestone
	if(obj.milestone_id && !obj.parent_id){	
		if(target.css('display') == 'none'){
			og.toggleTemplateSubtasks(obj.milestone_id);		
		}
	}else if(obj.parent_id){
		if(target.css('display') == 'none'){
			og.toggleTemplateSubtasks(obj.parent_id);	
		}		
	}		
};

og.toggleTemplateSubtasks = function(object_id){
	$('[id^=subTasksDiv' + object_id +']').toggle();
	$('[id^=subtasksExpander' + object_id +'] a').toggleClass('toggle_collapsed');
	$('[id^=subtasksExpander' + object_id +'] a').toggleClass('toggle_expanded');		
	
}

og.toggleTemplateProperties = function(object_id){
	$('[id^=propertyDiv' + object_id +']').toggle();
	$('[id^=toggleTemplateProperties' + object_id +'] a').toggleClass('toggle_collapsed');
	$('[id^=toggleTemplateProperties' + object_id +'] ').toggleClass('toggle_expanded');		
}

og.removeObjectFromTemplate = function(div, obj_id) {
	var parent = div.parentNode.parentNode;
	var ob_type = "template_task";
	//search ob type
	var removed_object = [];
	for(var k=0; k < og.templateObjects.length; k++){
		if(og.templateObjects[k].object_id == obj_id ){
			removed_object = og.templateObjects.splice(k,1);
			break;
		}
	}
	
	//keep the milestone tasks
	if (removed_object.length > 0 && removed_object[0].type == 'template_milestone') {
		ob_type = "template_milestone";
		var milestoneTasksDiv = document.getElementById('subTasksDiv' + obj_id);
			
		var milestoneTasksDivs = $("#"+milestoneTasksDiv.getAttribute("id")).children("[id^='objectDiv']")
	}
	
	//remove
	var removeId = div.parentNode.id;
	parent.removeChild(div.parentNode);
		
	var inputs = parent.getElementsByTagName('input');
	var count = 0;
	for (var i=0; i < inputs.length; i++) {
		if(inputs[i].className == 'objectID'){
			inputs[i].name = 'objects[' + count + ']';
			if(removeId == 'objectDiv' + count){
				for(var h = count + 1; h < og.templateObjects.length; h++){
					var objDiv = document.getElementById('objectDiv' + h);
					objDiv.id = 'objectDiv' + (h - 1);
					var propDiv = document.getElementById('propDiv' + h);
					propDiv.id = 'propDiv' + (h - 1);
					var comboMilestones = document.getElementById('objectDiv'+ h +'-milestones-combo');
					if (comboMilestones) comboMilestones.id = 'objectDiv'+ (h - 1) +'-milestones-combo';
				}
			}
			count++;
		}
	}
	
	//add the milestone tasks to parent container
	if (ob_type == "template_milestone") {
		$("#"+parent.getAttribute("id")).append(milestoneTasksDivs);
	}
	
	//rebuild colors 
	$("#"+parent.getAttribute("id")).children("[id^='objectDiv']").each(function( index ) {
		if (index%2==0){
			$( this ).removeClass( "odd" );
			$( this ).addClass( "pair" );
		}else{
			$( this ).removeClass( "pair" );
			$( this ).addClass( "odd" );
		}
	});
	
	
		
};

og.templateObjectMouseOver = function() {
	var close = this.firstChild;
	while (close && close.className != 'removeDiv') {
		close = close.nextSibling;
	}
	if (close) {
		close.style.display = 'block';
	}
};

og.templateObjectMouseOut = function() {
	var close = this.firstChild;
	while (close && close.className != 'removeDiv') {
		close = close.nextSibling;
	}
	if (close) {
		close.style.display = 'none';
	}
};

og.addTemplateObjectProperty = function(obj_id, count, property, value, object_type_id){
	var propDiv = document.getElementById('propDiv' + count);
	var parent = propDiv.parentNode;
	var selects = parent.getElementsByTagName('select');
	var total = 0;
	for (var i=0; i < selects.length; i++) {
		if(selects[i].className == 'propertySelect'){
			var res = selects[i].id.split("[");			
			if(res.length > 2){
				var prop_id = parseInt(res[2].replace("]", ""));
				if(prop_id > total){
					total = prop_id;
				}
			}			
		}
	}
	total++;
	
	var html = '';
	html += '<table><tr><td id="deletePropTD' + total + '" style="padding-right:5px;"><div class="clico ico-delete" onclick="og.deleteTemplateObjectProperty(' + obj_id + ',\'' + total + '\')"></div></td>' +
		'<td><select class="propertySelect" style="display:none;" id="objectProperties[' + obj_id + '][' + total + ']" ' + 
		'name="objectProperties[' + obj_id + '][' + total + ']" onchange="og.objectPropertyChanged(' + obj_id + ',\'' + total + '\',\'\')" style="width:150px">' +
		'</select></td><td style="padding-left:10px;" id="propSel[' + obj_id + '][' + total + ']"></td></tr></table>';
	var newProp = document.createElement('div');
	newProp.id = 'propDiv[' + obj_id + '][' + total + ']';
	newProp.style.paddingLeft = '30px';
	newProp.style.paddingBottom = '10px';
	newProp.innerHTML += html;
	propDiv.parentNode.insertBefore(newProp, propDiv);

	og.requestAndRenderTemplateObjectPropertySelector(obj_id, total, value, property, object_type_id);
	
};


og.requestAndRenderTemplateObjectPropertySelector = function(obj_id, total, value, property, object_type_id) {
	// if properties already loaded then render the component directly
	if (object_type_id && og.template_obj_properties && og.template_obj_properties[object_type_id]) {
		
		og.renderTemplateObjectPropertySelector(obj_id, og.template_obj_properties[object_type_id], total, property, value, object_type_id);
		
	} else {
		// request the properties and render the component directly
		og.openLink(og.getUrl('template', 'get_object_properties', {id: obj_id}), {
			callback: function(success, data) {
				if (success) {
					og.renderTemplateObjectPropertySelector(obj_id, data, total, property, value);
				}
			}
		});
	}
}

// render the component for the property
og.renderTemplateObjectPropertySelector = function(obj_id, data, total, property, value) {
	var propSelection = '';
	if(data.properties.length > 0){
		var select = document.getElementById('objectProperties[' + obj_id + '][' + total + ']');
		select.innerHTML = "";
		var option = document.createElement('option');
		option.value = "";
		option.innerHTML = '-- ' + lang('select') + ' --';
		select.appendChild(option);
		for(var j=0; j < data.properties.length; j++){
			var prop = data.properties[j];
			option = document.createElement('option');
			option.className = prop.type;
			option.value = prop.id;
			if (prop.id == property) option.selected = "selected";
			option.innerHTML = prop.name;
			select.appendChild(option);
		}
		select.style.display = '';
		og.objectPropertyChanged(obj_id, total, value);
	}
}


og.deleteTemplateObjectProperty = function(obj_id, count){
	var propDiv = document.getElementById('propDiv[' + obj_id + '][' + count + ']');
	var parent = propDiv.parentNode;
	propDiv.parentNode.removeChild(propDiv);
	var selects = parent.getElementsByTagName('select');
	var total = 0;
	for (var i=0; i < selects.length; i++) {
		if(selects[i].className == 'propertySelect'){
			total++;
		}
	}
	for(var j=count+1; j <= total; j++){
		propDiv = document.getElementById('propDiv[' + obj_id + '][' + j + ']');
		propDiv.id = 'propDiv[' + obj_id + '][' + (j - 1) + ']';
		deletePropTD = document.getElementById('deletePropTD' + j);
		deletePropTD.id = 'deletePropTD' + (j - 1);
		deletePropTD.innerHTML = '<div class="clico ico-delete" onclick="og.deleteTemplateObjectProperty(' + obj_id + ',\'' + (j - 1) + '\')">';
		var objectPropSel = document.getElementById('objectProperties[' + obj_id + '][' + j + ']');
		objectPropSel.id = 'objectProperties[' + obj_id + '][' + (j - 1) + ']';
		objectPropSel.name = 'objectProperties[' + obj_id + '][' + (j - 1) + ']';
		objectPropSel.attributes["onchange"].value = 'og.objectPropertyChanged(' + obj_id + ',' + (j - 1) + ',\'\')';
		var propSelTD = document.getElementById('propSel[' + obj_id + '][' + j + ']');
		if(propSelTD != null){
			propSelTD.id = 'propSel[' + obj_id + '][' + (j - 1) + ']';
			var datePropType = document.getElementById('datePropType[' + obj_id + '][' + j + ']');
			if(datePropType != null){
				datePropType.id = 'datePropType[' + obj_id + '][' + (j - 1) + ']';
				datePropType.attributes["onchange"].value = 'og.datePropertyTypeSel(' + (j - 1) + ',' + obj_id + '\')';
				var datePropTD = document.getElementById('datePropTD[' + obj_id + '][' + j + ']');
				if(datePropTD != null){
					datePropTD.id = 'datePropTD[' + obj_id + '][' + (j - 1) + ']';
				}
			}
		}
		
	}
};

og.objectPropertyChanged = function(obj_id, count, value){
	var propertySel = document.getElementById('objectProperties[' + obj_id + '][' + count + ']');
	var selectedPropIndex = propertySel.selectedIndex; 
	if(selectedPropIndex != -1){
		var prop = propertySel[selectedPropIndex];
		var propValueTD = document.getElementById('propSel[' + obj_id + '][' + count + ']');
		var datePropTD = document.getElementById('datePropTD[' + obj_id + '][' + count + ']');
		if (datePropTD != null) datePropTD.innerHTML = '';
		var numericPropTD = document.getElementById('numericPropTD[' + obj_id + '][' + count + ']');
		if (numericPropTD != null) numericPropTD.innerHTML = '';
		var timePropTD = document.getElementById('timePropTD[' + obj_id + '][' + count + ']');
		if (timePropTD != null) timePropTD.innerHTML = '';
		
		var id = 0;
		var propSel = document.getElementById('objectProperties[' + obj_id + '][' + id + ']');
		while(propSel != null){
			if(propSel.value == prop.value && selectedPropIndex != 0 && id != count){
				alert(lang('template property already selected'));
				propertySel.selectedIndex = 0;
				propValueTD.innerHTML = '';
				return;
			}
			propSel = document.getElementById('objectProperties[' + obj_id + '][' + ++id + ']');
		}
		if(prop.className == 'STRING'){
			propValueTD.innerHTML = ' <textarea id="propValues[' + obj_id + '][' + prop.value + ']" name="propValues[' + obj_id + '][' + prop.value + ']">' + value + '</textarea>&nbsp;' +
				'<a href="#" onclick="og.editStringTemplateObjectProperty(' + obj_id + ',\'' + prop.value + '\')">[' + lang('open property editor') + ']</a>';
		}else if(prop.className == 'DATETIME'){
			propValueTD.innerHTML = '<select style="display:none;" id="datePropType[' + obj_id + '][' + count + ']" onchange="og.datePropertyTypeSel(' + count + ',\'' + obj_id + '\')">'
				+ '<option value="-1">' + lang('select') + '</option><option value="0">' + lang('fixed date') + '</option><option value="1" selected="selected">' + lang('parametric date') + '</option></select>';
			
			var newTD = document.createElement('td');
			newTD.id = 'datePropTD[' + obj_id + '][' + count + ']';
			
			propValueTD.parentNode.appendChild(newTD);
			if(value != ''){
				
				var time_value = '';
				var splitted_value = value.split("|");
				value = splitted_value[0];
				if (splitted_value[1]) time_value = splitted_value[1];
				
				var datePropTypeSel = document.getElementById('datePropType[' + obj_id + '][' + count + ']');
				if(value.indexOf("+") != -1 || value.indexOf("-") != -1){
					var param = "";
					var posOp = value.indexOf("}") + 1;
					var operator = value.substring(posOp, posOp + 1);
					
					param = value.substring(0, posOp);
					amount = value.substring(posOp + 1, value.length - 1);
					unit = value.substring(value.length - 1);
					datePropTypeSel.selectedIndex = 2;
					og.datePropertyTypeSel(count, obj_id, time_value, param);
					var paramSel = document.getElementById('propValueParam[' + obj_id + '][' + prop.value + ']');
					for(var i=0; i < paramSel.length; i++){
						var paramName = '{' + paramSel.options[i].value + '}';
						if(paramName == param){
							paramSel.selectedIndex = i;
						}
					}
					paramSel.style.display = '';
					var opSel = document.getElementById('propValueOperation[' + obj_id + '][' + prop.value + ']');
					for(i=0; i < opSel.length; i++){
						var op = opSel.options[i].value;
						if(op == operator){
							opSel.selectedIndex = i;
						}
					}

					if (og.override_template_date_amount_input_init && typeof(og.override_template_date_amount_input_init) == 'function') {

						og.override_template_date_amount_input_init.call(null, obj_id, prop, amount);

					} else {
						var amountInput = document.getElementById('propValueAmount[' + obj_id + '][' + prop.value + ']');
						amountInput.value = amount;
					}

					var unitSel = document.getElementById('propValueUnit[' + obj_id + '][' + prop.value + ']');
					for(i=0; i < unitSel.length; i++){
						var u = unitSel.options[i].value;
						if(u == unit){
							unitSel.selectedIndex = i;
						}
					}
				}else{
					datePropTypeSel.selectedIndex = 1;
					og.datePropertyTypeSel(count, obj_id);
					var dateProp = document.getElementById('propValues[' + obj_id + '][' + prop.value + ']');
					dateProp.value = value; 
				}
			} else {
				og.datePropertyTypeSel(count, obj_id);
			}
		}else if(prop.className == 'USER'){
			propValueTD.innerHTML = '<select id="integerPropType[' + obj_id + '][' + count + ']" onchange="og.integerPropertyTypeSel(' + count + ',\'' + obj_id + '\')">'
				+ '<option value="-1">' + lang('select') + '</option><option value="0">' + lang('fixed user') + '</option><option value="1">' + lang('parametric user') + '</option></select>';
			var newTD = document.createElement('td');
			newTD.id = 'integerPropTD[' + obj_id + '][' + count + ']';
			newTD.style.paddingLeft = '10px';
			propValueTD.parentNode.appendChild(newTD);
			if(value != ''){
				var integerPropTypeSel = document.getElementById('integerPropType[' + obj_id + '][' + count + ']');
				if (value.indexOf("{") != -1) {
					integerPropTypeSel.selectedIndex = 2;
					og.integerPropertyTypeSel(count, obj_id);
					var paramSel = document.getElementById('propValueParam[' + obj_id + '][' + prop.value + ']');
					for(var i=0; i < paramSel.length; i++){
						var paramName = '{' + paramSel.options[i].value + '}';
						if (paramName == value) {
							paramSel.selectedIndex = i;
						}
					}
				} else {
					integerPropTypeSel.selectedIndex = 1;
					og.integerPropertyTypeSel(count, obj_id, function() {
						var propSel = document.getElementById('propValues[' + obj_id + '][' + prop.value + ']');
						for(var i=0; i < propSel.length; i++){
							var propVal = propSel.options[i].value;
							if (propVal == value) {
								propSel.selectedIndex = i;
							}
						}
					});
				}
			}
		} else if (prop.className == 'INTEGER' || prop.className == 'FLOAT') {
			
			var newTD = document.createElement('td');
			newTD.id = 'numericPropTD[' + obj_id + '][' + count + ']';
			
			propValueTD.parentNode.appendChild(newTD);

			og.numericPropertyTypeSel(count, obj_id, value);

		} else if (prop.className == 'TIME') {
			
			var newTD = document.createElement('td');
			newTD.id = 'timePropTD[' + obj_id + '][' + count + ']';
			
			propValueTD.parentNode.appendChild(newTD);

			og.timePropertyTypeSel(count, obj_id, value);

		}else{
			propValueTD.innerHTML = '';
		}
	}
};

og.datePropertyTypeSelOnChange = function(input, obj_id, prop) {
	var display = $(input).val() == 'task_creation' ? '' : 'none';
	//$('#prop_value_time_container_'+ obj_id +'_'+ prop).css('display', display);
}

og.datePropertyTypeSel = function(count, obj_id, value_time, sel_param){
	
	var datePropTD = document.getElementById('datePropTD[' + obj_id + '][' + count + ']');
	var datePropTypeSel = document.getElementById('datePropType[' + obj_id + '][' + count + ']');
	var selectedPropTypeIndex = datePropTypeSel.selectedIndex;
	datePropTD.innerHTML = '';
	if(selectedPropTypeIndex != -1){
		var propertySel = document.getElementById('objectProperties[' + obj_id + '][' + count + ']');
		var prop = propertySel[propertySel.selectedIndex].value;
		var type = datePropTypeSel[selectedPropTypeIndex].value;
		if(type == 0){
			var dateProp = new og.DateField({
				renderTo: datePropTD,
				name: 'propValues[' + obj_id + '][' + prop + ']',
				id: 'propValues[' + obj_id + '][' + prop + ']'
			});
		}else if(type == 1){
			var selectParam = '';
			
			selectParam = '<select name="propValueParam[' + obj_id + '][' + prop + ']" id="propValueParam[' + obj_id + '][' + prop + ']" onchange="og.datePropertyTypeSelOnChange(this,\''+obj_id+'\',\''+prop+'\');">';
			for(var j=0; j < og.templateParameters.length; j++){
				var item = og.templateParameters[j];
				if(og.templates.datetypes.indexOf(item.type) != -1){
					selectParam += '<option value="'+ item['name'] +'">' + item['name'] + '</option>';
				}
			}
			selectParam += '<option value="task_creation">' + lang('date of task creation') + '</option>';
			selectParam += '</select>';
			var html = '= &nbsp;<input type="hidden" name="propValues[' + obj_id + '][' + prop + ']">' + 
				selectParam + '&nbsp;<select name="propValueOperation[' + obj_id + '][' + prop + ']" id="propValueOperation[' + obj_id + '][' + prop + ']">' +
				'<option>+</option><option>-</option></select>&nbsp;';

			var amount_html = '';
			if (og.override_template_date_amount_input && typeof(og.override_template_date_amount_input) == 'function') {
				amount_html = og.override_template_date_amount_input.call(null, obj_id, prop);
			} else {
				amount_html = '<input name="propValueAmount[' + obj_id + '][' + prop + ']" id="propValueAmount[' + obj_id + '][' + prop + ']" style="width:50px;" type="number" step="any" />';
			}
			html += amount_html;

			html += '&nbsp;<select name="propValueUnit[' + obj_id + '][' + prop + ']" id="propValueUnit[' + obj_id + '][' + prop + ']">' + 
				'<option value="i">'+ lang('minutes') + '</option><option value="d" selected="selected">'+ lang('days') + '</option>' +
				'<option value="w">'+ lang('weeks') + '</option><option value="m">'+ lang('months') + '</option></select>';
			
			if (og.config.use_time_in_task_dates) {
				//html += '&nbsp;<span id="prop_value_time_container_'+ obj_id +'_'+ prop +'"></span>';
			}
			
			datePropTD.innerHTML = html;
			
			if (og.config.use_time_in_task_dates) {
				if (!value_time) value_time = '';
				/*
				var dueTime = new Ext.form.TimeField({
					renderTo:'prop_value_time_container_'+ obj_id +'_'+ prop,
					id: 'propValueTime[' + obj_id + '][' + prop + ']',
					name: 'propValueTime[' + obj_id + '][' + prop + ']',
					width: 80,
					format: (og.preferences['time_format_use_24'] == 1 ? 'G:i' : 'g:i A'),
					emptyText: 'hh:mm',
					value: value_time
									*/
				//});
				//$('#prop_value_time_container_'+ obj_id +'_'+ prop +' .x-form-field-wrap').css('display', 'inline-block').css('vertical-align','top');

				og.datePropertyTypeSelOnChange(document.getElementById('propValueParam[' + obj_id + '][' + prop + ']'), obj_id, prop);
			}
		}
	}
};



og.numericPropertyTypeSel = function(count, obj_id, value) {

	if (og.override_template_numeric_property_selector && typeof(og.override_template_numeric_property_selector) == 'function') {
		og.override_template_numeric_property_selector.call(null, count, obj_id, value);
		return;
	}
	
	var numericPropTD = document.getElementById('numericPropTD[' + obj_id + '][' + count + ']');
	
	numericPropTD.innerHTML = '';
	
	var propertySel = document.getElementById('objectProperties[' + obj_id + '][' + count + ']');
	var prop = propertySel[propertySel.selectedIndex].value;
	
	var amount = '';
	var param = "";
	var operator = "+";
	// parse the already defined value if exists
	if(value.indexOf("+") != -1 || value.indexOf("-") != -1){
		var posOp = 0;
		if(value.indexOf("+") != -1){
			posOp = value.indexOf("+");
		}
		if (value.indexOf("-") != -1){
			posOp = value.indexOf("-");
			operator = "-";
		}
		param = value.substring(0, posOp);
		amount = value.substring(posOp + 1);
	}
	
	// selectbox with numeric variables
	selectParam = '<select name="propValueParam[' + obj_id + '][' + prop + ']" id="propValueParam[' + obj_id + '][' + prop + ']" >';
	for (var j=0; j < og.templateParameters.length; j++) {
		var item = og.templateParameters[j];
		if (item.type.toUpperCase() == "NUMERIC") {
			var param_selected = param == '{'+item['name']+'}' ? 'selected="selected"' : '';
			selectParam += '<option value="'+ item['name'] +'" '+ param_selected +'>' + item['name'] + '</option>';
		}
	}
	selectParam += '</select>';

	var html = '= &nbsp;<input type="hidden" name="propValues[' + obj_id + '][' + prop + ']">' + selectParam + '&nbsp;';

	// operator selctor
	html += '<select name="propValueOperation[' + obj_id + '][' + prop + ']" id="propValueOperation[' + obj_id + '][' + prop + ']">' +
		'<option '+ (operator == "+" ? 'selected="selected"' : '') +'>+</option>' +
		'<option '+ (operator == "-" ? 'selected="selected"' : '') +'>-</option>' +
		'</select>&nbsp;';

	// amount input
	html += '<input type="number" step="any" name="propValueAmount[' + obj_id + '][' + prop + ']" id="propValueAmount[' + obj_id + '][' + prop + ']" style="width:50px;" value="'+amount+'"/>';
		
	numericPropTD.innerHTML = html;
	
};


og.timePropertyTypeSel = function(count, obj_id, value) {

	var timePropTD = document.getElementById('timePropTD[' + obj_id + '][' + count + ']');
	
	timePropTD.innerHTML = '';
	
	var propertySel = document.getElementById('objectProperties[' + obj_id + '][' + count + ']');
	var prop = propertySel[propertySel.selectedIndex].value;
	
	var param = "";
	var var_unit = "h";
	var operator = "+";
	var amount = "";
	var unit = "h";
	// parse the already defined value if exists
	if(value.indexOf("+") != -1 || value.indexOf("-") != -1){
		var posOp = 0;
		if(value.indexOf("+") != -1){
			posOp = value.indexOf("+");
		}
		if (value.indexOf("-") != -1){
			posOp = value.indexOf("-");
			operator = "-";
		}
		param = value.substring(0, posOp - 1);
		var_unit = value.substring(posOp - 1, posOp);
		amount = value.substring(posOp + 1, value.length - 1);
		unit = value.substring(value.length - 1);
	}

	// value = "[var][unit][operator][amount][amount_unit]"
	// example: "{var1}d+60i" => var1 days + 60 minutes
	
	// selectbox with numeric variables
	selectParam = '<select name="propValueParam[' + obj_id + '][' + prop + ']" id="propValueParam[' + obj_id + '][' + prop + ']" >';
	for (var j=0; j < og.templateParameters.length; j++) {
		var item = og.templateParameters[j];
		if (item.type.toUpperCase() == "NUMERIC") {
			var param_selected = param == '{'+item['name']+'}' ? 'selected="selected"' : '';
			selectParam += '<option value="'+ item['name'] +'" '+ param_selected +'>' + item['name'] + '</option>';
		}
	}
	selectParam += '</select>';

	var html = '= &nbsp;<input type="hidden" name="propValues[' + obj_id + '][' + prop + ']">' + selectParam + '&nbsp;';

	// variable unit selector
	html += og.buildTimeUnitSelectorHtml(obj_id, prop, var_unit, 'propValueVarUnit');

	// operator selctor
	html += '<select name="propValueOperation[' + obj_id + '][' + prop + ']" id="propValueOperation[' + obj_id + '][' + prop + ']">' +
		'<option '+ (operator == "+" ? 'selected="selected"' : '') +'>+</option>' +
		'<option '+ (operator == "-" ? 'selected="selected"' : '') +'>-</option>' +
		'</select>&nbsp;';

	// amount input
	html += '<input type="number" step="any" name="propValueAmount[' + obj_id + '][' + prop + ']" id="propValueAmount[' + obj_id + '][' + prop + ']" style="width:50px;" value="'+amount+'"/>&nbsp;';

	// amount unit selector
	html += og.buildTimeUnitSelectorHtml(obj_id, prop, unit, 'propValueUnit');
		
	timePropTD.innerHTML = html;
}

og.buildTimeUnitSelectorHtml = function(obj_id, prop, unit, field_name) {

	return '<select name="'+ field_name +'[' + obj_id + '][' + prop + ']" id="'+ field_name +'[' + obj_id + '][' + prop + ']">' +
		'<option value="i" '+ (unit == "i" ? 'selected="selected"' : '') +'>'+ lang('minutes') + '</option>' +
		'<option value="h" '+ (unit == "h" ? 'selected="selected"' : '') +'>'+ lang('hours') + '</option>' +
		'<option value="d" '+ (unit == "d" ? 'selected="selected"' : '') +'>'+ lang('days') + '</option>' +
		'<option value="w" '+ (unit == "w" ? 'selected="selected"' : '') +'>'+ lang('weeks') + '</option>' +
	'</select>&nbsp;';
}


og.renderAssignedToTemplatePropertySelector = function(companies, obj_id, count, prop, callback) {
	var integerPropTD = document.getElementById('integerPropTD[' + obj_id + '][' + count + ']');
	var html = "";
	html += '<select name="propValues[' + obj_id + '][' + prop + ']" id="propValues[' + obj_id + '][' + prop + ']">';
	if (companies.length > 0){
		for (var i=0; i<companies.length; i++) {
			if (!companies[i]) continue;
			html += '<option value="'+ companies[i].id+'" name="propValue[' + obj_id + '][' + prop + ']">'+ companies[i].name + '</option>';
			var users = companies[i].users;
			for(j=0; j<users.length; j++){
				var usu = users[j];
				if (usu.id == 'undefined') continue;
				html += '<option value="'+ usu.id+'" name="propValue[' + obj_id + '][' + prop + ']">'+ usu.name + '</option>';
			}
		}
	}else{
		html += '<option value="0" name="propValue[' + obj_id + '][' + prop + ']">'+ lang ('no users to display') + '</option>';
	}
	
	html += '</select>';
	integerPropTD.innerHTML = html;
	if (typeof callback == 'function') callback();
}


og.integerPropertyTypeSel = function(count, obj_id, callback){	
	var integerPropTD = document.getElementById('integerPropTD[' + obj_id + '][' + count + ']');
	var integerPropTypeSel = document.getElementById('integerPropType[' + obj_id + '][' + count + ']');
	var selectedPropTypeIndex = integerPropTypeSel.selectedIndex;
	integerPropTD.innerHTML = '';
	if(selectedPropTypeIndex != -1){
		var propertySel = document.getElementById('objectProperties[' + obj_id + '][' + count + ']');
		var prop = propertySel[propertySel.selectedIndex].value;
		var type = integerPropTypeSel[selectedPropTypeIndex].value;		
		if(type == 0){
			
			if (og.template_allowed_users_to_assign) {
				
				var companies = og.template_allowed_users_to_assign;
				og.renderAssignedToTemplatePropertySelector(companies, obj_id, count, prop, callback);
				
			} else {
				og.openLink(og.getUrl('task', 'allowed_users_to_assign', {for_template_var:1}), {
					callback: function(success, data) {
						og.template_allowed_users_to_assign = data.companies;
						var companies = og.template_allowed_users_to_assign;
						
						og.renderAssignedToTemplatePropertySelector(companies, obj_id, count, prop, callback);
					}
				});
			}
			
		}else if(type == 1){
			var selectParam = '';
			if(og.templateParameters.length > 0){
				selectParam = '<input type="hidden" name="propValues[' + obj_id + '][' + prop + ']" id="propValues[' + obj_id + '][' + prop + ']" value="" />'
				selectParam += '<select name="propValueParam[' + obj_id + '][' + prop + ']" id="propValueParam[' + obj_id + '][' + prop + ']" onChange="og.changeIntegerParam(this)" />';
				for(var j=0; j < og.templateParameters.length; j++){
					var item = og.templateParameters[j];
					if(item.type == "user"){
						// value="{'+ item['name'] +'}" name="propValue[' + obj_id + '][' + prop + ']"
						selectParam += '<option value="'+ item['name'] +'">' + item['name'] + '</option>';
					}
				}
				selectParam += '</select>';
				integerPropTD.innerHTML = selectParam;
			}
			if(selectParam == ''){
				selectParam = '<input type="hidden" name="propValues[' + obj_id + '][' + prop + ']" id="propValues[' + obj_id + '][' + prop + ']" value="" />'
				selectParam += '<select name="propValueParam[' + obj_id + '][' + prop + ']" id="propValueParam[' + obj_id + '][' + prop + ']" onChange="og.changeIntegerParam(this)" />';
				selectParam += '</select>';
				integerPropTD.innerHTML = selectParam;
				integerPropTypeSel.selectedIndex = 0;
			}
		}
	}
};

og.changeIntegerParam = function(select){
	var id = select.id();
	id = id.repace("propValueParam","propValues");
	hidd = document.getElementById(id);
	if (hidd){
		hidd.value = select.value;
	}
	
}

og.editStringTemplateObjectProperty = function(obj_id, prop, value_field_id){
	var params = [];
	for(var j=0; j < og.templateParameters.length; j++){
		var item = og.templateParameters[j];
		if(item.type == "string" || item.type == "numeric"){
			params.push([item['name']]);
		}
	}
	if (typeof value_field_id == 'undefined') {
		var valueField = document.getElementById('propValues[' + obj_id + '][' + prop + ']');
	} else {
		var valueField = document.getElementById(value_field_id);
	}
	var config = {
			genid: Ext.id(),
			title: lang('edit object property'),
			height: 350,
			width: 350,
			labelWidth: 80,
			ok_fn: function() {
				var value = Ext.getCmp('propValue').getValue();
				valueField.value = value;
				og.ExtendedDialog.hide();
			},
			dialogItems:[
			    {
			    	xtype: 'combo',
			    	fieldLabel: lang('template parameters'),
			    	id: 'paramSel',
			    	mode: 'local',
			    	width: 100,
			    	editable: false,
			    	forceSelection: true,
			    	triggerAction: 'all',
			    	displayField: 'name',
			    	valueField: 'name',
			    	store: new Ext.data.SimpleStore({
						fields: ['name'],
						data : params
					})
			    },
			    {
			    	xtype: 'button',
			    	text: lang('add'),
			    	id: 'addParamterBtn',
			    	iconCls: 'ico-add',
			    	listeners: {
			    		click: function(button, event){
			    			var param = '{' + Ext.getCmp('paramSel').getValue() + '}';
			    			Ext.getCmp('propValue').setValue(Ext.getCmp('propValue').getValue() + param);
			    		}
			    	}
			    },
			    {
			    	xtype: 'textarea',
			    	width: 280,
			    	height: 200,
			    	id: 'propValue',
			    	hideLabel: true,
			    	listeners: {
			    		render: function(textarea){
			    			textarea.setValue(valueField.value);
			    		}
			    	}
			    }
			]
		};
		og.ExtendedDialog.show(config);
};

og.promptAddParameter = function(before, edit, pos, config) {
	var for_task_templates = true;
	if (config) {
		var ok_function = config.ok_function;
		var prev_name = config.prev_name;
		var prev_type = config.prev_type;
		for_task_templates = config.for_task_templates;
	}

	var paramName = document.getElementById('parameters[' + pos + '][name]');
	var paramType = document.getElementById('parameters[' + pos + '][type]');
	var loadName = '';
	var loadType = 'string';
	if(paramName != null){
		loadName = paramName.value;
		loadType = paramType.value;
	} else {
		if (prev_name) loadName = prev_name;
		if (prev_type) loadType = prev_type;
	}
	
	var variable_types = [['string', lang('text')],['numeric', lang('numeric')],['user', lang('user')],['date', lang('date')]];
	if (og.templates.more_var_types) {
		for (var i=0; i<og.templates.more_var_types.length; i++) {
			var t = og.templates.more_var_types[i];
			variable_types.push([t, lang(t)]);
		}
	}
	
	var dialog_items = [
	    {
	    	xtype: 'textfield',
	    	fieldLabel: lang('name'),
	    	id: 'paramName',
	    	value: loadName
	    },
	    {
	    	xtype: 'combo',
	    	fieldLabel: lang('type'),
	    	id: 'paramType',
	    	mode: 'local',
	    	width: 100,
	    	editable: false,
	    	disabled: edit,
	    	forceSelection: true,
	    	triggerAction: 'all',
	    	displayField: 'name',
	    	valueField: 'id',
	    	value: loadType,
	    	store: new Ext.data.SimpleStore({
				fields: ['id', 'name'],
				data : variable_types
			})
	    }
	];
	if (for_task_templates && og.templates && og.templates.more_param_prompt_items) {
		for (var i=0; i<og.templates.more_param_prompt_items.length; i++) {
			var item = og.templates.more_param_prompt_items[i];
			if (edit) {
				if (item.xtype == 'checkbox') {
					item.checked = $('#parameters_'+pos+'_'+item.name).val()=='1' ? 'checked' : '';
				} else {
					item.value = $('#parameters_'+pos+'_'+item.name).val();
				}
			}
			dialog_items.push(item);
		}
	}
	
	
	var config = {
		genid: Ext.id(),
		title: lang('add parameter'),
		height: 100 + (25 * dialog_items.length),
		width: 350,
		labelWidth: 50,
		ok_fn: function() {
			if (typeof ok_function == 'function') {
				// used by c_obj_templates
				var name = Ext.getCmp('paramName').getValue();
				var type = Ext.getCmp('paramType').getValue();
				/*var req_cmp = Ext.getCmp('paramIsRequired');
				var required = req_cmp ? req_cmp.getValue() : false;*/
				
				ok_function.call(null, pos, name, type);
				og.ExtendedDialog.hide();
				return;
			}
			var name = Ext.getCmp('paramName').getValue();
			if(typeof name == 'string') {
				name = name.trim();
			}
			if(name == ""){
				alert(lang('parameter name empty'));
				return;
			}
			if(og.parameterNameExistsInTemplate(before, name)){
				alert(lang('parameter name exists'));
				return;
			}
			var type = Ext.getCmp('paramType').getValue();

			// Add or edit param
			if(!edit) {
				og.addParameterToTemplate(before, name, type);
			} else {
				var oldname = paramName.value;
				for (var i=0; i < og.templateParameters.length; i++) {
					if (og.templateParameters[i].name == oldname) {
						og.templateParameters[i].name = name;
						
						// replace all selector options that contain this property with the new property name and value
						$('#templateConteiner.template option[value="'+oldname+'"]').each(function() {
							$(this).attr('value', name);
							$(this).html(name);
						});
						// replace all textarea variables that contain this property with the new property name
						$('#templateConteiner.template textarea').each(function() {
							let new_content = $(this).val().replace('{'+oldname+'}', '{'+name+'}');
							$(this).val(new_content);
						});
						
						break;
					}
				}
				paramName.value = name;
				var paramNameSpan = document.getElementById('paramName_' + pos);
				paramNameSpan.innerHTML = '<b>' + name + '</b>&nbsp;(' + lang(type) + ') ';
			}
			
			// Order template parameters by name
			og.templateParameters.sort(function(a, b) {
				return a.name.localeCompare(b.name);
			});

			// Retrieves container fieldset to update DOM
			const container = document.querySelector('[id$="add_template_parameters_div"]');
			const fieldset = container.querySelector('fieldset');
			const elements = container.querySelectorAll('div.og-add-template-object');
			const link = document.querySelector('a[id$="params"]');

			elements.forEach(element => {
				element.remove();
			});

			for (i = 0; i < og.templateParameters.length; i++) {
				var parameter = og.templateParameters[i];
				
				var div = document.createElement('div');
				div.className = "og-add-template-object";
				div.innerHTML =
				'<input type="hidden" name="parameters[' + i + '][name]" id="parameters[' + i + '][name]" value="' + parameter.name + '"/>&nbsp;' +
				'<input type="hidden" name="parameters[' + i + '][type]" id="parameters[' + i + '][type]" value="' + parameter.type + '"/>&nbsp;' +
				'<span class="name" id="paramName_' + i + '"><b>' + parameter.name + '</b>&nbsp;(' + lang(parameter.type) + ') </span>' +
				'<span id="editRemoveParam' + i + '" style="cursor:pointer;line-height:25px;position:absolute;right:0;top:0;"><a href="#" onclick="og.promptAddParameter(this, 1, ' + i + ')" >'+lang('edit')+'</a>' +
				'&nbsp;|&nbsp;<a href="#" onclick="og.removeParameterFromTemplate(this.parentNode.parentNode, \'' + parameter.name + '\')" class="removeParamDiv">'+lang('remove')+'</a></span>';
				fieldset.insertBefore(div, link)
			}

			if (og.templates.after_param_prompt_ok) {
				for (var i=0; i<og.templates.after_param_prompt_ok.length; i++) {
					var fn = og.templates.after_param_prompt_ok[i];
					if (typeof(fn) == 'function') {
						fn.call(null, pos);
					}
				}
			}
			
			og.ExtendedDialog.hide();
		},
		dialogItems: dialog_items
	};
	og.ExtendedDialog.show(config);
};

og.parameterNameExistsInTemplate = function(before, name){
	var parent = before.parentNode;
	var inputs = parent.getElementsByTagName('input');
	for (var i=0; i < inputs.length; i=i+2) {
		if(inputs[i].value == name){
			return true;
		}
	}
	return false;
};

og.addParameterToTemplate = function(params, name, type, default_value) {
	if (!default_value) default_value = '';
	var parent = params.parentNode;
	var count = parent.getElementsByTagName('input').length / 2;
	var div = document.createElement('div');
	div.className = "og-add-template-object";
	div.innerHTML =
		'<input type="hidden" name="parameters[' + count + '][name]" id="parameters[' + count + '][name]" value="' + name + '"/>&nbsp;' +
		'<input type="hidden" name="parameters[' + count + '][type]" id="parameters[' + count + '][type]" value="' + type + '"/>&nbsp;' +
		'<span class="name" id="paramName_' + count + '"><b>' + name + '</b>&nbsp;(' + lang(type) + ') </span>' +
		'<span id="editRemoveParam' + count + '" style="cursor:pointer;line-height:25px;position:absolute;right:0;top:0;"><a href="#" onclick="og.promptAddParameter(this, 1, ' + count + ')" >'+lang('edit')+'</a>' +
		'&nbsp;|&nbsp;<a href="#" onclick="og.removeParameterFromTemplate(this.parentNode.parentNode, \'' + name + '\')" class="removeParamDiv">'+lang('remove')+'</a></span>';
	parent.insertBefore(div, params);
	var param = [];
	param['name'] = name;
	param['type'] = type;
	param['default_value'] = default_value;
	og.templateParameters.push(param);
	
	og.eventManager.fireEvent('after add tempalte parameter', {param: param});
};

og.removeParameterFromTemplate = function(paramDiv, name) {
	const selects = document.querySelectorAll('select[id*="propValueParam"]');
	var found = false;

	var divsToRemove = [];
	// Removes variables using param that is deleted
	for (let i = 0; i < selects.length; i++) {
        var selectedValue = selects[i].value;
        if (selectedValue === name) {
			// store divs con "propDiv[][]"" to delete
			var containerDiv = selects[i].closest('div');
			if (containerDiv) {
                divsToRemove.push(containerDiv);
            }
            found = true;
        }
    }

	// if found variables with the parameter that is being deleted show warning modal, otherwise just delete parameter 
	if(found) {
		og.askUserToDeleteParameter(divsToRemove, name, paramDiv);
	} else {
		og.deleteParameter(paramDiv, name);
	}
};


og.askUserToDeleteParameter = function(divsToRemove, name, paramDiv) {

	var info = lang('confirm delete parameter');
	var div = document.createElement('div');
	div.style = "border-radius: 5px; background-color: #fff; padding: 10px; width: 500px;";
	var genid = Ext.id();
	
	div.innerHTML = '<div><label class="coInputTitle">'+lang('delete template parameter')+'</label></div>'+
	'<div id="'+genid+'_question">'+ info+'</div>'+
	'<div id="'+genid+'_buttons" style="text-align:center">'+
	'<button class="yes submit blue" style="width:40%;">'+lang('yes')+'</button>'+
	'<button class="no submit blue" style="width:40%;">'+lang('no')+'</button>'+
	'</div><div class="clear"></div>';

	var modal_params = {
		'escClose': false,
		'overlayClose': false,
		'closeHTML': '<a id="'+genid+'_close_link" class="modal-close" title="'+lang('close')+'" style="display:none;"></a>',
		'onShow': function (dialog) {
			$("#"+genid+"_close_link").addClass("modal-close-img");
			$("#"+genid+"_buttons").css('text-align', 'center').css('margin', '10px 0');
			$("#"+genid+"_question").css('margin', '10px 0');
			$("#"+genid+"_buttons button.yes").css('margin-right', '10px').click(function(){
				// Removes parameter and variables using it
				divsToRemove.forEach(divToRemove => divToRemove.remove());
				og.deleteParameter(paramDiv, name);
				$('.modal-close').click();
			});
			$("#"+genid+"_buttons button.no").css('margin-left', '10px').click(function(){
				$('.modal-close').click();
			});
	    }
	};
	setTimeout(function() {
		$.modal(div, modal_params);
	}, 100);
	
}

og.deleteParameter = function(paramDiv, name){
	var parent = paramDiv.parentNode;
	parent.removeChild(paramDiv);

	// Deletes param from og.templateParameters
	for(var j=0; j < og.templateParameters.length; j++){
		var param = og.templateParameters[j];
		if(param['name'] == name){
			break;
		}
	}
	og.templateParameters.splice(j,1);
	
	// Deletes the param option from all selects 
	const selects = document.querySelectorAll('select[id*="propValueParam"]');
	for (let i = 0; i < selects.length; i++) {
		var options = selects[i].options;
		for (let j = 0; j < options.length; j++) {
			if (options[j].value === name) {
				selects[i].remove(j);
				break;
			}
		}
	}
	og.eventManager.fireEvent('after remove tempalte parameter', {param: param});
};

og.templateConfirmSubmit = function(genid) {
	var div = document.getElementById(genid + "add_template_objects_div");
	var count = div.getElementsByTagName('input').length;
	if (count == 0) {
		return confirm(lang('confirm template with no objects'));
	}
	return true;
};