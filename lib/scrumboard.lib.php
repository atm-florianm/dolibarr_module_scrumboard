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
	
	$t2week= strtotime('-2weeks');
	
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


function getAllTaskInStory($fk_project, $story) {
    global $db;
    
    // Sélectionne toutes les taches existantes liées à une story
    $sql = 'SELECT t.rowid';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'projet_task AS t';
    $sql .= ' WHERE t.story_k = '.intval($story).' AND t.fk_projet = '.intval($fk_project);
    
    $resql = $db->query($sql);
    
    $TData = array();
    if($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $TData[] = $obj->rowid;
        }
    }
    
    
    return $TData;
}
