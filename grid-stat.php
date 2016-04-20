<?php

    require('config.php');
    
    /*
     * Statistique sur les postes de travail de l'ordonnancement
     */
     
    dol_include_once('/abricot/inc.core.php');
     
  	if(ABRICOT_VERSION<1.5) accessforbidden( $langs->trans( 'abricotNeed15version' ) ); 
    
   // if(!$conf->report->enabled) accessforbidden( $langs->trans( 'moduleReportNeeded' ) );
    if(!$conf->workstation->enabled) accessforbidden( $langs->trans( 'moduleWorkstationNeeded' ) );
    
    define('INC_FROM_DOLIBARR',true);
    dol_include_once('/workstation/config.php');
	//dol_include_once('/report/class/dashboard.class.php');
   
    $PDOdb = new TPDOdb;

    $TWS = TWorkstation::getWorstations($PDOdb, false);

    
    llxHeader('',$langs->trans('OrdonnancementStat'));
    print_fiche_titre('Filtres');
    echo '<div class="tabBar">';
    $form1 = new TFormcore('auto','form1', 'post');
    
    echo '<table>';
    
    ?>
        <tr>
            <td>Date de début : </td>
            <td><?php echo $form1->calendrier('', 'date_deb', ($_REQUEST['date_deb'])? $_REQUEST['date_deb'] : ''); ?></td>
        </tr>
        <tr>
            <td>Date de fin : </td>
            <td><?php echo $form1->calendrier('', 'date_fin', ($_REQUEST['date_fin'])? $_REQUEST['date_fin'] : ''); ?></td>
        </tr>
    <?php

    echo '<tr><td colspan="2" align="center">'.$form1->btsubmit('Valider', 'valider').'</td></tr>';
    echo '</table>';
    $form1->end();
    echo '</div>';
    
    $tDeb = isset($_REQUEST['date_deb']) ? Tools::get_time($_REQUEST['date_deb']) : time();
    $tFin = isset($_REQUEST['date_fin']) ? Tools::get_time($_REQUEST['date_fin']) : time() + (86400 * 7);
    
    foreach($TWS as $id_ws=>$ws_name) {
        
       _stat_wd($PDOdb, $id_ws, $tDeb, $tFin);
        
    }
    
    
    llxFooter();

function _get_time(&$TRes, &$TAxis,& $TSerie) {
    
    foreach($TRes as $row) {
        
        $tStart = strtotime($row->date_estimated_start);
        $tEnd = strtotime($row->date_estimated_end);
       
        if($tStart< $tDeb) $tStart = $tDeb;
        if($tFin>$tEnd)$tFin = $tEnd;
       
        $tCurrent = strtotime(date('Y-m-d 00:00:00', $tStart));
        while($tCurrent < $tEnd) {
            
            $tCurrentSoir = strtotime(date('Y-m-d 23:59:59', $tCurrent));
            
            $c_day = date('Ymd',$tCurrent);
            
            
            if($tStart>$tCurrent && $tEnd<$tCurrentSoir) {
                $t = $tEnd - $tStart;
                $TAxis[$c_day] += $t; 
            }
            else if($tStart>$tCurrent) {
                $t = $tCurrentSoir - $tStart;
                $TAxis[$c_day] += $t; 
            }
            else if($tStart<$tCurrent && $tCurrentSoir<$tEnd) {
                $TAxis[$c_day] += 86400;
            }
            else if($tEnd<$tCurrentSoir) {
                $t = $tEnd - $tCurrent;
                $TAxis[$c_day] += $t;
            }
            
            $tCurrent = strtotime('+1day', $tCurrent);
        }
        
        
        
    }
}

function _init_data(&$TAxis, &$TSerie, $tDeb, $tFin) {
    
    $tCurrent = $tDeb;
    while($tCurrent<=$tFin) {
         $c_day = date('Ymd',$tCurrent);
        
        $TAxis[$c_day] = 0;
        $TSerie[$c_day] = date('d',$tCurrent) == '1' ? date('d/m',$tCurrent) : date('d',$tCurrent);
  
        
        $tCurrent = strtotime('+1day', $tCurrent);
    }
    
}

function _get_data_ws(&$PDOdb, $id_ws, $tDeb, $tFin,$nb_ressource) {
 global $conf;
 
    $Tab=array(
        'series'=>array()
        ,'axis'=>array()
    );
    
    if($nb_ressource<1)$nb_ressource = 1;
    
    $nb_hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 7;
    $nb_second_in_hour = 3600 * (24 / $nb_hour_per_day);
   
    $TAxis = $TSerie = array();
    _init_data($TAxis, $TSerie, $tDeb, $tFin);
   
   
    $sql = "SELECT t.date_estimated_start,t.date_estimated_end 
                FROM ".MAIN_DB_PREFIX."projet_task t 
                    LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (t.rowid=tex.fk_object)
                    
                WHERE t.entity=".$conf->entity." AND tex.fk_workstation=".$id_ws." AND t.date_estimated_end > NOW() AND progress<100
                AND t.date_estimated_start<'".date('Y-m-d 23:59:59', $tFin)."' 
                AND t.date_estimated_end>'".date('Y-m-d 00:00:00', $tDeb)."'
                ORDER BY  t.date_estimated_start  ";
    $TRes = $PDOdb->ExecuteAsArray($sql);
    _get_time($TRes, $TAxis, $TSerie, $tDeb, $tFin);
    
    
    $sql = "SELECT t.dateo as 'date_estimated_start',t.datee as 'date_estimated_end'
                FROM ".MAIN_DB_PREFIX."projet_task t 
                    LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (t.rowid=tex.fk_object)
                    
                WHERE t.entity=".$conf->entity." AND tex.fk_workstation=".$id_ws." AND t.datee < NOW()
                AND t.dateo<'".date('Y-m-d 23:59:59', $tFin)."' 
                AND t.datee>'".date('Y-m-d 00:00:00', $tDeb)."'
                ORDER BY  t.dateo  ";
    $TRes = $PDOdb->ExecuteAsArray($sql);
    
    _get_time($TRes, $TAxis, $TSerie, $tDeb, $tFin);
    
     
    ksort($TAxis);
    ksort($TSerie);
    
    
    foreach($TAxis as &$val) { $val = round( $val / $nb_second_in_hour / $nb_ressource / $nb_hour_per_day * 100 ); }
    
    $Tab = array();
	foreach($TSerie as $k=>$v) {
		
			$Tab[] = array(
				'Jour'=>$v
				,'Usage'=>$TAxis[$k]
			);
		
	}
	
    return $Tab;
    
}

function _stat_wd(&$PDOdb, $id_ws, $tDeb, $tFin) {
    
    $ws = new TWorkstation;
    $ws->load($PDOdb, $id_ws);
    
    $TData = _get_data_ws($PDOdb, $id_ws, $tDeb, $tFin, $ws->nb_ressource);
   // var_dump($TData);
    ?>
    <table class="border" style="margin-top:20px;width:100%;">
        <tr>
            <td><?php
            echo $ws->getNomUrl(1).' du '.date('d/m/Y', $tDeb).' au '.date('d/m/Y', $tFin);
            ?></td>
        </tr>
        <tr>
            <td>
                <?php
            
                    $l=new TListviewTBS('stat_ws_'.$id_ws);
                	echo $l->renderArray($PDOdb, $TData,array(
						'type'=>'chart'
						,'chartType'=>'AreaChart'
					));
                
                    
                ?></td>
        </tr>
        
    </table>
   
    <?php
    
}
