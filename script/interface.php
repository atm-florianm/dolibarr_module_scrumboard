<?php

require ('../config.php');

if($conf->of->enabled)dol_include_once('/of/class/ordre_fabrication_asset.class.php');
else if($conf->asset->enabled) dol_include_once('/asset/class/ordre_fabrication_asset.class.php'); //OLD


$get = GETPOST('get','alpha');
$put = GETPOST('put','alpha');

_put($db, $put);
_get($db, $get);

function _get(&$db, $case) {
global $conf;

	switch ($case) {
		
		case 'logged-status':
			echo 'ok';
			
			break;
		
		case 'tasks' :

			$onlyUseGrid = isset($_REQUEST['gridMode']) && $_REQUEST['gridMode']==1 && empty($conf->global->SCRUM_ALLOW_ALL_TASK_IN_GRID) ? true : false;

			$var = explode('|',GETPOST('status'));
			$Tab=array();
			foreach($var as $statut) {
				$Tab=array_merge($Tab, _tasks($db, (int)GETPOST('id_project'), $statut, $onlyUseGrid));
			}

			print json_encode($Tab);

			break;
		case 'task-ordo-simulation':
			if($conf->workstation->enabled) {
                define('INC_FROM_DOLIBARR',true);
                dol_include_once('/workstation/config.php');
                $PDOdb=new TPDOdb;
                $TWorkstation=TWorkstation::getWorstations($PDOdb,true);

            }
			else {
				print 'module non configuré';
				exit;
			}

		    $type_object =  GETPOST('type_object');
		    $TTaskObject = ($type_object == 'propal') ? _task_propal($db, GETPOST('fk_object')) : _task_commande($db, GETPOST('fk_object'));

		    $Tab = ordonnanceur(
    			array_merge(
    				_tasks_ordo($db, $TWorkstation, 'inprogress|todo', 0)
					,$TTaskObject
				)
    			, $TWorkstation
    			, 0
				, false
			);

			$time_max = 0;
			foreach($Tab['tasks'] as &$task) {
				if($task['time_estimated_end']> $time_max) $time_max =(int)$task['time_estimated_end'];
			}

			if($type_object == 'propal') {
				print dol_print_date($time_max + ($conf->global->SCRUM_TIME_MORE_PREVISION_PROPAL * 86400), 'day');
			}
			else {
				print dol_print_date($time_max + ($conf->global->SCRUM_TIME_MORE_PREVISION * 86400), 'day');
			}

			break;
        case 'tasks-ordo':
           $TWorkstation = array(
                0=>array('nb_ressource'=>1, 'velocity'=>1, 'background'=>'linear-gradient(to right,white, #ccc)', 'name'=>'Non ordonnancé') // base de 7h par jour
            );

            if($conf->workstation->enabled) {
                define('INC_FROM_DOLIBARR',true);
                dol_include_once('/workstation/config.php');
                $PDOdb=new TPDOdb;
                $TWorkstation = TWorkstation::getWorstations($PDOdb,true,false,$TWorkstation);


            }
           //     var_dump($TWorkstation);
            $Tab = ordonnanceur(
    			_tasks_ordo($db, $TWorkstation, GETPOST('status'), GETPOST('fk_workstation') )
    			, $TWorkstation
    			, (int)GETPOST('fk_workstation')
			);

            if(!empty($conf->global->SCRUM_LINK_EVENT_TO_TASK)) {

                ordonnanceur_link_event($Tab);

            }

            print json_encode($Tab);


            break;

		case 'task' :

			print json_encode(_task($db, (int)GETPOST('id')));

			break;

		case 'velocity':

			print json_encode(_velocity($db, (int)GETPOST('id_project')));

			break;

		case 'select-task':
			dol_include_once('/core/class/html.formother.class.php');
			$formother = new FormOther($db);

			//selectProjectTasks($selectedtask='', $projectid=0, $htmlname='task_parent', $modeproject=0, $modetask=0, $mode=0, $useempty=0, $disablechildoftaskid=0)
			echo $formother->selectProjectTasks(GETPOST('fk_task'), GETPOST('fk_project'), 'fk_project_task',0,1,0,1);

			break;
	}

}

