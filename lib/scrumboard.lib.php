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

/**
 * @param DoliDB $db
 * @param int    $id_project
 * @param string $status
 * @param int    $fk_user
 * @param int    $fk_soc
 * @param string $soc_type
 * @param array  $TDateFilters
 * @param array  $search_array_options
 * @param Task   $task
 * @param array  $extrafieldstask
 * @param string $label_filter
 * @param int    $country_filter
 * @param int    $state_filter
 * @return array|string
 */
function getSQLForTasks(
	&$db,
	$id_project,
	$status,
	$fk_user,
	$fk_soc,
	$soc_type,
	$TDateFilters,
	$search_array_options,
	$task,
	$extrafieldstask,
	$label_filter,
	$country_filter,
	$state_filter
) {
	global $conf, $hookmanager;

	dol_include_once('scrumboard/class/scrumboard.class.php');

	$sql = 'SELECT DISTINCT pt.rowid, pt.story_k, pt.scrum_status, pt.rang
			FROM '.MAIN_DB_PREFIX.'projet_task pt
			INNER JOIN '.MAIN_DB_PREFIX.'projet p ON (p.rowid = pt.fk_projet)';

	if (!empty($search_array_options)) $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'projet_task_extrafields ef ON (ef.fk_object = pt.rowid)';

	if(empty($id_project) && $status != 'unknownColumn')
	{
		$sql.= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_storie ps ON (ps.fk_projet = pt.fk_projet AND ps.storie_order = pt.story_k)';
	}

	if (!empty($conf->global->SCRUM_FILTER_BY_USER_ENABLE) && $fk_user > 0)
	{
		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'element_contact ec ON (ec.element_id = pt.rowid)';
		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'c_type_contact tc ON (tc.rowid = ec.fk_c_type_contact)';
	}
	if ((!empty($country_filter) || !empty($state_filter)) && !empty($search_array_options))
	{
		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'societe soc ON (ef.fk_etablissement = soc.rowid)';
	}

	if($status == 'unknownColumn') {
		$scrumboardColumn = new ScrumboardColumn;
		$PDOdb=new TPDOdb;
		$scrumboardColumn->LoadAllBy($PDOdb);
		$defaultColumn = $scrumboardColumn->getDefaultColumn();

		$sql .= ' WHERE (scrum_status NOT IN (SELECT code FROM '.MAIN_DB_PREFIX.'c_scrum_columns WHERE active=1))';
	}
	else {
		$sql.= ' WHERE 1 ';
		$sql.= ' AND ((scrum_status IS NOT NULL AND scrum_status = "'.$status.'")';

		if      ($status=='ideas')      $sql.= ' OR (scrum_status IS NULL AND (progress = 0 OR progress IS NULL) AND datee IS NULL)';
		else if ($status=='todo')       $sql.= ' OR (scrum_status IS NULL AND (progress = 0 OR progress IS NULL))';
		else if ($status=='inprogress') $sql.= ' OR (scrum_status IS NULL AND  progress > 0 AND progress < 100)';
		else if ($status=='finish')     $sql.= ' OR (scrum_status IS NULL AND  progress=100)';
		$sql .= ')';
	}

	if($id_project > 0) $sql.= ' AND fk_projet='.$id_project;

	if (!empty($conf->global->SCRUM_FILTER_BY_USER_ENABLE) && $fk_user > 0)
	{
		$sql.= ' AND tc.element = \'project_task\' AND ec.fk_socpeople = '.$fk_user;
	}

	$parameters = array('id_project' => $id_project, 'fk_soc' => $fk_soc, 'soc_type' => $soc_type);
	$reshook = $hookmanager->executeHooks('scrumManageFk_socSQL', $parameters, $task, $action);
	if ($reshook > 0) $sql.=$hookmanager->resPrint;
	if (empty($reshook) && $fk_soc > 0)
	{
		if ($soc_type === 'onlycompany' || $soc_type === 'both')
		{
			$sql.= ' AND ';
			if ($soc_type === 'both') $sql.= ' ( ';
			$sql.= 'p.fk_soc = '.$fk_soc;
		}

		if ($soc_type === 'onlychildren' || $soc_type === 'both')
		{
			$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE parent = '.$fk_soc);
			if ($resql)
			{
				$TSocId = array();
				while ($obj = $db->fetch_object($resql))
				{
					$TSocId[] = $obj->rowid;
				}

				if (!empty($TSocId))
				{
					if ($soc_type === 'both') $sql.= ' OR ';
					else $sql.= ' AND ';
					$sql.= 'p.fk_soc IN ('.implode(',', $TSocId).')';
				}
				else
				{
					$sql.= 'p.fk_soc = -1';
				}
			}
			else
			{
				dol_print_error($db);
			}

			if ($soc_type === 'both') $sql.= ' ) ';
		}
	}
	// date filter
	LIST ($start_date_after, $start_date_before, $end_date_after, $end_date_before) = $TDateFilters;

	// add error if date range boundaries are not in the right order (negative range)
	$startDateNegativeDateRange = !empty($start_date_before) && $start_date_after > $start_date_before;
	$endDateNegativeDateRange   = !empty($end_date_before)   && $end_date_after   > $end_date_before;
	if ($startDateNegativeDateRange || $endDateNegativeDateRange)
	{
		global $langs;
		return array(
			'error' => true,
			'message' => $langs->trans('FilterErrorNegativeDateRange')
		);
	}
	if (!empty($start_date_after))  $sql .= ' AND pt.dateo >= ' . "'" . $db->idate($start_date_after)  . "'";
	if (!empty($start_date_before)) $sql .= ' AND pt.dateo <= ' . "'" . $db->idate($start_date_before) . "'";
	if (!empty($end_date_after))    $sql .= ' AND pt.datee >= ' . "'" . $db->idate($end_date_after)    . "'";
	if (!empty($end_date_before))   $sql .= ' AND pt.datee <= ' . "'" . $db->idate($end_date_before)   . "'";

	// extrafields filters
	if (!empty($search_array_options))
	{
		$extrafields = &$extrafieldstask; // Compatibility for tpl
		$action = 'setSqlExtrafields';
		$parameters = array('sql' => &$sql, 'id_project' => $id_project, 'status' => $status, 'fk_user' => $fk_user, 'fk_soc' => $fk_soc, 'soc_type' => $soc_type, 'TDateFilters' => $TDateFilters, 'search_array_options' => $search_array_options, 'extrafieldstask' => $extrafieldstask, 'label_filter' => $label_filter, 'country_filter' => $country_filter, 'state_filter' => $state_filter);
		$reshook = $hookmanager->executeHooks('doTasks', $parameters, $task, $action); // Note that $action and $object may have been modified by some
		if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

		if (empty($reshook))
		{
			// Add where from extra fields
			include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
		}
	}
	// filter on label
	if (!empty($label_filter))
	{
		$sql .= ' AND pt.label LIKE \'%' . $db->escape($label_filter) . '%\'';
	}
	// filter on state / country
	if (!empty($country_filter))
	{
		$sql .= ' AND soc.fk_pays = ' . $country_filter;
	}
	if (!empty($state_filter))
	{
		$sql .= ' AND soc.fk_departement = ' . $state_filter;
	}

	$sql.= ' ORDER BY pt.rang';

	return $sql;
}
