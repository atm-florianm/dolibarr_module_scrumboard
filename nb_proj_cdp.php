<?php
	require('config.php');
	dol_include_once('/projet/class/project.class.php');
	dol_include_once('/projet/class/task.class.php');
	dol_include_once('/user/class/user.class.php');
	dol_include_once('/core/lib/usergroups.lib.php');
	dol_include_once('/comm/propal/class/propal.class.php');
	
	
	
	llxHeader('',$langs->trans('graphProjectCDP'));
	
	print dol_get_fiche_head($langs->trans('graphProjectCDP'));
	print_fiche_titre($langs->trans("graphProjectCDP"));	
	_print_graph();
	
	function _print_graph(){
		global $db, $langs;
		
		

		
		$PDOdb = new TPDOdb;

		$TDataBrut=
		$TData = _get_projet_cdp();
		//var_dump($TDataBrut);
		
		
		$explorer = new stdClass();
		$explorer->actions = array("dragToZoom", "rightClickToReset");
		
		
		$listeview = new TListviewTBS('graphProjectCDP');
		
		print $listeview->renderArray($PDOdb, $TData
			,array(
				'type' => 'chart'
				,'chartType' => 'ColumnChart'
				,'liste'=>array(
					'titre'=> $langs->transnoentities('graphProjectCDP')
				)
				,'hAxis'=>array('title'=> 'Chef de projet')
				,'vAxis'=>array('title'=> 'Nombre de projets')
				,'explorer'=>$explorer
			)
		);
	}
	
	

	function _get_projet_cdp(){
		global $db;
		
		$TData = array();
		
		$sql = 'SELECT COUNT(p.rowid) AS nombre, ec.fk_socpeople AS user FROM '.MAIN_DB_PREFIX.'projet p ';
		$sql .= 'INNER JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.element_id=p.rowid ';
		$sql .= 'WHERE ec.fk_c_type_contact = 160 AND p.fk_statut=1 ';
		$sql .= 'GROUP BY ec.fk_socpeople ';
		
		$resql = $db->query($sql);
		
		if ($resql){
			while ($line = $db->fetch_object($resql)){
				
				$user = new User($db);
				$user->fetch($line->user);			
				$TData[] = array(
					"nom"   => $user->firstname.' '.$user->lastname,
					"nombre" => $line->nombre
				);
			}
		}
		return $TData;
		
	}