function _put(&$db, $case) {
	switch ($case) {
        case 'split':

            $task2id = _split_task(GETPOST('taskid'),GETPOST('tache1'),GETPOST('tache2'));
            print json_encode(_task($db, $task2id));

            break;
		case 'task' :

			print json_encode(_task($db, (int)GETPOST('id'), $_REQUEST));

			break;

		case 'sort-task' :

			_sort_task($db, $_REQUEST['TTaskID'],$_REQUEST['list']);

			break;
		case 'reset-date-task':

			_reset_date_task($db,(int)GETPOST('id_project'), (float)GETPOST('velocity') * 3600);

			break;

		case 'coord':
			_coord($db, $_POST['coord']);

			break;
        case 'sort-task-ws' :

            print _sort_task_ws($db, GETPOST('TTaskID'));

            break;

        case 'ws':
            print json_encode( _task_ws($db, GETPOST('taskid'), GETPOST('fk_workstation')) );

            break;
		case 'resize':
			return _resize($db, $_POST['coord']);
			break;

        case 'set-user-task':
            print _set_user_in_task(GETPOST('taskid'), GETPOST('userid'));

            break;
        case 'remove-user-task':
            print _remove_user_in_task(GETPOST('taskid'), GETPOST('userid'));

            break;
	}

}

function _set_user_in_task($taskid, $userid) {
global $db;

    $task=new Task($db);
    $task->fetch($taskid);

    return $task->add_contact($userid, 180, 'internal');

}
function _remove_user_in_task($taskid, $userid) {
global $db;

    $db->query("DELETE FROM ".MAIN_DB_PREFIX."element_contact WHERE element_id=".$taskid." AND fk_c_type_contact=180 AND fk_socpeople=".$userid);

    return 1;


}

function _sort_task_ws(&$db, &$TTaskId) {

     foreach($TTaskId as $data) {

         list($id,$top)=explode('-',$data);

         $sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET
            grid_row=".$top."
            WHERE rowid = ".$id;
         $db->query($sql);

     }

    return 1;
}

function  _task_ws(&$db, $taskid, $fk_workstation) {
	  $TIdTask = array();
	  TSmallGeoffrey::setTaskWS($TIdTask,$taskid,$fk_workstation);
      return $TIdTask;

}
function _coord(&$db, $TCoord) {

	foreach($TCoord as $coord) {
		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET
			grid_col=".(int)$coord['col']."
			,grid_row=".(int)$coord['row']."
		WHERE rowid = ".(int)$coord['id'];
		$db->query($sql);

		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_extrafields SET
			fk_workstation=".(int)$coord['fk_workstation']."
		WHERE fk_object = ".(int)$coord['id'];
		$db->query($sql);

	}

}

function _resize(&$db, $TCoord) {

	foreach($TCoord as $coord) {
		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET
			planned_workload=".((int)$coord['size_y']*3600)."
		WHERE rowid = ".(int)$coord['id'];
		$db->query($sql);

		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_extrafields SET
			needed_ressource=".(int)$coord['size_x']."
		WHERE fk_object = ".(int)$coord['id'];
		$db->query($sql);

	}

    return 1;

}

