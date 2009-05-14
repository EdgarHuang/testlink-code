<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * Filename $RCSfile: cfieldsEdit.php,v $
 *
 * @version $Revision: 1.16 $
 * @modified $Date: 2009/05/14 19:01:57 $ by $Author: schlundus $
 *
 * rev: 20090503 - franciscom - BUGID 2425
 *      20090408 - franciscom - BUGID 2352, BUGID 2359
 *      20080810 - franciscom - BUGID 1650 
 *
 */
require_once(dirname(__FILE__) . "/../../config.inc.php");
require_once("common.php");
testlinkInitPage($db,false,false,"checkRights");

$cfield_mgr = new cfield_mgr($db);
$templateCfg = templateConfiguration();
$args=init_args();

$gui = new stdClass();
$gui->cfield=null;
$gui->cfield_is_used=0;
$gui->cfield_is_linked=0;
$gui->linked_tprojects=null;
$gui->cfield_types=$cfield_mgr->get_available_types();

$result_msg = null;
$do_control_combo_display = 1;

$cfieldCfg=cfieldCfgInit($cfield_mgr);
$emptyCF = array('id' => $args->cfield_id,
		         'name' => ' ',
                 'label' => ' ',
				 'type' => 0,
		         'possible_values' => '',
		         'show_on_design' => 1,
		         'enable_on_design' => 1,
		         'show_on_execution' => 1,
		         'enable_on_execution' => 1,
		         'show_on_testplan_design' => 1,
		         'enable_on_testplan_design' => 1,
		         'node_type_id' => $cfieldCfg->allowed_nodes['testcase']);

$gui->cfield = $emptyCF;
switch ($args->do_action)
{
	case 'create':
    	$templateCfg->template=$templateCfg->default_template;
		$user_feedback ='';
    	$operation_descr = '';
		break;

	case 'edit':
	  	$op = edit($args,$cfield_mgr);
		$gui->cfield = $op->cf;
		$gui->cfield_is_used = $op->cf_is_used;
		$gui->cfield_is_linked = $op->cf_is_linked;
		$gui->linked_tprojects = $op->linked_tprojects;
    	$user_feedback = $op->user_feedback;
    	$operation_descr=$op->operation_descr;
		break;

	case 'do_add':
	  	$op = doCreate($_REQUEST,$cfield_mgr);
		$gui->cfield = $op->cf;
    	$user_feedback = $op->user_feedback;
    	$templateCfg->template = $op->template;
    	$operation_descr = '';
		break;

	case 'do_update':
	  	$op = doUpdate($_REQUEST,$args,$cfield_mgr);
		$gui->cfield = $op->cf;
    	$user_feedback = $op->user_feedback;
    	$operation_descr=$op->operation_descr;
    	$templateCfg->template = $op->template;
		break;

	case 'do_delete':
		$op = doDelete($args,$cfield_mgr);
	    $user_feedback = $op->user_feedback;
    	$operation_descr=$op->operation_descr;
		$templateCfg->template = $op->template;
		$do_control_combo_display = 0;
		break;
}

// To control combo display
if( $do_control_combo_display )
{
    $keys2loop = $cfield_mgr->get_application_areas();
	foreach( $keys2loop as $ui_mode)
	{
		if(!$cfieldCfg->enable_on_cfg[$ui_mode][$gui->cfield['node_type_id']])
		{
			$cfieldCfg->disabled_cf_enable_on[$ui_mode]=' disabled="disabled" ';
        }
		if(!$cfieldCfg->show_on_cfg[$ui_mode][$gui->cfield['node_type_id']])
		{
			$cfieldCfg->disabled_cf_show_on[$ui_mode]=' disabled="disabled" ';
		}	
	}
}

$gui->show_possible_values = 0;
if(isset($gui->cfield['type']))
{
	$gui->show_possible_values = $cfieldCfg->possible_values_cfg[$gui->cfield['type']];
}
$gui->cfieldCfg=$cfieldCfg;

$smarty = new TLSmarty();
$smarty->assign('operation_descr',$operation_descr);
$smarty->assign('user_feedback',$user_feedback);
$smarty->assign('user_action',$args->do_action);
renderGui($smarty,$args,$gui,$cfield_mgr,$templateCfg);


