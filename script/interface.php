<?php

require ('../config.php');

$get = GETPOST('get','alpha');
$put = GETPOST('put','alpha');
	
_put($db, $put);
_get($db, $get);

function _get(&$db, $case) {
	switch ($case) {
		case 'tasks' :
			
			print json_encode(_tasks($db, (int)GETPOST('id_project'), GETPOST('status')));

			break;
		case 'task' :
			
			print json_encode(_task($db, (int)GETPOST('id')));

			break;
			
		case 'velocity':
			
			print json_encode(_velocity($db, (int)GETPOST('id_project')));
			
			break;
	}

}

function _put(&$db, $case) {
	switch ($case) {
		case 'task' :
			
			print json_encode(_task($db, (int)GETPOST('id'), $_REQUEST));
			
			break;
			
		case 'sort-task' :
			
			_sort_task($db, $_REQUEST['TTaskID'],$_REQUEST['list']);
			
			break;
		case 'reset-date-task':
			
			_reset_date_task($db,(int)GETPOST('id_project'), (float)GETPOST('velocity') * 3600);
			
			break;

	}

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
	
	if(strpos($listname, 'inprogress')!==false)$step = 1000;
	else if(strpos($listname, 'todo')!==false)$step = 2000;
	else $step = 0;
	
	foreach($TTask as $rank=>$id) {
		$task=new Task($db);
		$task->fetch($id);
		$task->rang = $step + $rank;
		$task->update($db);
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
global $user, $langs;

	$task=new Task($db);
	if($id_task) $task->fetch($id_task);
	
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
	
	$task->aff_time = convertSecondToTime($task->duration_effective);
	$task->aff_planned_workload = convertSecondToTime($task->planned_workload);

	$task->long_description.='';
	if($task->date_start>0) $task->long_description .= $langs->trans('TaskDateStart').' : '.dol_print_date($task->date_start).'<br />';
	if($task->date_end>0) $task->long_description .= $langs->trans('TaskDateEnd').' : '.dol_print_date($task->date_end).'<br />';
	if($task->date_delivery>0 && $task->date_delivery>$task->date_end) $task->long_description .= $langs->trans('TaskDateShouldDelivery').' : '.dol_print_date($task->date_delivery).'<br />';
	
	$task->long_description.=$task->description;

	$task->project = new Project($db);
	$task->project->fetch($task->fk_project);

	return _as_array($task);
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
		
		if($task->progress==0)$task->date_start = $current_time;
		
		$task->date_end = _get_delivery_date_with_velocity($db, $task, $velocity, $current_time);
		
		$current_time = $task->date_end;
		
		$task->update($user);
		
	}
	
	$project->date_end = $current_time;
	$project->update($user);

}

function _tasks(&$db, $id_project, $status) {
		
	if($status=='ideas') {
		$sql = "SELECT t.rowid 
		FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (t.fk_projet=p.rowid) 
		WHERE t.progress=0 AND t.datee IS NULL";
		
	}	
	else if($status=='todo') {
		$sql = "SELECT t.rowid 
		FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (t.fk_projet=p.rowid) 
		WHERE t.progress=0";
	}
	else if($status=='inprogress') {
		$sql = "SELECT t.rowid 
		FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (t.fk_projet=p.rowid) 
		WHERE t.progress>0 AND t.progress<100";
	}
	else if($status=='finish') {
		$sql = "SELECT t.rowid 
		FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (t.fk_projet=p.rowid) 
		WHERE t.progress=100 
		";
	}
	
	if($id_project) $sql.=" AND t.fk_projet=".$id_project; 
	else $sql.=" AND p.fk_statut IN (0,1)";	
		
	$sql.=" ORDER BY rang";	
		
	$res = $db->query($sql);	
		
		
	$TTask = array();
	while($obj = $db->fetch_object($res)) {
		$TTask[] = array_merge( _task($db, $obj->rowid) , array('status'=>$status));
	}
	
	return $TTask;
}