function _velocity(&$db, $id_project) {
global $langs;

	$Tab=array();

	$velocity = scrum_getVelocity($db, $id_project);
	$Tab['velocity'] = $velocity;
	$Tab['current'] = convertSecondToTime($velocity).$langs->trans('HoursPerDay');

	if( (float)DOL_VERSION <= 3.4 ) {
		// ne peut pas gérér la résolution car pas de temps plannifié
	}
	else {

		if($velocity>0) {

			$time = time();
			$res=$db->query("SELECT SUM(planned_workload-duration_effective) as duration
				FROM ".MAIN_DB_PREFIX."projet_task
				WHERE fk_projet=".$id_project." AND progress>0 AND progress<100");
			if($obj=$db->fetch_object($res)) {
				//time rest in second
				$time_end_inprogress = $time + $obj->duration / $velocity * 86400;
			}

			if($time_end_inprogress<$time)$time_end_inprogress = $time;

			$res=$db->query("SELECT SUM(planned_workload-duration_effective) as duration
				FROM ".MAIN_DB_PREFIX."projet_task
				WHERE fk_projet=".$id_project." AND progress=0");
			if($obj=$db->fetch_object($res)) {
				//time rest in second
				$time_end_todo = $time_end_inprogress + $obj->duration / $velocity * 86400;
			}

			if($time_end_todo<$time)$time_end_todo = $time;

			if($time_end_todo>$time_end_inprogress) $Tab['todo']=', '.$langs->trans('EndedThe').' '.date('d/m/Y', $time_end_todo);
			$Tab['inprogress']=', '.$langs->trans('EndedThe').' '.date('d/m/Y', $time_end_inprogress);

			$Tab['time_todo'] = $time_end_todo;
			$Tab['time_inprogress'] = $time_end_inprogress;
		}



	}

	return $Tab;

}

function _as_array(&$object, $recursif=false) {
global $langs;
	$Tab=array();

		foreach ($object as $key => $value) {

			if(is_object($value) || is_array($value)) {
				if($recursif) $Tab[$key] = _as_array($recursif, $value);
				else $Tab[$key] = $value;
			}
			else if(strpos($key,'date_')===0){

				$Tab['time_'.$key] = $value;

				if(empty($value))$Tab[$key] = '0000-00-00 00:00:00';
				else $Tab[$key] = date('Y-m-d H:i:s',$value);
			}
			else{
				$Tab[$key]=$value;
			}
		}
		return $Tab;

}

function _sort_task(&$db, $TTask, $listname) {
	global $user;

	if(strpos($listname, 'inprogress')!==false)$step = 1000;
	else if(strpos($listname, 'todo')!==false)$step = 2000;
	else $step = 0;

	foreach($TTask as $rank=>$id) {
		$task=new Task($db);
		$task->fetch($id);
		$task->fetch_optionals($id); // Otherwise they are scratched in the update
		$task->rang = $step + $rank;
		$task->update($user);
	}

}
function _set_values(&$object, $values) {

	foreach($values as $k=>$v) {

		if(isset($object->{$k})) {

			$object->{$k} = $v;

		}

	}

}
function _task(&$db, $id_task, $values=array()) {
global $user, $langs,$conf;

	$task=new Task($db);
	if($id_task) {
		$task->fetch($id_task);
		$task->fetch_optionals($id_task);
	}

	if(!empty($values)){
		_set_values($task, $values);

		if($values['status']=='inprogress') {
			if($task->progress==0)$task->progress = 5;
			else if($task->progress==100)$task->progress = 95;
		}
		else if($values['status']=='finish') {
			$task->progress = 100;
		}
		else if($values['status']=='todo') {
			$task->progress = 0;
		}

		$task->status = $values['status'];

		$task->update($user);
	}

	$task->date_delivery = 0;
	if($task->date_end >0 && $task->planned_workload>0) {

		$velocity = scrum_getVelocity($db, $task->fk_project);
		$task->date_delivery = _get_delivery_date_with_velocity($db, $task, $velocity);

	}

	$dayInSecond = 86400;
	if($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY){
		$dayInSecond = 60*60*$conf->global->TIMESHEET_WORKING_HOUR_PER_DAY;
	}

	$task->aff_time = convertSecondToTime($task->duration_effective,'all',$dayInSecond);
	$task->aff_planned_workload = convertSecondToTime($task->planned_workload,'all',$dayInSecond);
    $task->time_rest = $task->planned_workload * (1 - ($task->progress / 100) );
    $task->aff_time_rest = $langs->trans('TimeRest').' : '.convertSecondToTime($task->time_rest,'all',$dayInSecond);

	$task->long_description=$task->divers='';

	if((int)$task->array_options['options_fk_of']>0 && $conf->of->enabled) {

    		if(!isset($PDOdb))$PDOdb = new TPDOdb;

			$of=new TAssetOF;
			$of->withChild = false;
			$of->load($PDOdb, $task->array_options['options_fk_of']);

			$link_of =  !empty($conf->of->enabled) ? dol_buildpath('/of/fiche_of.php?id='.$task->array_options['options_fk_of'],1) : '';

			if($of->fk_soc > 0) {
				$soc=new Societe($db);
				$soc->fetch($of->fk_soc);
			}

			$task->divers.='[<a href="'.$link_of.'">'.$of->numero.'</a>] '.(!empty($soc) ? $soc->getNomUrl() : '' ).'<br />';

			if($of->fk_commande > 0) {
				dol_include_once('/commande/class/commande.class.php');
				$commande=new Commande($db);
				$commande->fetch($of->fk_commande);
				$task->divers.=$commande->getNomUrl(1) .'<br />';

			}

	}

    if((int)$task->array_options['options_fk_product']>0 && (empty($conf->global->SCRUMBOARD_ICON_SET) || $conf->global->SCRUMBOARD_ICON_SET!='null')) {
        dol_include_once('/product/class/product.class.php');

        $product = new Product($db);
        if($product->fetch((int)$task->array_options['options_fk_product'])>0) {
            $task->divers.='['.$product->getNomUrl().' '.$product->label.']<br />';

            $nb_picto = ($product->id % 49) - 1;
            $y_picto = floor($nb_picto / 7);
            $x_picto = ($nb_picto - ($y_picto * 7));

            $w_cell = 27;
            $h_cell = 28;

            $task->divers.='<div class="picto" style="float:left; margin-left:3px; background-image:url(./img/'.(!empty($conf->global->SCRUMBOARD_ICON_SET) ? $conf->global->SCRUMBOARD_ICON_SET:'animal-icons-mini').'.png);background-position:'.($w_cell * -$x_picto).'px '.($h_cell * -$y_picto).'px;width:'.$w_cell.'px; height:'.$h_cell.'px;"></div>';
            //var_dump(array($nb_picto,$y_picto, $x_picto,$task->divers));
        }


    }

    if(!empty($task->note_private)) $task->divers.='<br />'.$task->note_private;

	if($task->date_start>0) $task->long_description .= $langs->trans('TaskDateStart').' : '.dol_print_date($task->date_start).'<br />';
	if($task->date_end>0) $task->long_description .= $langs->trans('TaskDateEnd').' : '.dol_print_date($task->date_end).'<br />';
	if($task->date_delivery>0 && $task->date_delivery>$task->date_end) $task->long_description .= $langs->trans('TaskDateShouldDelivery').' : '.dol_print_date($task->date_delivery).'<br />';

	$task->long_description.=$task->description;

	$task->project = new Project($db);
	$task->project->fetch($task->fk_project);
	$task->project->fetch_optionals($task->fk_project,'color');

	if (!empty($conf->global->SCRUM_SHOW_LINKED_CONTACT)) getTContact($task);

	return _as_array($task);
}

function getTContact(&$task)
{
	global $db;

	$TInternalContact = $task->liste_contact(-1, 'internal');
	$TExternalContact = $task->liste_contact(-1, 'external');

	$task->internal_contacts = '';
	$task->external_contacts = '';
	if (!empty($TInternalContact))
	{
		dol_include_once('/user/class/user.class.php');
		$user = new User($db);
		foreach ($TInternalContact as &$row)
		{
			$user->id = $row['id'];
			$user->lastname = $row['lastname'];
			$user->firstname = $row['firstname'];
			$task->internal_contacts .= $user->getNomUrl(1).'&nbsp;';
		}
	}

	if (!empty($TExternalContact))
	{
		dol_include_once('/contact/class/contact.class.php');
		$contact = new Contact($db);
		foreach ($TExternalContact as &$row)
		{
			$contact->id = $row['id'];
			$contact->lastname = $row['lastname'];
			$contact->firstname = $row['firstname'];
			$task->internal_contacts .= $contact->getNomUrl(1).'&nbsp;';
		}
	}
}

function _get_task_just_before(&$db, &$task) {
	if($task->rang<=0)return false;

	$sql="SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task
		WHERE rang<".(int)$task->rang."
		ORDER BY rang DESC
		LIMIT 1 ";
	$res=$db->query($sql);
	if($obj=$db->fetch_object($res)) {
		$task_before=new Task($db);
		$task_before->fetch($obj->rowid);
		return $task_before;
	}
	else {
		return false;
	}

}

function _split_task($taskid, $task1time, $task2time) {
    global $db, $user, $conf;

    $task =new Task($db);
    $task->fetch($taskid);
    $task->fetch_optionals($task->id); // Nécessaire de préciser l'id jusqu'à la version 3.8

    $task->planned_workload = $task1time * 3600;
    $task->update($user);

    $task2 = new Task($db);
    foreach($task as $k=>$v) {

        if($k!='id' && $k!='progress' &$k!='duration_effective' && $k!='ref' ) {
            $task2->{$k} = $v;
        }

    }

    $task2->planned_workload = $task2time * 3600;

    $defaultref='';
    $obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
    if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.".php"))
    {
        require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
        $modTask = new $obj;
        $defaultref = $modTask->getNextValue(0,$task2);
    }

    if (is_numeric($defaultref) && $defaultref <= 0) $defaultref='';

    $task2->ref = $defaultref;

    $task2->create($user);

    return $task2->id;
}

