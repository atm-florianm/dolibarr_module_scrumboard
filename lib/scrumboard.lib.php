<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file		lib/scrumboard.lib.php
 *	\ingroup	scrumboard
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function scrumboardAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("scrumboard@scrumboard");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/scrumboard/admin/scrumboard_setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/scrumboard/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@scrumboard:/scrumboard/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@scrumboard:/scrumboard/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'scrumboard');

    return $head;
}

function scrum_getVelocity(&$db, $id_project) {
	global $conf;
	
	$n_day = ($conf->global->SCRUM_VELOCITY_NUMBER_OF_DAY) ? $conf->global->SCRUM_VELOCITY_NUMBER_OF_DAY : 14;
	
	$t2week= strtotime('-'.$n_day.' days');
	
	$projet=new Project($db);
	$projet->fetch($id_project);
	
	if($projet->date_start>$t2week) $t2week = $projet->date_start;

	$res=$db->query("SELECT SUM(tt.task_duration) as task_duration 
	FROM ".MAIN_DB_PREFIX."projet_task_time tt LEFT JOIN ".MAIN_DB_PREFIX."projet_task t ON (tt.fk_task=t.rowid)
	WHERE tt.task_date>='".date('Y-m-d', $t2week)."' AND t.fk_projet=".$id_project);
	
	$velocity = 0;
	if($obj=$db->fetch_object($res)) {
		 $velocity = round($obj->task_duration / ((time() - $t2week) / 86400));
	}
	
	if($velocity==0)$velocity = (int)$conf->global->SCRUM_DEFAULT_VELOCITY * 3600;

	return $velocity;	
}

function ordonnanceur_link_event(&$Task) {
    global $db, $user;
    
    dol_include_once('/comm/action/class/actioncomm.class.php');
    
    foreach($Task['tasks'] as &$task) {
        
         $t_start = $task['time_estimated_start'];
         $t_end = $task['time_estimated_end'];
         
         $res = $db->query("SELECT id FROM ".MAIN_DB_PREFIX."actioncomm WHERE elementtype='project_task' AND fk_element=".(int)$task['id'] );
         
         $TUserAssigned = array();
         foreach($task['TUser'] as $idContact=>$u) {
             if($u['selected']) {
                 $TUserAssigned[]=array('id'=>$idContact);
             }
         }
         
         
         if($obj = $db->fetch_object($res)) {
    
            $a=new ActionComm($db);
            $a->fetch($obj->id);         
            $a->datep = $t_start;
            $a->datef = $t_end;
            $a->durationp = $task['planned_workload'] * 3600;
            $a->label = $task['ref'] .' '. $task['label'] ;
            $a->progress = $task['progress'];
            $a->fk_project = $task['fk_projet']; 
            $a->userassigned = $TUserAssigned;
            $a->socid = $task['fk_soc'];
            
            $a->update($user);
          //  print "update ".$a->id.'<br />';
         }
         else {
    
             $a=new ActionComm($db);
             $a->datep = $t_start;
             $a->datef = $t_end;
             
             $a->userownerid = $user->id;
             $a->type_code='AC_OTH_AUTO';
             $a->label = $task['ref'] .' '. $task['label'] ;
             
             $a->elementtype='project_task';
             $a->fk_element = $task['id'];
             $a->fk_project = $task['fk_projet'];
            
             $a->progress = $task['progress'];
             
             $a->durationp = $task['planned_workload'] * 3600;
             $a->userassigned = $TUserAssigned;
             
             $a->socid = $task['fk_soc'];
             
             $a->add($user);
                 
             
         }
         
        
    }
    
}

function _ordo_int_get_good_row_product(&$TTaskToOrder, &$taskToMove, $tolerance) {
    
    $good_date = false;
    $grid_row = 999999;
    
    foreach($TTaskToOrder as &$task) {
        
       if($task['grid_row']!=999999 && $task['fk_workstation'] ==  $taskToMove['fk_workstation'] && $task['fk_product'] == $TTaskToOrder['fk_product']) {
               
           if($TTaskToOrder['date_end'] == false && ($task['grid_row']>$grid_row || $grid_row == 999999 ) ) {
               $grid_row = $task['grid_row'];    
           }
           else if($TTaskToOrder['date_end'] != false && (abs($task['date_end']-$TTaskToOrder['date_end'])<=$tolerance * 86400 ) ) {
               $grid_row = $task['grid_row'];
           }
           
       }
        
    }
    
    return ($grid_row == 999999) ? 999999 : $grid_row+0.0001;
    
}

function _ordo_init_new_task(&$TTaskToOrder) {
    global $conf;
    
    foreach($TTaskToOrder as &$task) {
        if($task['grid_row'] == 999999) {
            
            if(!empty($conf->global->SCRUM_GROUP_TASK_BY_PRODUCT) && $task['fk_product']>0 ) {
                
                $task['grid_row'] = _ordo_int_get_good_row_product($TTaskToOrder, $task, $conf->global->SCRUM_GROUP_TASK_BY_PRODUCT_TOLERANCE);
                
            }
            
            
            
        }
        
    }
    
}

function _ido_add_immobilisation_event(&$PDOdb, &$smallGeoffrey, &$TOff, $fk_workstation, $nb_ressource_max, $time_init,$nb_second_in_hour,$t_start_ordo, $t_end_ordo) {
	
	if($fk_workstation<=0) return false;
	
	/*$sql = "SELECT ac.label, acex.needed_ressource, ac.datep as 'date_deb', ac.datep2 as 'date_fin' 
	FROM ".MAIN_DB_PREFIX."actioncomm ac LEFT JOIN ".MAIN_DB_PREFIX."actioncomm_extrafields acex ON (acex.fk_object=ac.id)
	WHERE ac.datep>='".date('Y-m-d',$time_init)."' AND ac.datep2<='".date('Y-m-d',$time_init + 86400 * 90 )."' AND acex.fk_workstation=".$fk_workstation;
	//var_dump($sql,$time_init);
	$Tab = $PDOdb->ExecuteAsArray($sql);
	
	foreach($Tab as $row) {
		$nb_ressource = $row->needed_ressource;
		if($nb_ressource<=0 || $nb_ressource > $nb_ressource_max) $nb_ressource = $nb_ressource_max;
		
		$time_start = strtotime($row->date_deb);
		$time_end = strtotime($row->date_fin);
		
		if(date('Hi', $time_start)<date('Hi', $t_start_ordo)) $time_start = strtotime( date('Y-m-d ', $time_start ).' '.date('H:i:s',$t_start_ordo) );
		if(date('Hi', $time_end)>date('Hi', $t_end_ordo)) $time_end = strtotime( date('Y-m-d ', $time_end ).' '.date('H:i:s',$t_end_ordo) );
		
		if($time_end<$time_start) continue; // pas de date de fin
		
		$height = $time_end - $time_start;
		
		$t_start = $time_start - $time_init;
		
		$top = $t_start / $nb_second_in_hour;
    	$height = $height / 3600;
		
		$TOff[] = array('top'=>$top,'left'=>0,'height'=>$height,'nb_ressource'=>$nb_ressource,'title'=>$row->label, 'class'=>'event'); 
		$smallGeoffrey->addBox($top, 0, $height, $nb_ressource);
		
	}
	*/
	return true;
}

function _ordo_ido_get($time_off_start, $day_moment, $nb_ressource, $time_init, $nb_second_in_hour) {
    global $langs;
	
	
    $t_start = $time_off_start - $time_init;
//            var_dump($time_off_start, $day_moment, $nb_ressource, $time_init, $nb_second_in_hour);exit;
    if($day_moment == 'ALL') {
        $t_end = $t_start + 86400;
        $height = 86400;
    }
    else if($day_moment == 'AM') {
        $t_end = $t_start + 43200;
        $height = 43200;
    }
    else if($day_moment == 'TINY_AM') {
	$t_end = $start + 43199;
	$height = $nb_second_in_hour;
    }
    else if($day_moment == 'TINY_PM') {
	$t_start += 86399;
        $t_end = $start + 1;
	$height = 1;
    }

    else {
        $t_start += 43200;
        $t_end = $t_start + 43200;
        $height = 43200;
    }
    
    $top = $t_start / $nb_second_in_hour;
    $height = $height / $nb_second_in_hour;
    $left = 0;
    
   return array('top'=>$top,'left'=>$left,'height'=>$height,'nb_ressource'=>$nb_ressource,'title'=>$langs->trans('DontWork')); 
    
}

function _ordo_init_dayOff(&$smallGeoffrey, $fk_workstation, $time_init, $time_day, $nb_second_in_hour, $velocity) {
    global $conf;	
		
    $TOff = array();
     
    $PDOdb = new TPDOdb;
    
    $ws = new TWorkstation;
	//var_dump($fk_workstation);
    $ws->load($PDOdb, $fk_workstation);
    
	// TODO function asshole
	$t_start_ordo = strtotime(date('Y-m-d').' '. $conf->global->SCRUM_TIME_ORDO_START, $time_day);
	$t_end_ordo = strtotime(date('Y-m-d').' '. $conf->global->SCRUM_TIME_ORDO_END, $time_day);
	$t_diff = $t_end_ordo - $t_start_ordo; 
    // task for past of day
    
    
    if(empty($conf->global->SCRUM_TIME_ORDO_START) || empty($conf->global->SCRUM_TIME_ORDO_END)) {
    	$height_of_past_day = ($time_init - $time_day) / $nb_second_in_hour;	
    }
	else if($time_init<$t_start_ordo) {
    	$height_of_past_day = 0;
    }
	else if($time_init>$t_end_ordo) {
    	$height_of_past_day = 86400 / $nb_second_in_hour;
    }
	else {
		$height_of_past_day  = ($time_init - $t_start_ordo) / $t_diff * 86400 / $nb_second_in_hour;
	}
    //var_dump($height_of_past_day, $nb_second_in_hour);exit;
    
    if($height_of_past_day>0) {
    	$smallGeoffrey->addBox(0, 0,  $height_of_past_day , $ws->nb_ressource);
    	$TOff[] = array('top'=>0,'left'=>0,'height'=>$height_of_past_day,'nb_ressource'=>$ws->nb_ressource, 'class'=>'past','title'=>$fk_workstation.'. Passé'); 
	}
  //  var_dump($height_of_past_day,$smallGeoffrey );
    
    $TDayWeekOff=array();
    foreach($ws->TWorkstationSchedule as &$sc) {
    
        if($sc->nb_ressource == 0) $sc->nb_ressource = $ws->nb_ressource; 
    
        if($sc->date_off>0) {
            
           $TRow = _ordo_ido_get($sc->date_off, $sc->day_moment, $sc->nb_ressource, $time_init, $nb_second_in_hour);
           $TOff[]=$TRow;  
           $smallGeoffrey->addBox($TRow['top'], $TRow['left'], $TRow['height'], $TRow['nb_ressource']);
           
        }     
        else{
           
           if(!isset($TDayWeekOff[$sc->week_day]))$TDayWeekOff[$sc->week_day] = array('AM'=>0,'PM'=>0, 'nb_ressource'=>0);
           
           if($sc->day_moment == 'AM')$TDayWeekOff[$sc->week_day]['AM'] = 1;
           else if($sc->day_moment == 'PM')$TDayWeekOff[$sc->week_day]['PM'] = 1;
           else if($sc->day_moment == 'TINY_PM')$TDayWeekOff[$sc->week_day]['TINY_PM'] = 1;
           else if($sc->day_moment == 'TINY_AM')$TDayWeekOff[$sc->week_day]['TINY_AM'] = 1;
           else $TDayWeekOff[$sc->week_day]['AM'] = $TDayWeekOff[$sc->week_day]['PM'] = 1; 
            
           if($TDayWeekOff[$sc->week_day]['nb_ressource']<$sc->nb_ressource)$TDayWeekOff[$sc->week_day]['nb_ressource'] = $sc->nb_ressource;
            
        }
        
    }
    
	/*
	 * Jour non travaillé
	 */
    $t_end_3month = strtotime('+3month', $time_day);
    $t_current = $time_init;
    
    while($t_current<$t_end_3month) {
        
        $dw = (int)date('w', $t_current);
        
        $TRow=array();
        if(!empty($TDayWeekOff[$dw]['AM']) && !empty($TDayWeekOff[$dw]['PM'])) {
            $TRow = _ordo_ido_get($t_current, 'ALL', $TDayWeekOff[$dw]['nb_ressource'], $time_init, $nb_second_in_hour);
           
        }
        else if(!empty($TDayWeekOff[$dw]['AM'])) {
            $TRow = _ordo_ido_get($t_current, 'AM', $TDayWeekOff[$dw]['nb_ressource'], $time_init, $nb_second_in_hour);
        } 
        else if(!empty($TDayWeekOff[$dw]['PM'])) {
            $TRow = _ordo_ido_get($t_current, 'PM', $TDayWeekOff[$dw]['nb_ressource'], $time_init, $nb_second_in_hour);
        } 
        else if(!empty($TDayWeekOff[$dw]['TINY_AM'])) {
            $TRow = _ordo_ido_get($t_current, 'TINY_AM', $TDayWeekOff[$dw]['nb_ressource'], $time_init, $nb_second_in_hour);
        }
	else if(!empty($TDayWeekOff[$dw]['TINY_PM'])) {
            $TRow = _ordo_ido_get($t_current, 'TINY_PM', $TDayWeekOff[$dw]['nb_ressource'], $time_init, $nb_second_in_hour);
        }
        if(!empty($TRow)) {
           $TOff[]=$TRow;  
           $smallGeoffrey->addBox($TRow['top'], $TRow['left'], $TRow['height'], $TRow['nb_ressource']);
        }
        
        $t_current = strtotime('+1day', $t_current);   
    }
   
    _ido_add_immobilisation_event($PDOdb, $smallGeoffrey, $TOff, $fk_workstation, $ws->nb_ressource, $time_init,$nb_second_in_hour,$t_start_ordo, $t_end_ordo);
   
  
    //if(!empty($TPlanned)) $TFree = _ordo_gnc_get_free_place($TPlanned, $ws->nb_ressource,true);
        
    $PDOdb->close();
   
    return $TOff;
}

/*
 * Refresh task position
 */
function ordonnanceur(&$TTaskToOrder, &$TWorkstation ,$fk_workstation_to_order=0,$update_base=true) {
global $conf,$db;    
    if(isset($_REQUEST['DEBUG2'])) print count($TTaskToOrder);
    $Tab = $TTaskOrdered = array();
  
	$TCol = $TRow = $TPlan = array();
    
    $time_day = strtotime(date('Y-m-d'));
    $time_init = time();
    $t_ecart = $time_init - $time_day;
  //  var_dump($TWorkstation );
    
    $nb_hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 7;
    $nb_second_in_hour = 3600 * (24 / $nb_hour_per_day);
    
    $grid_decalage = $t_ecart / $nb_second_in_hour;
    
    $TDayOff=$TSmallGeoffrey = array();
    if( $fk_workstation_to_order == 0 ) {
        foreach($TWorkstation as $fk_workstation=> &$ws) {
             
             if(!isset($TSmallGeoffrey[$fk_workstation])) $TSmallGeoffrey[$fk_workstation] = new TSmallGeoffrey($ws['nb_ressource'], $ws['nb_hour_before'], $ws['nb_hour_after']);
             if(!isset( $TDayOff[$fk_workstation] )) $TDayOff[$fk_workstation] = _ordo_init_dayOff($TSmallGeoffrey[$fk_workstation], $fk_workstation, $time_init, $time_day, $nb_second_in_hour, $ws['velocity']);
        }
    }
    
    _ordo_init_new_task($TTaskToOrder);
    
    foreach($TTaskToOrder as $task) {
         
       $fk_workstation = (int)$task['fk_workstation'];
	   
       if(!isset($TWorkstation[$fk_workstation])) continue; //$fk_workstation = 0;
	   
       if(!isset($TPlan[$fk_workstation])) {
       	
			$ws_nb_ressource = (int)$TWorkstation[$fk_workstation]['nb_ressource'];
			if($ws_nb_ressource<1)$ws_nb_ressource = 1;
			$ws_velocity = (float)$TWorkstation[$fk_workstation]['velocity'];	
			if($ws_velocity<0.01)$ws_velocity=1;
		
	   		$TPlan[$fk_workstation]=array(
				'@param'=>array(
					'available_ressource'=>$ws_nb_ressource
					,'velocity'=>$ws_velocity
				)
				,'@plan'=>array(
				
				)
				,'@free'=>array(
                
                )
			);
	   }
      
       if( $fk_workstation_to_order == 0  ||  $fk_workstation == $fk_workstation_to_order ) {
               if(!isset($TSmallGeoffrey[$fk_workstation])) $TSmallGeoffrey[$fk_workstation] = new TSmallGeoffrey($ws_nb_ressource, $TWorkstation[$fk_workstation]['nb_hour_before'], $TWorkstation[$fk_workstation]['nb_hour_after']);
               if(!isset( $TDayOff[$fk_workstation] )) $TDayOff[$fk_workstation] = _ordo_init_dayOff($TSmallGeoffrey[$fk_workstation], $fk_workstation, $time_init, $time_day, $nb_second_in_hour, $ws_velocity);
              
       	       $velocity = $TPlan[$fk_workstation]['@param']['velocity'];
               if($velocity<=0)$velocity=1;
               $height = $task['planned_workload'] / $velocity * (1- ($task['progress'] / 100));
			   
			   //$height+=$TWorkstation[$fk_workstation]['nb_hour_before'] + $TWorkstation[$fk_workstation]['nb_hour_after'];
			   
               //var_dump($task['progress'],$velocity);
               
               $t_nb_ressource = $task['needed_ressource']>0 ? $task['needed_ressource'] : 1;
               
               
               if(isset($_REQUEST['DEBUG_ORDO'])) {
               		$TSmallGeoffrey[$fk_workstation]->debug = true;
				    $TSmallGeoffrey[$fk_workstation]->debug_info = 'Taskid='. $task['id'];
			   }

               if($task['date_start']>$time_day && $fk_workstation>0) {
                   // la date de début est dans le future
                   $t_start_ecart =  $task['date_start'] - $time_day;
                   $y_start_ecart = $t_start_ecart / $nb_second_in_hour;
                   
               }
               else {
                   $y_start_ecart = 0;
               }

               list($col, $row, $grid_height) = $TSmallGeoffrey[$fk_workstation]->getNextPlace($height,$t_nb_ressource, (int)$task['fk_task_parent'] , $y_start_ecart);
               
               $TSmallGeoffrey[$fk_workstation]->addBox($row,$col, $grid_height, $t_nb_ressource, $task['id'], $task['fk_parent']);
               
	   		   //list($col, $row) = _ordonnanceur_get_next_coord($TWorkstation, $TPlan[$fk_workstation], $task, $height);  
               
               //$row+=$grid_decalage;
               
	  		   $task['grid_col'] = $col;
       		   $task['grid_row'] = $row;
			   $task['grid_height'] = $grid_height;
	  
      //TODO prendre en compte les jours non travaillé
               $task['time_estimated_start'] = $time_day + ($row * $nb_second_in_hour);
               $task['time_estimated_end'] =  $task['time_estimated_start'] + ($height  *$nb_second_in_hour) ;
               $task['time_projection'] ='Début prévu : '.dol_print_date($task['time_estimated_start'],'daytext').', '.getHourInDay($task['time_estimated_start'])
                    .'<br />Fin prévue : '.dol_print_date($task['time_estimated_end'],'daytext').', '.getHourInDay($task['time_estimated_end']);
               
			    if($update_base) {
			        
			        $sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET
			                grid_col=".$task['grid_col']."
			                , grid_row=".$task['grid_row']."
			                , grid_height=".$grid_height."
			                , date_estimated_start = '".date('Y-m-d H:i:s',$task['time_estimated_start'])."'
			                , date_estimated_end = '".date('Y-m-d H:i:s',$task['time_estimated_end'])."'
			                WHERE rowid = ".$task['id'];
			              
			        $db->query($sql);
			       
			    }
			   
                $TTaskOrdered[] = $task;
       }
    }
     
    //$TTimeScale = scrumboard_get_time_scale($TTaskOrdered, $time_init); 
     //var_dump($TPlan[2]['@free']);exit;
    return array('tasks'=>$TTaskOrdered, 'timeScale'=>$TTimeScale, 'dayOff'=>$TDayOff);
}

function scrumboard_get_time_scale(&$TTaskOrdered, $time_init) {
global $conf;

    $TDayOff=array();
    if(!empty($conf->global->TIMESHEET_DAYOFF)) $TDayOff = array_flip(explode(',', $conf->global->TIMESHEET_DAYOFF));         // 0,6
    
    $TFerie=array();
    
    if(!empty($conf->jouroff->enabled)) {
        define('INC_FROM_DOLIBARR',true);
        dol_include_once('/jouroff/config.php');
        dol_include_once('/jouroff/class/jouroff.class.php');
        $PDOdb=new TPDOdb;
        $TRes = TRH_JoursFeries::getAll($PDOdb, date('Y-m-d'), date('Y-m-d', strtotime('+1year')));
        $TFerie = array();
        foreach($TRes as $row) {
            $TFerie[substr($row->date_jourOff,0,10)] = 1;
        }
    }
 
    $Tab = array();
   
    foreach($TTaskOrdered as &$task) {
            
            $t_start = $task['time_estimated_start'];
            $t_end = $task['time_estimated_end'];
              
            $t_ecart = $t_end - $t_start;     
            $t_start = _scrumboard_gts_next($t_start, $TFerie, $TDayOff);
            $t_end = $t_start + $t_ecart;
            
            $t_current = $t_start;
            while($t_current<=$t_end) {

                $t2 = _scrumboard_gts_next($t_current, $TFerie, $TDayOff);
                
                if($t2!=$t_current) {
                    $t_end+=($t2-$t_current);
                    $t_current = $t2;
                }
                
                $t_current=strtotime('+1 day',$t_current);
            }
            
            $t_end = _scrumboard_gts_next($t_end, $TFerie, $TDayOff);
            
            $task['time_estimated_start'] = $t_start;
            $task['time_estimated_end'] = $t_end;
            
            $task['time_projection'] ='Début prévu : '.dol_print_date($task['time_estimated_start'],'daytext').', '.getHourInDay($task['time_estimated_start'])
                    .'<br />Fin prévue : '.dol_print_date($task['time_estimated_end'],'daytext').', '.getHourInDay($task['time_estimated_end']);
        
    }
   
   
    return $Tab;
    
}
function _scrumboard_gts_next($time, &$TFerie, &$TDayOff) {
     
      $t_current = $time;
      
      $cpt = 0;
      while( ( isset($TFerie[date('Y-m-d', $t_current)]) || isset($TDayOff[ date('w', $t_current) ] ) ) && $cpt<50) {
         $t_current=strtotime('+1 day',$t_current);
      
        $cpt++;    
      }
      
      return $t_current;
    
}

function getHourInDay($time) {
    global $langs;
    
    $h = date('H', $time);
    
    if($h<5) return $langs->trans('OrdoEarlyMorning');
    else if($h<10) return $langs->trans('OrdoMorning');
    else if($h<13) return $langs->trans('OrdoLateMorning');
    else if($h<15) return $langs->trans('OrdoAfternoon');
    else if($h<18) return $langs->trans('OrdoLateAfternoon');
    else if($h<21) return $langs->trans('OrdoEvening');
    else return $langs->trans('OrdoLateEvening');
    
}

/*
 * Get new position for task
 */
function _ordonnanceur_get_next_coord(&$TWorkstation, &$TPlan,&$task,$height) {
global $db;

    $available_ressource = $TPlan['@param']['available_ressource'];
    
    
    if($available_ressource<1) return array(0,0); // cas impossible :-|
    
    $TFree = &$TPlan['@free'];
    $TPlanned = &$TPlan['@plan'];

    $needed_ressource = $task['needed_ressource'];
    if($needed_ressource<0)$needed_ressource = 1;
	
    $col = 0;
    
    if(empty($TFree)){
        $TFree[] = array(0, 0, false, $available_ressource); // y,x,h,w de largeur tous à partir de 0 à gauche et 0 en haut et d'une hauteur infinie
    }
    
    list($col, $top) = _ordo_gnc_get_free($TWorkstation, $TFree, $TPlanned,$available_ressource, $needed_ressource, $height, $task);
    
   
   
    return array($col, $top);
}
/*
 * Get position for parent task
 */
function _ordo_get_parent_coord(&$TWorkstation, &$TPlanned, $fk_task_parent) {
    global $db; 
    
    if($fk_task_parent>0) {
        // dans la même file ? 
        foreach($TPlanned as $planned) {
            if($planned[4] == $fk_task_parent) {
                return array($planned[0] + $planned[2] ,0);
            }
        }
    
        $sql = "SELECT t.grid_row,t.planned_workload,t.progress,tex.fk_workstation 
            FROM ".MAIN_DB_PREFIX."projet_task t 
            LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (t.rowid=tex.fk_object)
            WHERE t.rowid = ".$fk_task_parent." AND t.progress<100 AND tex.grid_use = 1";
        $res = $db->query($sql);    
        
        $obj = $db->fetch_object($res);
        if($obj) {
           
            $fk_worstation = isset($TWorkstation[$obj->fk_workstation]) ? $obj->fk_workstation :0; 
            $velocity = $TWorkstation[$fk_worstation]['velocity'];
           
            $height = $obj->planned_workload / $velocity / 3600 * (1- ($obj->progress / 100));
            $y = ($obj->grid_row / $velocity )+ $height;
            
            return array($y, 0);
        }
           
            
    }
    
    
    return array(0,0);
}
/*
 * get next free place for task
 */
function _ordo_gnc_get_free(&$TWorkstation, &$TFree, &$TPlanned,$available_ressource, $needed_ressource, $height, &$task) {
    print $task['id'].'<hr>';
    $left = $top = false;
    
    $fKey = false;
    
    $fk_task_parent = (int)$task['fk_task_parent'];
    list($yParent) = _ordo_get_parent_coord($TWorkstation, $TPlanned, $fk_task_parent);
    
    $id_task_to_debug=12;
    if($task['id'] == $id_task_to_debug) {
        var_dump($height, $needed_ressource, $TFree);
           
        
    }
    foreach($TFree as $k=>$free) {
        
      list($y,$x,$h,$w) = $free;
      
      if($w>=$needed_ressource && ($h===false || $h>=$height ) && ($top===false || $y<$top || ($y == $top && $x<$left) ) && $y>=$yParent ) {
                // recherche du casier vide le plus prometteur
            $fKey = $k;
            $left = $x; 
            $top  = $y;
      }
    }
    
    if($yParent>0 && $fKey===false) {
        $fKey = 0;
        $left=0;
        $top=$yParent;
        
    }
    if($task['id'] == $id_task_to_debug) {
        var_dump($fKey);
          
        
    }
    if(isset($_REQUEST['DEBUG2'])) {
         var_dump(array($task['id'], $fk_task_parent, $yParent, $top, $height, $fKey));
    }
	
    if($fKey!==false) {
       $TPlanned[]=array($top,$left,$height,$needed_ressource, $task['id'], $fk_task_parent);  
        
       if(isset($_REQUEST['DEBUG'])) {
         print "{$task[id]} :: $fKey,$available_ressource || $needed_ressource, $height >> $top, $left<br />";
       }
       $TFree= _ordo_gnc_get_free_place($TPlanned, $available_ressource); 
       if(isset($_REQUEST['DEBUG'])) {
            var_dump('TFree',$TFree);   
       }
    }
    else{
       var_dump('TFree',$TFree);   
       exit('aucune solution ?! pas possible !');
    }
    
    if($task['id'] == $id_task_to_debug) {
        var_dump($TFree);
         exit;    
        
    }
    
    return array($left,$top);
}
function _ordo_gnc_gfp_checksum($free_place) {
    return md5($free_place[0].'/'.$free_place[1].'/'.$free_place[2].'/'.$free_place[3]);
}
function _ordo_gnc_gfp_add_new(&$TFree, $fn) {
        
    foreach($TFree as $k=>&$emptyPlace) {
        
        if($emptyPlace[0] == $fn[0] && $emptyPlace[1]==$fn[1] && $emptyPlace[2]==$fn[2]) {
            if( $emptyPlace[3]< $fn[3] ) {
                unset($TFree[$k]);
            }
            else {
                $fn = null;
            }
            
        }
        
    }    
    
    if(!empty($fn)) $TFree[_ordo_gnc_gfp_checksum($fn)] = $fn;
} 
function _ordo_gnc_get_free_place_sort(&$a, &$b) {
      if($a[0]<$b[0]) return -1;
      else if($a[0]>$b[0]) return 1;
      else {
          
          if($a[1]<$b[1]) return -1;
          elseif($a[1]>$b[1]) return 1; 
          else return 0;
      }
    
}
function _ordo_gnc_get_free_place(&$TPlanned, $available_ressource,$sort=false) {
   $TFree = array(); 
   //$free_after_all = array(0, 0, false, $available_ressource,'$free_after_all');
   
   if($sort) usort($TPlanned,'_ordo_gnc_get_free_place_sort');
   
   /*
    * J'ai mes boîtes.
    */
   foreach($TPlanned as $planned) {
         /* De quel espace je dispose entre chacune de mes boîtes ?
        ---------
        |       |
        | *   * |
        |   *   |
        ---------  */
            $TFreeNew=array();
        
            list($y,$x,$h,$w) = $planned;
           
            $TFreeNew[] = array(0, $x, $y, $w); // before
            $TFreeNew[] = array($y+$h, $x, false, $w); // after
            
            $TFreeNew[] = array(0, $x, $y, $available_ressource - $x);
            $TFreeNew[] = array($y+$h, $x, false, $available_ressource - $x);
            
            $TFreeNew[] = array(0, $x+$w, false, $available_ressource - ($x+$w));
            $TFreeNew[] = array(0, 0, false, $x);
            
            foreach($TPlanned as &$other_planned) {
                foreach($TFreeNew as &$fn) {
                    _ordo_gnc_get_free_place_correction($fn, $other_planned);     
                }
            }
          
            _ordo_gnc_purge($TFreeNew, $available_ressource);
            foreach($TFreeNew as $fn) {
                 _ordo_gnc_gfp_add_new($TFree, $fn);
            }

   }    
            
   //$TFree[_ordo_gnc_gfp_checksum($free_after_all)] = $free_after_all;
    
  // _ordo_gnc_purge($TFree, $available_ressource);
    
    return $TFree;
    
}
/*
 * Reconstruct Free Place after adding some task
 */
function _ordo_gnc_get_free_place_old(&$TPlanned, $available_ressource) {
        /*
         * Reconstruit $TFree sur la base du plannifié
         * $TFree[] = array($y, $x, $h, $w);
         */
        $TFree = array(); 
         
        $free_after_all = array(0, 0, false, $available_ressource,'$free_after_all');
          
        foreach($TPlanned as $planned) {
            list($y,$x,$h,$w) = $planned;
           
            $free_before = array(0, $x, $y, $w);
            $free_after = array($y+$h, $x, false, $w);
            
            $free_before_ext = array(0, $x, $y, $available_ressource - $x);
            $free_after_ext = array($y+$h, $x, false, $available_ressource - $x);
            
            $free_right = array(0, $x+$w, false, $available_ressource - ($x+$w));
            $free_left = array(0, 0, false, $x);
            
            foreach($TPlanned as $other_planned) {
                
               _ordo_gnc_get_free_place_correction($free_before, $other_planned);
               _ordo_gnc_get_free_place_correction($free_after, $other_planned);
               _ordo_gnc_get_free_place_correction($free_before_ext, $other_planned);
               _ordo_gnc_get_free_place_correction($free_after_ext, $other_planned);
               _ordo_gnc_get_free_place_correction($free_right, $other_planned);
               _ordo_gnc_get_free_place_correction($free_left, $other_planned);    
                
            }
             
            if($free_after_all[0]<$y+$h)$free_after_all[0] = $y+$h; 
            
            $TFree[_ordo_gnc_gfp_checksum($free_before)] = $free_before;
            $TFree[_ordo_gnc_gfp_checksum($free_after)] = $free_after;

            $TFree[_ordo_gnc_gfp_checksum($free_before_ext)] = $free_before_ext;
            $TFree[_ordo_gnc_gfp_checksum($free_after_ext)] = $free_after_ext;
            
            $TFree[_ordo_gnc_gfp_checksum($free_right)] = $free_right;
            $TFree[_ordo_gnc_gfp_checksum($free_left)] = $free_left; 
        } 
        
        $TFree[_ordo_gnc_gfp_checksum($free_after_all)] = $free_after_all;
        
       _ordo_gnc_purge($TFree, $available_ressource);
        
        return $TFree;
        
}

/*
 * resize free place after adding
 */
function _ordo_gnc_get_free_place_correction(&$free, &$other_planned,$debug=false) {
    list($other_y,$other_x,$other_h,$other_w) = $other_planned;
    
   // if($free===array(5,0,false,2)) { print 'case' ;$debug = true; }
    
    if($other_x < $free[1] + $free[3] && $other_x + $other_w > $free[1] && ($free[2]===false || $free[2]>0) ) { 
    
        if($debug) {
            var_dump(array('dedans', $free, $other_planned)); 
        }
        
        if($other_y + $other_h > $free[0] && $other_y<=$free[0]) {
            if($free[2]!==false) $free[2] =  $free[2] - (($other_y + $other_h)- $free[0]);
            $free[0] = $other_y + $other_h;

            if($debug) {
                print "... réduit le top <br />";
            }

        }
         
        if($other_y > $free[0] && ($free[0] + $free[2] > $other_y || $free[2] ===false ) ) { // bloc après
            $free[2] = $other_y - $free[0] ; // hauteur limitée à la hauteur du bloc d'après
            
           if($debug) { print "... réduit la hauteur <br />"; }
        }
        
        if($debug) {
            var_dump(array('après ',$free)); 
         }
    }
    
    
}

/*
 * Delete wrong free place (void, empty, ...)
 */
function _ordo_gnc_purge(&$TFree, $available_ressource) {
    
    foreach($TFree as $k=>$free) {
        list($y,$x,$h,$w) = $free;
        
        if( ($h!==false && $h<=0) || $w<=0 || $x>=$available_ressource) unset($TFree[$k]);
        
    }

}   
    
function scrumboard_random_color_part($min = 50) {
    return str_pad( dechex( mt_rand( $min, 255 ) ), 2, '0', STR_PAD_LEFT);
}

function scrumboard_random_color() {
    return scrumboard_random_color_part(200) . scrumboard_random_color_part() . scrumboard_random_color_part();
}
    
