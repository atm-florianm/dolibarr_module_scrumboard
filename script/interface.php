<?php

require ('../config.php');

$get = GETPOST('get','alpha');
$put = GETPOST('put','alpha');
	
_put($db, $put);
_get($db, $get);

function _get(&$db, $case) {
global $conf;    
    
	switch ($case) {
		case 'tasks' :
			
			$onlyUseGrid = isset($_REQUEST['gridMode']) && $_REQUEST['gridMode']==1 ? true : false;
			
			$var = explode('|',GETPOST('status'));
			$Tab=array();
			foreach($var as $statut) {
				$Tab=array_merge($Tab, _tasks($db, (int)GETPOST('id_project'), $statut, $onlyUseGrid));	
			}
			
			print json_encode($Tab);

			break;
            
        case 'tasks-ordo':
           $TWorkstation = array(
                0=>array('nb_ressource'=>1, 'velocity'=>1, 'background'=>'linear-gradient(to right,white, #ccc)', 'name'=>'Non ordonnancé') // base de 7h par jour
            );
            
            if($conf->workstation->enabled) {
                define('INC_FROM_DOLIBARR',true);
                dol_include_once('/workstation/config.php');
                $ATMdb=new TPDOdb;
                $TWorkstation=array_merge($TWorkstation, TWorkstation::getWorstations($ATMdb,true));
                
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

		case 'coord':
			_coord($db, $_POST['coord']);
			
			break;
        case 'sort-task-ws' :
            
            print _sort_task_ws($db, GETPOST('TTaskID'));
            
            break;
		
        case 'ws':
            print _task_ws($db, GETPOST('taskid'), GETPOST('fk_workstation'));
        
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
    
      $sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_extrafields SET
            fk_workstation=".(int)$fk_workstation."
        WHERE fk_object = ".(int)$taskid;
      $db->query($sql);
      return 1;

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
    $task->time_rest = $task->planned_workload * (1 - ($task->progress / 100) );
    $task->aff_time_rest = $langs->trans('TimeRest').' : '.convertSecondToTime($task->time_rest);

	$task->long_description.='';
	if($task->date_start>0) $task->long_description .= $langs->trans('TaskDateStart').' : '.dol_print_date($task->date_start).'<br />';
	if($task->date_end>0) $task->long_description .= $langs->trans('TaskDateEnd').' : '.dol_print_date($task->date_end).'<br />';
	if($task->date_delivery>0 && $task->date_delivery>$task->date_end) $task->long_description .= $langs->trans('TaskDateShouldDelivery').' : '.dol_print_date($task->date_delivery).'<br />';
	
	$task->long_description.=$task->description;

	$task->project = new Project($db);
	$task->project->fetch($task->fk_project);
	$task->project->fetch_optionals($task->fk_project,'color');
	
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
function _tasks_ordo(&$db,&$TWorkstation, $status, $fk_workstation=0) {
    
    $sql = "SELECT t.rowid,t.label,t.ref,t.fk_task_parent,t.fk_projet, t.grid_col,t.grid_row,ex.fk_workstation,ex.needed_ressource,t.planned_workload,t.progress,t.datee,p.fk_soc
        FROM ".MAIN_DB_PREFIX."projet_task t 
        LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (t.fk_projet=p.rowid)
        LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields ex ON (t.rowid=ex.fk_object) "; 
        
    if($status=='ideas') {
        $sql.=" WHERE t.progress=0 AND t.datee IS NULL";
    }   
    else if($status=='todo') {
        $sql.=" WHERE t.progress=0";
    }
    else if($status=='inprogress|todo') {
        $sql.=" WHERE t.progress>=0 AND t.progress<100";
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
		
    $sql.=" AND ex.grid_use=1 
        ORDER BY t.grid_row";
        
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
                ,'fk_task_parent'=>(int)$obj->fk_task_parent
                ,'needed_ressource'=>($obj->needed_ressource ? $obj->needed_ressource : 1) 
                ,'planned_workload'=>$obj->planned_workload / 3600
                ,'progress'=>$obj->progress
                ,'fk_soc'=>$obj->fk_soc
                ,'TUser'=>$TUser
                ,'date_end'=>strtotime($obj->datee)
         );
    }
    
   
    
    return $TTask;
    
}
function _tasks(&$db, $id_project, $status, $onlyUseGrid = false) {
	
	$sql = "SELECT t.rowid,t.fk_task_parent, t.grid_col,t.grid_row,ex.fk_workstation,ex.needed_ressource,p.datee as 'project_date_end'
		FROM ".MAIN_DB_PREFIX."projet_task t 
		LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (t.fk_projet=p.rowid)
		LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields ex ON (t.rowid=ex.fk_object) ";	
		
	if($status=='ideas') {
		$sql.=" WHERE t.progress=0 AND t.datee IS NULL";
	}	
	else if($status=='todo') {
		$sql.=" WHERE t.progress=0";
	}
	else if($status=='inprogress|todo') {
		$sql.=" WHERE t.progress>=0 AND t.progress<100";
	}
	else if($status=='inprogress') {
		$sql.=" WHERE t.progress>0 AND t.progress<100";
	}
	else if($status=='finish') {
		$sql.=" WHERE t.progress=100 
		";
	}
	
	if($id_project) $sql.=" AND t.fk_projet=".$id_project; 
	else $sql.=" AND p.fk_statut IN (0,1)";	
		
	if($onlyUseGrid) {
	    $sql.=" AND ex.grid_use=1 
	    ORDER BY t.grid_row";
    }
    else{
        $sql.=" ORDER BY rang";    
    }	
		
	$res = $db->query($sql);	
		
	$TTask = array();
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
	
	return $TTask;
}
