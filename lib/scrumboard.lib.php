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

function ordonnanceur($TTaskToOrder, $TWorkstation ,$fk_workstation=0) {
    $Tab = $TTaskOrdered = array();
  //  var_dump($fk_workstation,$TWorkstation);
    $TCol = $TRow = $TPlan = array();
    
	foreach($TTaskToOrder as $task) {
         /*   
       if(!isset($TCol[$task['fk_workstation']]))$TCol[$task['fk_workstation']]=0;
       if(!isset($TRow[$task['fk_workstation']]))$TRow[$task['fk_workstation']]=0;
	   */
	   if(!isset($TPlan[$task['fk_workstation']])) {
	   		$TPlan[$task['fk_workstation']]=array(
				'@param'=>array(
					'available_ressource'=>(int)$TWorkstation[(int)$task['fk_workstation']]['nb_ressource']
				)
				,'@plan'=>array(
				
				)
				,'@free'=>array(
                
                )
			);
	   }
      /*  
       $col = &$TCol[$task['fk_workstation']];
       $row = &$TRow[$task['fk_workstation']];
       */
       if(empty($fk_workstation) || $fk_workstation == $task['fk_workstation']) {
	   		   list($col, $row) = _ordonnanceur_get_next_coord2($TPlan[$task['fk_workstation']], $task);  
               
	  		   $task['grid_col'] = $col;
       		   $task['grid_row'] = $row;
	  
	   	       $TTaskOrdered[] = $task;
       }
    }
     
  //  print '<pre>';print_r($TPlan);
    
    return $TTaskOrdered;
}

function _ordonnanceur_get_next_coord2(&$TPlan,&$task) {
    $available_ressource = $TPlan['@param']['available_ressource'];
    
    if($available_ressource<1) return array(0,0); // cas impossible :-|
    
    $TFree = &$TPlan['@free'];

    $needed_ressource = $task['needed_ressource'];
    $height = $task['planned_workload'];
    $col = 0;
    
    if(empty($TFree)){
        $TFree[] = array(0, 0, -1, $available_ressource); // y,x,h,w de largeur tous à partir de 0 à gauche et 0 en haut et d'une hauteur infinie
    }
    
    list($col, $top) = _orgo_gnc_get_free($TFree,$available_ressource, $needed_ressource, $height,$task);
    
    
    return array($col, $top);
}

function _orgo_gnc_get_free(&$TFree,$available_ressource, $needed_ressource, $height,$task) {
    
    $left = $top = false;
    
    $fKey = false;
    foreach($TFree as $k=>$free) {
        
      // print "$k {$free[3]}>=$needed_ressource && ({$free[2]}==-1 || {$free[2]}>=$height ) && ($top==0 || {$free[0]}<$top<br>";
        
        if($free[3]>=$needed_ressource && ($free[2]==-1 || $free[2]>=$height ) && ($top===false || $free[0]<$top) ) {
                // recherche du casier vide le plus prometteur
            $fKey = $k;
            $left = $free[1]; 
            $top  = $free[0];
            
        }
    }
    
  //  var_dump($fKey, $left, $top,$available_ressource,$needed_ressource, $height,$TFree,'----');
    
    if($fKey!==false) {
        
     //   var_dump($TFree,$task,'*********');
       $TFree= _orgo_gnc_cut_free($TFree, $fKey,$available_ressource, $needed_ressource, $height); 
        
         
       // var_dump($TFree,$task,'------------------');
        
    }
    else{
       $TFree= _orgo_gnc_add_free($TFree, $available_ressource,$needed_ressource, $height);
    }
    
    return array($left,$top);
}

function _orgo_gnc_add_free($TFree, $available_ressource,$needed_ressource, $height) {
    /* A priori complètement useless puisque c'est _orgo_gnc_cut_free qui doit perpettuer le truc mais pris dans mon élan j'ai codé, je verrai plus tard si suppression */
    $topMax = 0;
    
    foreach($TFree as $free) {
        
        if($topMax < $free[0] + $free[2]) $topMax = $free[0] + $free[2];
        
    }
    
    $TFree[] = array($topMax, 0, -1, $available_ressource);
    
    return $TFree;
}

function _orgo_gnc_cut_free($TFree, $k, $available_ressource, $needed_ressource, $height) {
   // print "$available_ressource, $needed_ressource, $height";
    $free1 = $free2 = $TFree[$k];
   // var_dump($TFree,$k, $available_ressource, $needed_ressource, $height);
    unset($TFree[$k]);
    
    $free1[0] += $height; // la case après le bloc ajouté
    if($free1[2]!=-1) {
        $free1[2] -= $height;
        
    }
    
    if($free1[2]==-1 || $free1[2] > 0 ) {
        $TFree = _orgo_gnc_cut_bad_top($TFree, $free1);
     
        $TFree[] = $free1;
        
    }
    
    
    if($free2[3]>$needed_ressource) { // la case à côté du bloc ajouté 
        
        $free2[1]+=$needed_ressource;
        $free2[3]-=$needed_ressource;
        
        $TFree = _orgo_gnc_cut_bad_top($TFree, $free2);
        $TFree[] = $free2;
    }
    
    usort($TFree, '_ordo_sort_on_top');
    
    return $TFree;
}

function _orgo_gnc_cut_bad_top($TFree, $newFree) {
    
    foreach($TFree as $k=>$free) {
        list($y,$x,$h,$w) = $free;
        
        if($y<$newFree[0]) {
            // possible recut
            
            if($x+$w>$newFree[1]) {
                $TFree[] = array($y,$x, $h,$newFree[1] - $x );
                
                $ny = $newFree[0];
                
                if($h==-1)$nh = -1;
                else $nh = $h-($ny - $y);
                  
                $TFree[$k]= array($ny,$x, $nh,$w );
            }
            
        }
        
        
    }
    
    return $TFree;
}

function _ordo_sort_on_top(&$a, $b) {
    
    if($a[0]<$b[0]) return -1;
    elseif($a[0]>$b[0]) return 1;
    else return 0;
}

function _ordonnanceur_get_next_coord(&$TPlan,&$task) {

	$available_ressource = $TPlan['@param']['available_ressource'];
	$TPlanned = &$TPlan['@plan'];

	$needed_ressource = $task['needed_ressource'];
	$height = $task['planned_workload'];
	$col = 0;
	
	$top_dispo = 9999999999999999;
	
	$new_row=array();
	
	foreach($TPlanned as $row) {
		for($i=0;$i<$available_ressource;$i++) {
			
			if($i+$needed_ressource<=$available_ressource) {

				for($j=0;$j<$needed_ressource;$j++) {
	//		print "$col, $top_dispo<br />";
					if($top_dispo>$row[$i+$j] && $row[$i+$j]>0) {
						$top_dispo=$row[$i+$j];
						if($top_dispo>0)$top_dispo+=0.01;
						$col = $i;
					}		
					
				}
				
			}
			
			
		}	
		
	}

	if($top_dispo == 9999999999999999) $top_dispo = 0;

	for($m=0;$m<$available_ressource;$m++) {
		$new_row[$m]=0;
	}
	
	for($k=$col;$k<$col+$needed_ressource;$k++) {
		$new_row[$k] = $top_dispo+$height; 	
	}
//var_dump($new_row, $col,$top_dispo,'<br>');
	$TPlanned[] = $new_row; 
		/*
    $next_col = $col;    
    $next_row = $row;
    $next_row+=$height;
    if($row == $next_row) $next_row++;  
*/
    return array($col, $top_dispo);
}