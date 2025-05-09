<?php
require_javascript ( 'og/modules/memberListView.js' );
require_javascript ( "og/Permissions.js" );

if (! isset ( $genid )) $genid = gen_id ();
if (! isset ( $name )) $name = 'permissions';
if (! isset ( $pg_id )) $pg_id = -1;

$default_user_permissions = DB::executeAll("SELECT * FROM ".TABLE_PREFIX."role_object_type_permissions WHERE object_type_id NOT IN (SELECT id FROM ".TABLE_PREFIX."object_types WHERE name IN ('template','comment'))");
$default_user_permissions_by_role = array();
foreach ($default_user_permissions as $perm_info) {
	if (!isset($default_user_permissions_by_role[$perm_info['role_id']])) $default_user_permissions_by_role[$perm_info['role_id']] = array();
	$default_user_permissions_by_role[$perm_info['role_id']][$perm_info['object_type_id']] = array('d' => $perm_info['can_delete'], 'w' => $perm_info['can_write'], 'r' => "1");
}
?>

<input id="<?php echo $genid ?>hfPerms" type="hidden" value="<?php echo str_replace('"',"'", json_encode($member_permissions));?>" />
<input id="<?php echo $genid ?>hfAllowedOT" type="hidden" value="<?php echo str_replace('"',"'", json_encode($allowed_object_types));?>" />
<input id="<?php echo $genid ?>hfAllowedOTbyMemType" type="hidden" value="<?php echo str_replace('"',"'", json_encode($allowed_object_types_by_member_type));?>" />
<input id="<?php echo $genid ?>hfMemTypes" type="hidden" value="<?php echo str_replace('"',"'", json_encode($member_types));?>" />
<input id="<?php echo $genid ?>hfPermsSend" name="<?php echo $name ?>" type="hidden" value="" />
<input id="<?php echo $genid ?>hfPermsApplyToAll" name="apply_to_all_<?php echo $name ?>" type="hidden" value="" />
<input id="<?php echo $genid ?>hfPermsApplyToSubmembers" name="apply_to_submembers_<?php echo $name ?>" type="hidden" value="" />
<input id="<?php echo $genid ?>hfAdditionalPermsSend" name="additional_<?php echo $name ?>" type="hidden" value="" />
<input id="<?php echo $genid ?>hfPgId" name="hfPgId" type="hidden" value="<?php echo $pg_id?>" />

<?php 
	$display_permissions = true; 
	if (config_option('give_member_permissions_to_new_users') && isset($is_new_user) && $is_new_user) {
		$display_permissions = false; 
?>
<input type="checkbox" id="<?php echo $genid?>_set_manual_permissions_checkbox" onchange="og.toggleManualPermissions('<?php echo $genid?>', <?php echo (array_var($_REQUEST, 'modal')?'1':'0')?>);" style="float:left;cursor:pointer;margin-top:4px;margin-right:5px;"/>
<label for="<?php echo $genid?>_set_manual_permissions_checkbox" style="float: left;" class="checkbox"><?php echo lang('set manual permissions')?></label>
<div class="desc" style="float:left;margin:1px 0 1px 10px;" id="<?php echo $genid?>_manual_perm_help"><?php echo lang('set manual permissions desc')?></div>
<input type="hidden" id="<?php echo $genid?>_set_manual_permissions" name="manual_permissions_setted" value="0" />
<div class="clear"></div>
<?php } ?>

<div id="<?php echo $genid?>_dimension_permissions" class="user-dimension-permissions" style="<?php echo $display_permissions ? "" : "display:none;" ?>">
<?php

