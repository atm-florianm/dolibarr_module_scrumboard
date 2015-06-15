<?php

    require('config.php');
    
    /*
     * Statistique sur les postes de travail de l'ordonnancement
     */
    
    
    if(!$conf->workstation->enabled) accessforbidden( $lang->trans( 'moduleWorkstationNeeded' ) );
    if(!$conf->report->enabled) accessforbidden( $lang->trans( 'moduleReportNeeded' ) );
    
    
    
    define('INC_FROM_DOLIBARR',true);
    dol_include_once('/workstation/config.php');
    dol_include_once('/report/class/dashboard.class.php');
    
    
    $PDOdb = new TPDOdb;

    $TWS = TWorkstation::getWorstations($PDOdb, false);


    llxHeader('',$langs->trans('OrdonnancementStat'));

    foreach($TWS as $id_ws=>$ws_name) {
        
       _stat_wd($PDOdb, $id_ws);
        
    }
    
    
    llxFooter();

function _get_time(&$TRes, &$TAxis,& $TSerie) {
    
    foreach($TRes as $row) {
        
        $tStart = strtotime($row->date_estimated_start);
        $tEnd = strtotime($row->date_estimated_end);
       
        $tCurrent = strtotime(date('Y-m-d 00:00:00', $tStart));
        while($tCurrent < $tEnd) {
            
            $tCurrentSoir = strtotime(date('Y-m-d 23:59:59', $tCurrent));
            
            $c_day = date('Ymd',$tCurrent);
            
            
            if(!isset($TAxis[$c_day])){
                 $TAxis[$c_day] = 0;
                 $TSerie[$c_day] = date('d/m/Y',$tCurrent);
            } 
          
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

function _get_data_ws(&$PDOdb, $id_ws) {
 global $conf;
 
    $Tab=array(
        'series'=>array()
        ,'axis'=>array()
    );
    
    
    $nb_hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 7;
    $nb_second_in_hour = 3600 * (24 / $nb_hour_per_day);
   
    
    $TRes = $PDOdb->ExecuteAsArray("SELECT t.date_estimated_start,t.date_estimated_end 
                FROM ".MAIN_DB_PREFIX."projet_task t 
                    LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (t.rowid=tex.fk_object)
                    
                WHERE tex.fk_workstation=".$id_ws." AND t.date_estimated_end > NOW() AND progress<100
                ORDER BY  t.date_estimated_start  ");
    
    _get_time($TRes, $TAxis, $TSerie);
    
    $TRes = $PDOdb->ExecuteAsArray("SELECT t.dateo as 'date_estimated_start',t.datee as 'date_estimated_end'
                FROM ".MAIN_DB_PREFIX."projet_task t 
                    LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (t.rowid=tex.fk_object)
                    
                WHERE tex.fk_workstation=".$id_ws." AND t.datee < NOW()
                ORDER BY  t.dateo  ");
    
    _get_time($TRes, $TAxis, $TSerie);
    
    ksort($TAxis);
    ksort($TSerie);
    
    
    foreach($TAxis as &$val) { $val = $val / $nb_second_in_hour; }
    
    $Tab['series']= array(
        0=>array(
            'data'=>array_values($TAxis)
            ,'name'=>'Usage'    
        )
    );
    
    $Tab['axis'] = array_values($TSerie);
    
    return $Tab;
    
}

function _stat_wd(&$PDOdb, $id_ws) {
    
    $ws = new TWorkstation;
    $ws->load($PDOdb, $id_ws);
    
    $TData = _get_data_ws($PDOdb, $id_ws);
    
    ?>
    <table class="border" style="margin-top:20px;width:100%;">
        <tr>
            <td><?php
            echo $ws->getNomUrl(1);
            ?></td>
        </tr>
        <tr>
            <td>
                <div id="stat-ws-<?php echo $id_ws; ?>"></div>
                <?php
            
            $d = new TReport_dashboard();
            $d->format='area';
            
            $d->getChart('stat-ws-'.$id_ws , false , '' , $TData);
            
            ?></td>
        </tr>
        
    </table>
   
    <?php
    
}