function _get_delivery_date_with_velocity(&$db, &$task, $velocity, $time=null) {
global $conf;

	if( (float)DOL_VERSION <= 3.4 || $velocity==0) {
		return 0;

	}
	else {
		$rest = $task->planned_workload - $task->duration_effective; // nombre de seconde restante

		if($conf->global->SCRUM_SET_DELIVERYDATE_BY_OTHER_TASK==0) {
			$time = time();
		}
		else if(is_null($time)) {
			$task_just_before = _get_task_just_before($db, $task);
			if($task_just_before===false) {
				$time = time();
			}
			else {
				$time = _get_delivery_date_with_velocity($db, $task_just_before,$velocity);

			}

			if($time<$task->start_date)$time = $task->start_date;
		}

		$time += ( 86400 * $rest / $velocity  )  ;

		return $time;

	}
}

function _reset_date_task(&$db, $id_project, $velocity) {
global $user;

	if($velocity==0) return false;

	$project=new Project($db);
	$project->fetch($id_project);


	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task
	WHERE fk_projet=".$id_project." AND progress<100
	ORDER BY rang";

	$res = $db->query($sql);

	$current_time = time();

	while($obj = $db->fetch_object($res)) {

		$task=new Task($db);
		$task->fetch($obj->rowid);
		$task->fetch_optionals($obj->rowid); // Otherwise they are scratched in the update

		if($task->progress==0)$task->date_start = $current_time;

		$task->date_end = _get_delivery_date_with_velocity($db, $task, $velocity, $current_time);

		$current_time = $task->date_end;

		$task->update($user);

	}

	$project->date_end = $current_time;
	$project->update($user);

}