/*
  function: request2cf
            scan a hash looking for a keys with 'cf_' prefix,
            because this keys represents fields of Custom Fields
            tables.
            Is used to get values filled by user on a HTML form.
            This requirement dictated how html inputs must be named.
            If notation is not followed logic will fail.

  args: hash

  returns: hash only with related to custom fields, where
           (keys,values) are the original with 'cf_' prefix, but
           in this new hash prefix on key is removed.
           
  rev: 20080811 - franciscom - added new values on missing_keys         

*/
function request2cf($hash)
{
  // design and execution has sense for node types regarding testing
  // testplan,testsuite,testcase, but no sense for requirements.
  //
  // Missing keys are combos that will be disabled and not show at UI.
  // For req spec and req, no combo is showed.
  // To avoid problems (need to be checked), my choice is set to 1
  // *_on_design keys, that right now will not present only for
  // req spec and requirements.
  //
	$missing_keys = array('show_on_design' => 1,
                        'enable_on_design' => 1,
                        'show_on_execution' => 0,
                        'enable_on_execution' => 0,
                        'show_on_testplan_design' => 0,
                        'enable_on_testplan_design' => 0,
                        'possible_values' => ' ' );

	$cf_prefix = 'cf_';
	$len_cfp = tlStringLen($cf_prefix);
	$start_pos = $len_cfp;
	$cf = array();
	foreach($hash as $key => $value)
	{
		if(strncmp($key,$cf_prefix,$len_cfp) == 0)
		{
			$dummy = substr($key,$start_pos);
			$cf[$dummy] = $value;
		}
	}

	foreach($missing_keys as $key => $value)
	{
		if(!isset($cf[$key]))
			$cf[$key] = $value;
	}

	return $cf;
}

/*
  function:

  args:

  returns:

*/
function init_args()
{
    $_REQUEST=strings_stripSlashes($_REQUEST);
    $args = new stdClass();
    $args->do_action = isset($_REQUEST['do_action']) ? $_REQUEST['do_action']:null;
    $args->cfield_id = isset($_REQUEST['cfield_id']) ? $_REQUEST['cfield_id']:0;
    $args->cf_name = isset($_REQUEST['cf_name']) ? $_REQUEST['cf_name']:null;
    return $args;
}

/*
  function: edit

  args:

  returns:

*/
function edit(&$argsObj,&$cfieldMgr)
{
    $op = new stdClass();
    $op->cf = null;
    $op->cf_is_used = 0;
    $op->cf_is_linked = 0;
    
    $op->user_feedback = '';
    $op->template = null;
    $op->operation_descr = '';
    $op->linked_tprojects = null;

	$cfinfo = $cfieldMgr->get_by_id($argsObj->cfield_id);
	if ($cfinfo)
	{
		$op->cf = $cfinfo[$argsObj->cfield_id];
		$op->cf_is_used = $cfieldMgr->is_used($argsObj->cfield_id);
		
  		$op->operation_descr = lang_get('title_cfield_edit') . TITLE_SEP_TYPE3 . $op->cf['name'];
  		$op->linked_tprojects = $cfieldMgr->get_linked_testprojects($argsObj->cfield_id); 
  		$op->cf_is_linked = !is_null($op->linked_tprojects) && count($op->linked_tprojects) > 0;
	}
    return $op;
}


/*
  function: doCreate

  args:

  returns:

*/
function doCreate(&$hash_request,&$cfieldMgr)
{
    $op = new stdClass();
   	$op->template = "cfieldsEdit.tpl";
    $op->user_feedback='';
	$op->cf = request2cf($hash_request);
	$keys2trim=array('name','label','possible_values');
	foreach($keys2trim as $key)
	{
	    $op->cf[$key]=trim($op->cf[$key]);
	}
		// Check if name exists
		$dupcf = $cfieldMgr->get_by_name($op->cf['name']);
		if(is_null($dupcf))
		{
			$ret = $cfieldMgr->create($op->cf);
			if(!$ret['status_ok'])
				$op->user_feedback = lang_get("error_creating_cf");
			else
			{
			  	$op->template = null;
				logAuditEvent(TLS("audit_cfield_created",$op->cf['name']),"CREATE",$ret['id'],"custom_fields");
      		}
		}
		else
			$op->user_feedback = lang_get("cf_name_exists");

		return $op;
}