foreach ( $dimensions as $dimension ) {
	
	$class = $dimension->getIsManageable() ? 'toggle_expanded' : 'toggle_collapsed';
	$expand = $dimension->getIsManageable() ? 'false' : 'true';
	?>
<fieldset>
	<legend>
		<span class="og-task-expander <?php echo $class?>" style="padding-left: 20px;" title="<?php echo lang('expand-collapse') ?>" id="<?php echo $genid?>expander<?php echo $dimension->getId()?>"
			onclick="og.dimensionTreeDoLayout('<?php echo $genid?>', '<?php echo $dimension->getId()?>');og.editMembers.expandCollapseDim('<?php echo $genid?>dimension<?php echo $dimension->getId()?>', <?php echo $expand?>);"><?php echo $dimension->getName()?></span>
	</legend>
	<div id="<?php echo $genid?>dimension<?php echo $dimension->getId()?>" style="<?php echo $dimension->getIsManageable() ? '' : 'display:none;'?>">
	
	<table><tr><td style="padding:10px;">
		<h1><?php echo lang('user has permissions in')?>:</h1>
<?php
	$forced_members_param = "";
	if (isset($is_new_user) && $is_new_user) {
		$forced_members_param = "&new_user=1";
		?>
		<input type="hidden" name="forced_members"
			id="<?php echo $genid?>forced_members_<?php echo $dimension->getId()?>" 
			value="<?php echo json_encode(array_keys($member_permissions)); ?>">
		<?php
	} 
	// tree with members where user has permissions
	echo render_single_dimension_tree ( $dimension, $genid, null, array( 
		'select_root' => false, 
		'component_id' => $genid . '_with_permissions_' . $dimension->getId(), 
		'dont_load' => !$dimension->getIsManageable(),
		'loadUrl' => 'index.php?c=dimension&a=dimension_tree_for_permissions&ajax=true&dimension_id='.$dimension->getId().'&only_with_perm=1&pg='.$pg_id."$forced_members_param",
		'loadAdditionalParameters' => array($genid."forced_members_".$dimension->getId()),
		'enableDD' => true, 
		'ddGroup' => $genid.'_dimension_'.$dimension->getId(), 
		'width' => '300',
		'all_members' => false,
		'root_node_text' => $dimension->getName(),//lang('All'),
		'get_childs_params' => array('pg_id' => $pg_id, 'with_permissions' => '1'),
		//'use_ajax_member_tree' => true,
	));
?>
		<div class="desc" style="width:300px;"><?php echo lang('drag to right to remove permissions')?></div>
		
	</td><td style="padding:10px;">
		<h1><?php echo lang('user doesnt have permissions in')?>:</h1>
<?php 
	$excluded_members_param = "";
	if (isset($is_new_user) && $is_new_user) {
		$excluded_members_param = "&new_user=1";
		?>
		<input type="hidden" name="excluded_members" 
			id="<?php echo $genid?>excluded_members_<?php echo $dimension->getId()?>" 
			value="<?php echo json_encode(array_keys($member_permissions)); ?>">
		<?php
	}
	// tree with members where user doesn't have permissions
	echo render_single_dimension_tree ( $dimension, $genid, null, array(
		'select_root' => false, 
		'component_id' => $genid . '_without_permissions_' . $dimension->getId(), 
		'dont_load' => !$dimension->getIsManageable(),
		'loadUrl' => 'index.php?c=dimension&a=dimension_tree_for_permissions&ajax=true&dimension_id='.$dimension->getId().'&only_without_perm=1&pg='.$pg_id."$excluded_members_param",
		'loadAdditionalParameters' => array($genid."excluded_members_".$dimension->getId()),
		'enableDD' => true, 
		'ddGroup' => $genid.'_dimension_'.$dimension->getId(), 
		'width' => '300',
		'all_members' => false,
		'root_node_text' => $dimension->getName(),//lang('All'),
		'get_childs_params' => array('pg_id' => $pg_id, 'with_permissions' => '-1'),
		//'use_ajax_member_tree' => true,
	));
?>
		<div class="desc" style="width:300px;"><?php echo lang('drag to left to add permissions')?></div>
	
	</td></tr></table>

	  <div id="<?php echo $genid ?>member_permissions<?php echo $dimension->getId() ?>" class="permission-form-container" style="display: none;">
		<div id="<?php echo $genid . "_" . $dimension->getId()?>member_name" class="permissions-target-name"></div>
	
		<table>
			<tr class="permissions-title-row">
				<td></td>
				<td align=center style="width: 120px;">
					<a href="#" class="internalLink all-radio-sel radio-title-3" onclick="og.ogPermSetLevel('<?php echo $genid ?>', '<?php echo $dimension->getId() ?>', 3);return false;"><?php echo lang('read write and delete') ?></a>
				</td>
				<td align=center style="width: 120px;">
					<a href="#" class="internalLink all-radio-sel radio-title-2" onclick="og.ogPermSetLevel('<?php echo $genid ?>', '<?php echo $dimension->getId() ?>', 2);return false;"><?php echo lang('read and write') ?></a>
				</td>
				<td align=center style="width: 120px;">
					<a href="#" class="internalLink all-radio-sel radio-title-1" onclick="og.ogPermSetLevel('<?php echo $genid ?>', '<?php echo $dimension->getId() ?>', 1);return false;"><?php echo lang('read only') ?></a>
				</td>
				<td align=center style="width: 120px;">
					<a href="#" class="internalLink all-radio-sel radio-title-0" onclick="og.ogPermSetLevel('<?php echo $genid ?>', '<?php echo $dimension->getId() ?>', 0);return false;"><?php echo lang('none no bars') ?></a>
				</td>
			</tr>
			
			<tr class="permissions-checkall-row">
				<td><?php echo lang('check all').":"?></td>
				<td align="center">
					<input type="checkbox" class="all-radio-sel-chk" id="chk-3" title="<?php echo lang('set rwd permissions for all object types')?>"
						onchange="og.ogPermSetLevelCheckbox(this, '<?php echo $genid ?>', '<?php echo $dimension->getId() ?>', 3);"/>
				</td>
				<td align="center">
					<input type="checkbox" class="all-radio-sel-chk" id="chk-2" title="<?php echo lang('set rw permissions for all object types')?>"
						onchange="og.ogPermSetLevelCheckbox(this, '<?php echo $genid ?>', '<?php echo $dimension->getId() ?>', 2);"/>
				</td>
				<td align="center">
					<input type="checkbox" class="all-radio-sel-chk" id="chk-1" title="<?php echo lang('set r permissions for all object types')?>"
						onchange="og.ogPermSetLevelCheckbox(this, '<?php echo $genid ?>', '<?php echo $dimension->getId() ?>', 1);"/>
				</td>
				<td align="center">
					<input type="checkbox" class="all-radio-sel-chk" id="chk-0" title="<?php echo lang('set none permissions for all object types')?>"
						onchange="og.ogPermSetLevelCheckbox(this, '<?php echo $genid ?>', '<?php echo $dimension->getId() ?>', 0);"/>
				</td>
			</tr>
<?php
	$row_cls = "";
	foreach ( $all_object_types as $ot ) {
		if (! in_array ( $ot->getId (), $allowed_object_types [$dimension->getId ()] )) continue;
		$row_cls = $row_cls == "" ? "altRow" : "";
		$id_suffix = $dimension->getId () . "_" . $ot->getId ();
		$change_parameters = '\'' . $genid . '\', ' . $dimension->getId () . ', ' . $ot->getId ();
		?>
			<tr class="<?php echo $row_cls?>">
				<td style="padding-right: 20px"><span id="<?php echo $genid.'obj_type_label'.$id_suffix?>"><?php echo $ot->getObjectTypeName() ?></span></td>
				<td align=center><?php echo radio_field($genid .'rg_'.$id_suffix, false, array('onchange' => 'og.ogPermValueChanged('. $change_parameters .')', 'value' => '3', 'style' => 'width:16px', 'id' => $genid . 'rg_3_'.$id_suffix, 'class' => "radio_3")) ?></td>
				<td align=center><?php echo radio_field($genid .'rg_'.$id_suffix, false, array('onchange' => 'og.ogPermValueChanged('. $change_parameters .')', 'value' => '2', 'style' => 'width:16px', 'id' => $genid . 'rg_2_'.$id_suffix, 'class' => "radio_2")) ?></td>
				<td align=center><?php echo radio_field($genid .'rg_'.$id_suffix, false, array('onchange' => 'og.ogPermValueChanged('. $change_parameters .')', 'value' => '1', 'style' => 'width:16px', 'id' => $genid . 'rg_1_'.$id_suffix, 'class' => "radio_1")) ?></td>
				<td align=center><?php echo radio_field($genid .'rg_'.$id_suffix, false, array('onchange' => 'og.ogPermValueChanged('. $change_parameters .')', 'value' => '0', 'style' => 'width:16px', 'id' => $genid . 'rg_0_'.$id_suffix, 'class' => "radio_0")) ?></td>
			</tr>
<?php }?>
    
	    </table>
	    
		<div class="additional-member-permissions" id="<?php echo $genid?>-<?php echo $dimension->getId()?>-additional-member-permissions">
		</div>
	    
		<div style="width: 100%; margin: 15px 0;">
			<div>
				<?php echo radio_field($genid."_".$dimension->getId().'_apply_to', true, array('id' => $genid."_".$dimension->getId()."_apply_to0", 'style' => 'vertical-align: top;margin-top: 3px;', 'value' => 'only_current', 'onchange' => 'og.onApplyToRadioChange(this, "'.$genid.'", "'.$dimension->getId().'")')); ?>
				<label id="<?php echo $genid."_".$dimension->getId()."_apply_to_only_current_label" ?>" style="max-width:500px; display:inline-block;" for="<?php echo $genid."_".$dimension->getId()."_apply_to0"?>"></label>
			</div>
			<div class="clear"></div>
			<div>
				<?php echo radio_field($genid."_".$dimension->getId().'_apply_to', false, array('id' => $genid."_".$dimension->getId()."_apply_to1", 'style' => 'vertical-align: top;margin-top: 3px;', 'value' => 'apply_to_submembers', 'onchange' => 'og.onApplyToRadioChange(this, "'.$genid.'", "'.$dimension->getId().'")')); ?>
				<label id="<?php echo $genid."_".$dimension->getId()."_apply_to_submembers_label" ?>" style="max-width:500px; display:inline-block;" for="<?php echo $genid."_".$dimension->getId()."_apply_to1"?>"></label>
			</div>
			<div class="clear"></div>
			<div>
				<?php echo radio_field($genid."_".$dimension->getId().'_apply_to', false, array('id' => $genid."_".$dimension->getId()."_apply_to2", 'style' => 'vertical-align: top;margin-top: 3px;', 'value' => 'apply_to_all', 'onchange' => 'og.onApplyToRadioChange(this, "'.$genid.'", "'.$dimension->getId().'")')); ?>
				<label id="<?php echo $genid."_".$dimension->getId()."_apply_to_all_members_label" ?>" style="max-width:500px; display:inline-block;" for="<?php echo $genid."_".$dimension->getId()."_apply_to2"?>"></label>
			</div>
			<input type="hidden" id="<?php echo $genid."_".$dimension->getId().'_apply_to'?>" value="only_current" />
			<div class="clear"></div>
		</div>
	
		<div style="float:right;margin-top:5px;">
			<button class="add-first-btn" onclick="$('#<?php echo $genid?>_close_link').click();" id="<?php echo $genid?>_cancel_btn" style="margin-right:10px;">
				<img src="public/assets/themes/default/images/16x16/del.png">&nbsp;<?php echo lang('cancel')?>
			</button>
			<button class="add-first-btn" onclick="og.afterChangingPermissions('<?php echo $genid?>','<?php echo $dimension->getId() ?>'); $('#<?php echo $genid?>_close_link').click();" id="<?php echo $genid?>_save_btn">
				<img src="public/assets/themes/default/images/16x16/save.png">&nbsp;<?php echo lang('save changes')?>
			</button>
		</div>
		
	  </div>
	</div>

</fieldset>

<script>

if (!og.permissionDimensions) og.permissionDimensions = [];
og.permissionDimensions.push(<?php echo $dimension->getId() ?>);



$(function(){

	<?php if (isset($user_type) && $user_type > 0) { ?>
	og.showHidePermissionsRadioButtonsByRole('<?php echo $genid?>', '<?php echo $dimension->getId() ?>', '<?php echo $user_type?>');
	<?php } ?>
	
	var with_perm_tree = Ext.getCmp('<?php echo $genid . '_with_permissions_' . $dimension->getId() . '-tree'?>');
	if (with_perm_tree) {
		with_perm_tree.addClass('with-permissions-tree');
		with_perm_tree.disable_default_events = true;
		with_perm_tree.on('click', function(member) {
			if (!isNaN(member.id)) {
				og.showPermissionsPopup(genid, member.ownerTree.dimensionId, member.id, member.text);
			}
		});
		with_perm_tree.on('beforenodedrop', function(dropEvent) {
			og.permissionsDDAddRemovePermissions(dropEvent);
		});
		with_perm_tree.on('tree rendered', function(tree) {
			setTimeout(function() {
				tree.getRootNode().expand(true,false,function(n){
					if (!isNaN(n.id) && !og.hasAnyPermissions(genid, n.id)) {
						n.getUI().addClass('tree-node-no-permissions');
					}
					if (!isNaN(n.id)) n.collapse();
					else n.getUI().addClass('x-tree-node-icon-hidden');
				});
				// add emtpy nodes, to fill container area (to allow d&d)
				while (with_perm_tree.getRootNode().childNodes.length < 9) {
					var empty_node = new Ext.tree.TreeNode({ 'id': 'temp-'+Ext.id(), 'text': '', 'iconCls': '' });
					with_perm_tree.getRootNode().appendChild(empty_node);
				}
			}, 500);
		});
	}
	
	var without_perm_tree = Ext.getCmp('<?php echo $genid . '_without_permissions_' . $dimension->getId() . '-tree'?>');
	if (without_perm_tree) {
		without_perm_tree.addClass('with-permissions-tree');
		without_perm_tree.disable_default_events = true;
		without_perm_tree.on('beforenodedrop', function(dropEvent) {
			og.permissionsDDAddRemovePermissions(dropEvent);
		});
		without_perm_tree.on('tree rendered', function(tree) {
			setTimeout(function() {
				tree.getRootNode().expand(true,false,function(n){
					if (!isNaN(n.id) && !og.hasAnyPermissions(genid, n.id)) {
						n.getUI().addClass('tree-node-no-permissions');
					}
					if (!isNaN(n.id)) n.collapse();
					else n.getUI().addClass('x-tree-node-icon-hidden');
				});
				// add emtpy nodes, to fill container area (to allow d&d)
				while (without_perm_tree.getRootNode().childNodes.length < 9) {
					var empty_node = new Ext.tree.TreeNode({ 'id': 'temp-'+Ext.id(), 'text': '', 'iconCls': '' });
					without_perm_tree.getRootNode().appendChild(empty_node);
				}
			}, 500);
		});
	}
});
</script>
<?php } // foreach dimension ?>

<div id="<?php echo $genid?>ask_to_remove_from_submembers" class="permission-form-container" style="padding-bottom: 20px; display:none;">
	<input type="hidden" id="<?php echo $genid?>parent_member_removed_perms" name="parent_member_removed_perms" value=""/>
	<input type="hidden" id="<?php echo $genid?>dimension_id_removed_perms" name="dimension_id_removed_perms" value=""/>
	
	<div class="ask-to-remove-sumbembers-text" id="<?php echo $genid?>ask_to_remove_sumbembers_text"></div>
	<div class="ask-to-remove-sumbembers-buttons">
		<button onclick="og.removePermissionsForSubmembers('<?php echo $genid?>');$('#<?php echo $genid?>_remove_submembers_close_link').click();"><?php echo lang('yes')?></button>
		<button onclick="$('#<?php echo $genid?>_remove_submembers_close_link').click();"><?php echo lang('no')?></button>
	</div>
</div>

</div>

<script>

var genid = '<?php echo $genid ?>';
og.defaultRolePermissions = Ext.util.JSON.decode('<?php echo json_encode($default_user_permissions_by_role)?>');

$(function(){
	
	og.ogLoadPermissions('<?php echo $genid ?>');

});

</script>