function _task_from_line_object(&$PDOdb, &$TLine,$type_object) {

	$TTask = array();

	foreach($TLine as &$line) {

		$n = new TNomenclature;
		$n->loadByObjectId($PDOdb, $fk_commande, $type_object,true,$line->fk_product, $line->qty);

		foreach($n->TNomenclatureWorkstation as &$ws) {

			$TTask[] = array(
                'status'=>1
                ,'id'=>1
                ,'fk_projet'=>1
                ,'label'=>'Simul'
                ,'ref'=>'TKSIMUL'
                , 'grid_col'=>0
                , 'grid_row'=>999999
                ,'fk_workstation'=>$ws->fk_workstation
                ,'fk_product'=>$line->fk_product
                ,'fk_task_parent'=>0
                ,'needed_ressource'=>1
                ,'planned_workload'=>$ws->nb_hour
                ,'progress'=>0
                ,'fk_soc'=>0
                ,'TUser'=>array()
                ,'date_start'=>time()
                ,'date_end'=>0
                ,'date_estimated_end'=>0
         	);

		}

	}
	//var_dump($TTask);

	return $TTask;

}

function _task_commande(&$db, $fk_commande) {
global $conf,$langs, $user;

	if(empty($conf->nomenclature->enabled)) return array();

	$PDOdb=new TPDOdb;

	dol_include_once('/commande/class/commande.class.php');
	dol_include_once('/nomenclature/class/nomenclature.class.php');

	$c = new Commande($db);
	$c->fetch($fk_commande);

	$TTask = _task_from_line_object($PDOdb, $c->lines, 'commande');

	return $TTask;


}
function _task_propal(&$db, $fk_propal) {

	global $conf,$langs, $user;

	if(empty($conf->nomenclature->enabled)) return array();

	$PDOdb=new TPDOdb;

	dol_include_once('/comm/propal/class/propal.class.php');
	dol_include_once('/nomenclature/class/nomenclature.class.php');

	$o = new Propal($db);
	$o->fetch($fk_propal);

	$TTask = _task_from_line_object($PDOdb, $o->lines, 'propal');

	return $TTask;



}