/*
  function: doUpdate

  args:

  returns:

*/
function doUpdate(&$hash_request,&$argsObj,&$cfieldMgr)
{
    $op = new stdClass();
    $op->template = "cfieldsEdit.tpl";
    $op->user_feedback='';
	  $op->cf = request2cf($hash_request);
	  $op->cf['id'] = $argsObj->cfield_id;

    $oldObjData=$cfieldMgr->get_by_id($argsObj->cfield_id);
    $oldname=$oldObjData[$argsObj->cfield_id]['name'];
    $op->operation_descr=lang_get('title_cfield_edit') . TITLE_SEP_TYPE3 . $oldname;

	$keys2trim=array('name','label','possible_values');
	foreach($keys2trim as $key)
	{
		$op->cf[$key]=trim($op->cf[$key]);
	}

	// Check if name exists
	$is_unique = $cfieldMgr->name_is_unique($op->cf['id'],$op->cf['name']);
	if($is_unique)
	{
		$ret = $cfieldMgr->update($op->cf);
		if ($ret)
		{
			$op->template = null;
			logAuditEvent(TLS("audit_cfield_saved",$op->cf['name']),"SAVE",$op->cf['id'],"custom_fields");
		}
	}
	else
		$op->user_feedback = lang_get("cf_name_exists");
	
	return $op;
}



/*
  function: doDelete

  args:

  returns:

*/
function doDelete(&$argsObj,&$cfieldMgr)
{
    $op = new stdClass();
	  $op->user_feedback='';
	  $op->cf = null;
	  $op->template = null;
	  $op->operation_descr = '';
    
	  $cf = $cfieldMgr->get_by_id($argsObj->cfield_id);
	  if ($cf)
	  {
	  	$cf = $cf[$argsObj->cfield_id];
	  	if ($cfieldMgr->delete($argsObj->cfield_id))
	  	{
	  		logAuditEvent(TLS("audit_cfield_deleted",$cf['name']),"DELETE",$argsObj->cfield_id,"custom_fields");
	  	}	
	  }
	  return $op;
}






/*
  function: cfieldCfgInit

  args :

  returns: object with configuration options
  
  rev: 20080810 - franciscom - BUGID 1650 (REQ - CF on testplan_design)

*/
function cfieldCfgInit($cfieldMgr)
{
    $cfg = new stdClass();
    $cfAppAreas=$cfieldMgr->get_application_areas();     // 20080810 - BUGID 1650 - Start
    foreach($cfAppAreas as $area)
    {
        $cfg->disabled_cf_enable_on[$area]='';
        $cfg->disabled_cf_show_on[$area]='';
    	  $cfg->enable_on_cfg[$area] = $cfieldMgr->get_enable_on_cfg($area);
    	  $cfg->show_on_cfg[$area] = $cfieldMgr->get_show_on_cfg($area);
    }// 20080810 - BUGID 1650 - End

    $cfg->possible_values_cfg = $cfieldMgr->get_possible_values_cfg();
    $cfg->allowed_nodes = $cfieldMgr->get_allowed_nodes();
    $cfg->cf_allowed_nodes = array();
    foreach($cfg->allowed_nodes as $verbose_type => $type_id)
    {
    	$cfg->cf_allowed_nodes[$type_id] = lang_get($verbose_type);
    }

    return $cfg;
}


/*
  function: renderGui
            set environment and render (if needed) smarty template

  args: 

  returns: - 
  
  rev: 20080921 - franciscom - added guiObj argument

*/
function renderGui(&$smartyObj,&$argsObj,&$guiObj,&$cfieldMgr,$templateCfg)
{
    $doRender=false;
    switch($argsObj->do_action)
    {
    	case "do_add":
    	case "do_delete":
    	case "do_update":
        $doRender=true;
    		$tpl = is_null($templateCfg->template) ? 'cfieldsView.tpl' : $templateCfg->template;
    		break;

    	case "edit":
    	case "create":
        $doRender=true;
    		$tpl = is_null($templateCfg->template) ? $templateCfg->default_template : $templateCfg->template;
    		break;
    }

    if($doRender)
    {
		    $guiObj->cf_map=$cfieldMgr->get_all();
		    $guiObj->cf_types=$cfieldMgr->get_available_types();
		    $smartyObj->assign('gui',$guiObj);
		    $smartyObj->display($templateCfg->template_dir . $tpl);
	  }
}

function checkRights(&$db,&$user)
{
	return $user->hasRight($db,"cfield_management");
}
?>
