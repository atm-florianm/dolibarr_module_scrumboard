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
/*
 * Refresh task position
 */
function ordonnanceur($TTaskToOrder, $TWorkstation ,$fk_workstation=0) {
global $conf;    
    
    $Tab = $TTaskOrdered = array();
  //  var_dump($fk_workstation,$TWorkstation);
    $TCol = $TRow = $TPlan = array();
    
    $time_init = strtotime(date('Y-m-d'));
    
    $nb_hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 7;
    $nb_second_in_hour = 3600 * (24 / $nb_hour_per_day);
    
	foreach($TTaskToOrder as $task) {
         
       $fk_workstation = (int)$task['fk_workstation'];
       if(!isset($TWorkstation[$fk_workstation]))$fk_workstation = 0;
       if(!isset($TPlan[$fk_workstation])) {
	   		$TPlan[$fk_workstation]=array(
				'@param'=>array(
					'available_ressource'=>(int)$TWorkstation[$fk_workstation]['nb_ressource']
					,'velocity'=>(float)$TWorkstation[$fk_workstation]['velocity']
				)
				,'@plan'=>array(
				
				)
				,'@free'=>array(
                
                )
			);
	   }
      
       if(empty($fk_workstation) || $fk_workstation == $fk_workstation) {
               $velocity = $TPlan[$fk_workstation]['@param']['velocity'];
               $height = $task['planned_workload'] / $velocity * (1- ($task['progress'] / 100));
	   		   list($col, $row) = _ordonnanceur_get_next_coord($TWorkstation, $TPlan[$fk_workstation], $task, $height);  
               
	  		   $task['grid_col'] = $col;
       		   $task['grid_row'] = $row;
	  
               $task['time_estimated_start'] = $time_init + ($row * $nb_second_in_hour);
               $task['time_estimated_end'] =  $task['time_estimated_start'] + ($height  *$nb_second_in_hour) ;
               
               $task['time_projection'] ='Début prévu : '.dol_print_date($task['time_estimated_start'],'daytext').', '.getHourInDay($task['time_estimated_start'])
                    .'<br />Fin prévue : '.dol_print_date($task['time_estimated_end'],'daytext').', '.getHourInDay($task['time_estimated_end']);
      
      
	   	       $TTaskOrdered[] = $task;
       }
    }
     
    return $TTaskOrdered;
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
    
    $col = 0;
    
    if(empty($TFree)){
        $TFree[] = array(0, 0, false, $available_ressource); // y,x,h,w de largeur tous à partir de 0 à gauche et 0 en haut et d'une hauteur infinie
    }
    
    list($col, $top) = _orgo_gnc_get_free($TWorkstation, $TFree, $TPlanned,$available_ressource, $needed_ressource, $height, $task);
    
    $sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET
            grid_row=".$top.", grid_col=".$col."
            WHERE rowid = ".$task['id'];
          
    $db->query($sql);
   
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
            WHERE t.rowid = ".$fk_task_parent;
        $res = $db->query($sql);    
        $obj = $db->fetch_object($res);
        if($obj) {
           
            $fk_worstation = isset($TWorkstation[$obj->fk_workstation]) ? $obj->fk_workstation :0; 
            $velocity = $TWorkstation[$fk_worstation]['velocity'];
           
            $height = $obj->planned_workload / $velocity / 3600 * (1- ($obj->progress / 100));
            $y = $obj->grid_row + $height;
            
            return array($y, 0);
        }
           
            
    }
    
    
    return array(0,0);
}
/*
 * get next free place for task
 */
function _orgo_gnc_get_free(&$TWorkstation, &$TFree, &$TPlanned,$available_ressource, $needed_ressource, $height, &$task) {
    
    $left = $top = false;
    
    $fKey = false;
    
    $fk_task_parent = (int)$task['fk_task_parent'];
    list($yParent) = _ordo_get_parent_coord($TWorkstation, $TPlanned, $fk_task_parent);
    
    foreach($TFree as $k=>$free) {
        
      list($y,$x,$h,$w) = $free;
      
      if($w>=$needed_ressource && ($h===false || $h>=$height ) && ($top===false || $y<$top) && $y>=$yParent ) {
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
    if(isset($_REQUEST['DEBUG2'])) {
         var_dump(array($task['id'], $fk_task_parent, $yParent, $top, $height));
    }
    if($fKey!==false) {
       $TPlanned[]=array($top,$left,$height,$needed_ressource, $task['id'], $fk_task_parent);  
        
       if(isset($_REQUEST['DEBUG'])) {
         print "{$task[id]} :: $fKey,$available_ressource || $needed_ressource, $height >> $top, $left<br />";
       }
       $TFree= _orgo_gnc_get_free_place($TPlanned, $available_ressource); 
       if(isset($_REQUEST['DEBUG'])) {
            var_dump('TFree',$TFree);   
       }
    }
    else{
       var_dump('TFree',$TFree);   
       exit('aucune solution ?! pas possible !');
    }
    
    return array($left,$top);
}
/*
 * Reconstruct Free Place after adding some task
 */
function _orgo_gnc_get_free_place(&$TPlanned, $available_ressource) {
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
                
               _orgo_gnc_get_free_place_correction($free_before, $other_planned);
               _orgo_gnc_get_free_place_correction($free_after, $other_planned);
               _orgo_gnc_get_free_place_correction($free_before_ext, $other_planned);
               _orgo_gnc_get_free_place_correction($free_after_ext, $other_planned);
               _orgo_gnc_get_free_place_correction($free_right, $other_planned);
               _orgo_gnc_get_free_place_correction($free_left, $other_planned);    
                
            }
             
            if($free_after_all[0]<$y+$h)$free_after_all[0] = $y+$h; 
            
            $TFree[] = $free_before;
            $TFree[] = $free_after;

            if($free_before_ext!=$free_before) $TFree[] = $free_before_ext;
            if($free_after_ext!=$free_after)  $TFree[] = $free_after_ext;
            
            $TFree[] = $free_right;
            $TFree[] = $free_left; 
        } 
        
        $TFree[] = $free_after_all;
        
       _orgo_gnc_purge($TFree, $available_ressource);
        
        return $TFree;
        
}

/*
 * resize free place after adding
 */
function _orgo_gnc_get_free_place_correction(&$free, &$other_planned,$debug=false) {
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
function _orgo_gnc_purge(&$TFree, $available_ressource) {
    
    foreach($TFree as $k=>$free) {
        list($y,$x,$h,$w) = $free;
        
        if( ($h!==false && $h<=0) || $w<=0 || $x>=$available_ressource) unset($TFree[$k]);
        
    }

}   
    