function _tasks_ordo(&$db,&$TWorkstation, $status, $fk_workstation=0) {
    global $conf;
    
    $sql = "SELECT t.rowid,t.label,t.ref,t.fk_task_parent,t.fk_projet, t.grid_col,t.grid_row,ex.fk_workstation,ex.needed_ressource
                ,t.planned_workload,t.progress,t.datee,t.dateo,p.fk_soc,t.date_estimated_end";
                
        if(!empty($conf->asset->enabled)) $sql.= ',ex.fk_product';

		// SCRUM_GROUP_TASK_BY_RAL est la conf qui crée les 2 extrafields au dessous
		if(!empty($conf->global->SCRUM_GROUP_TASK_BY_RAL)) $sql.= ",ex.fk_product_ral";

    $sql.=" FROM ".MAIN_DB_PREFIX."projet_task t 
        LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (t.fk_projet=p.rowid)
        LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields ex ON (t.rowid=ex.fk_object) "; 
        
    if($status=='ideas') {
        $sql.=" WHERE t.progress=0 AND t.datee IS NULL";
    }
    else if($status=='todo') {
        $sql.=" WHERE t.progress=0";
    }
    else if($status=='inprogress|todo') {
        $sql.=" WHERE t.progress>=0 AND t.progress<100 AND t.planned_workload>0";
    }
    else if($status=='inprogress') {
        $sql.=" WHERE t.progress>0 AND t.progress<100";
    }
    else if($status=='finish') {
        $sql.=" WHERE t.progress=100
        ";
    }

    $sql.=" AND p.fk_statut IN (0,1)";

	if($fk_workstation>0)$sql.=" AND ex.fk_workstation=".(int)$fk_workstation;

    if(empty($conf->global->SCRUM_ALLOW_ALL_TASK_IN_GRID)) {
	    $sql.=" AND ex.grid_use=1 ";
    }
    $sql.=" ORDER BY t.grid_row, t.grid_col ";

    $res = $db->query($sql);

    $TTask = array();
    while($obj = $db->fetch_object($res)) {

        $fk_workstation = (int)$obj->fk_workstation;
        if(!isset($TWorkstation[$fk_workstation])) continue;
        $workstation = $TWorkstation[$fk_workstation];

        $TUser =array();
        if(!empty($workstation['TUser'])) {
            foreach($workstation['TUser'] as $idUser=>$user) {

                $TUser[$idUser] = array(
                    'name'=>$user->firstname.' '.$user->lastname
                    ,'selected'=>0
                );

            }

            $task=new Task($db);
            $task->fetch($obj->rowid);
            $TIdContact = $task->getListContactId('internal');
            foreach($TIdContact as $idContact) {
                $TUser[$idContact]['selected']=1;
            }

        }

        $TTask[] = array(
                'status'=>$status
                ,'id'=>$obj->rowid
                ,'fk_projet'=>$obj->fk_projet
                ,'label'=>$obj->label
                ,'ref'=>$obj->ref
                , 'grid_col'=>$obj->grid_col
                , 'grid_row'=>$obj->grid_row
                ,'fk_workstation'=>$fk_workstation
                ,'fk_product'=>(int)$obj->fk_product
                ,'fk_product_ral'=>empty($conf->global->SCRUM_GROUP_TASK_BY_RAL) ? 0 : (int)$obj->fk_product_ral
                ,'fk_task_parent'=>(int)$obj->fk_task_parent
                ,'needed_ressource'=>($obj->needed_ressource ? $obj->needed_ressource : 1)
                ,'planned_workload'=>$obj->planned_workload / 3600
                ,'progress'=>$obj->progress
                ,'fk_soc'=>$obj->fk_soc
               /* ,'fk_soc_order'=>$obj->fk_soc_order*/
                ,'TUser'=>$TUser
                ,'date_start'=>strtotime($obj->dateo)
                ,'date_end'=>strtotime($obj->datee)
                ,'date_estimated_end'=>strtotime($obj->date_estimated_end)
         );
    }



    return $TTask;

}
function _tasks(&$db, $id_project, $status, $onlyUseGrid = false) {
	global $hookmanager,$conf;
	$hookmanager->initHooks(array('scrumboardgettasks'));

	$sql = "SELECT t.rowid,t.fk_task_parent, t.grid_col,t.grid_row,ex.fk_workstation,ex.needed_ressource,p.datee as 'project_date_end', t.note_private
		FROM ".MAIN_DB_PREFIX."projet_task t
		LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (t.fk_projet=p.rowid)
		LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields ex ON (t.rowid=ex.fk_object) ";

	$sqlwhere = array();
	$sqlorder='';

	if (empty($conf->global->SCRUM_SHOW_TASK_WITHOUT_DURATION)) {
		$sqlwhere[]= " t.planned_workload>0 ";
	}

	if($status=='ideas') {
		$sqlwhere[]=" t.progress=0 AND t.datee IS NULL";
	}
	else if($status=='todo') {
		$sqlwhere[]=" t.progress=0";
	}
	else if($status=='inprogress|todo') {
		$sqlwhere[]=" t.progress>=0 AND t.progress<100";
	}
	else if($status=='inprogress') {
		$sqlwhere[]=" t.progress>0 AND t.progress<100";
	}
	else if($status=='finish') {
		$sqlwhere[]=" t.progress=100";
	}

	if($id_project) $sqlwhere[]=" t.fk_projet=".$id_project;
	else $sqlwhere[]=" p.fk_statut IN (0,1)";

	if($onlyUseGrid) {
		$sqlwhere[]=" ex.grid_use=1 ";

	    if (empty($conf->global->SCRUM_SHOW_TASK_WITHOUT_DURATION)) {
	    	$sqlwhere[]= " t.planned_workload>0 ";
	    }
	    $sqlorder = " ORDER BY t.grid_row";
    }
    else{
    	$sqlorder=" ORDER BY rang";
    }

    if (count($sqlwhere)>0) {
    	$sql .= " WHERE ".implode(' AND ',$sqlwhere);
    }

    if (!empty($sqlorder)) {
    	$sql .=$sqlorder;
    }

	$parameters=array('action'=>'_tasks_before_exec_sql', 'sql'=>&$sql, 'status'=>$status, 'fk_project'=>$id_project, 'onlyUseGrid'=>$onlyUseGrid);
	$reshook=$hookmanager->executeHooks('doScrumActions',$parameters);

	$res = $db->query($sql);
	$TTask = array();

	if ($res)
	{
		while($obj = $db->fetch_object($res)) {
			$TTask[] = array_merge(
				_task($db, $obj->rowid)
			 	, array(
			 		'status'=>$status
			 		, 'grid_col'=>$obj->grid_col
			 		, 'grid_row'=>$obj->grid_row
			 		,'fk_workstation'=>(int)$obj->fk_workstation
			 		,'fk_task_parent'=>(int)$obj->fk_task_parent
			 		,'needed_ressource'=>($obj->needed_ressource ? $obj->needed_ressource : 1)
			 		,'project_date_end'=>strtotime($obj->project_date_end)
				)
			 );
		}
	}


	return $TTask;
}
