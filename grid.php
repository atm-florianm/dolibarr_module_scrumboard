<?php
/* Copyright (C) 2014 Alexis Algoud        <support@atm-conuslting.fr>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       /scrumboard/scrum.php
 *	\ingroup    projet
 *	\brief      Project card
 */

 
	require('config.php');

	/*$TWorkstation = array(
	
		0=>array('nb_ressource'=>1, 'velocity'=>1, 'background'=>'linear-gradient(to right,white, #ccc)', 'name'=>'Non ordonnancé') // base de 7h par jour
		,1=>array('nb_ressource'=>2, 'velocity'=>(5/7), 'background'=>'linear-gradient(to right,white, #660000)', 'name'=>'Stagiaire') // base de 7h par jour
		,2=>array('nb_ressource'=>2, 'velocity'=>(5.5/7), 'background'=>'linear-gradient(to right,white, #cccc00)', 'name'=>'devconfirme')
		,3=>array('nb_ressource'=>1, 'velocity'=>1, 'background'=>'linear-gradient(to right,white,#00cc00)', 'name'=>'DSI')
	);*/
	
	$TWorkstation = array(
        0=>array('nb_ressource'=>1, 'velocity'=>1, 'background'=>'linear-gradient(to right,white, #ccc)', 'name'=>'Non ordonnancé') // base de 7h par jour
    );
	
    if($conf->workstation->enabled) {
        define('INC_FROM_DOLIBARR',true);
        dol_include_once('/workstation/config.php');
        $ATMdb=new TPDOdb;
        $TWorkstation=array_merge($TWorkstation, TWorkstation::getWorstations($ATMdb,true));
        
    }
    else {
        setEventMessage($langs->trans("moduleWorkstationNeeded").' : <a href="https://github.com/ATM-Consulting/dolibarr_module_workstation" target="_blank">'.$langs->trans('DownloadModule').'</a>','errors');
    }

	$number_of_columns = 0 ;
	foreach($TWorkstation as $w_name=>$w_param) {
		$number_of_columns+=$w_param['nb_ressource'];
	}

	$hh =  GETPOST('hour_height');
    if(!empty($hh)) $_SESSION['hour_height'] = (int)$hh;
	
	$hour_height = empty($_SESSION['hour_height']) ? 50 : $_SESSION['hour_height'];
	
	$day_height =  $hour_height * 7;

	llxHeader('', $langs->trans('GridTasks') , '','',0,0, array('/scrumboard/js/scrum.js.php'));

	$form = new Form($db);

?>
	<link rel="stylesheet" type="text/css" title="default" href="<?php echo dol_buildpath('/scrumboard/css/scrum.css',1) ?>">

		<div class="content">
	
			<table id="scrum" style="width: inherit;">
				<tr>
					<td>
					    <?php echo $langs->trans('WorkStation') ?> - <?php echo ($number_of_columns-1).' '.$langs->trans('NumberOfQueue'); ?>
					    <br />
					    <?php echo $langs->trans('HourHeight') ?> : <a href="?hour_height=10"><?php echo $langs->trans('Small') ?></a> <a href="?hour_height=50"><?php echo $langs->trans('Middle') ?></a> <a href="?hour_height=100"><?php echo $langs->trans('High') ?></a>
					</td>
				</tr>group.class.php
				<tr>
					<td class="gridster" id="tasks" style="position:relative;">
						<table><tr>
						<?php
						$column_width = 200;
						
						_draw_grid($TWorkstation, $column_width);
						
						?>
						<td valign="top" class="projects">
						    <?php echo $langs->trans('Projects') ?>
						    <ul style="position:relative;width:200px;" id="list-projects" class="task-list needToResize" >
                        
                            </ul>
						</td>
						</tr></table>
					</td>
				</tr>
			</table>
<?php

_js_grid($TWorkstation, $day_height, $column_width);

function _js_grid(&$TWorkstation, $day_height, $column_width) {
		?>		
		        <script type="text/javascript">
		            var http = "<?php echo DOL_URL_ROOT; ?>";
		            var w_column = <?php echo $column_width; ?>;
		            var h_day = <?php echo $day_height; ?>;
		        </script>
		        <script type="text/javascript" src="./js/ordo.js"></script>
				<script type="text/javascript">
				var TVelocity = [];
				
				$(document).ready(function(){
				
				     var ordo = new TOrdonnancement();
					 
					 <?php
					 	foreach($TWorkstation as $w_name=>$w_param) {
					 		?>
					 		
					 		var w = new TWorkstation();
                            w.nb_ressource = <?php echo $w_param['nb_ressource']; ?>;
                            w.velocity = <?php echo $w_param['velocity']; ?>;
                            w.id = "<?php echo $w_name; ?>";
					 		
					 		ordo.addWorkstation(w);
	
					 		<?php
						}
					 ?>
					  
					ordo.init(w_column, h_day,0.08); 		  
					
				});
				</script><?php	
	
}

function _draw_grid(&$TWorkstation, $column_width) {
	
	$width_table = 0;
	foreach($TWorkstation as $w_name=>$w_param) {
		$back = empty($w_param['background']) ? '' : 'background:'.$w_param['background'].';';
		$w_column = $column_width*$w_param['nb_ressource'];
		
		$width_table+=$w_column;	
		?><td valign="top" style="width:<?php echo round($w_column); ?>px; <?php echo $back; ?> border:1px solid #666;">
		        <?php echo $w_param['name']; ?>
				<ul style="position:relative;min-height: 500px;" id="list-task-<?php echo $w_name; ?>" ws-id="<?php echo $w_name; ?>" class="task-list droppable connectedSortable needToResize" rel="all-task" ws-nb-ressource="<?php echo $w_param['nb_ressource']; ?>">
						
				</ul>

		</td><?php 
		
	}
		
	?>
	<script type="text/javascript">
		$('table#scrum').css('min-width', <?php echo $width_table+50 ?>);
		
		
	</script>
	<?php
							
}
	
	
	?>
<div>
	<span style="background-color:red;">&nbsp;&nbsp;&nbsp;&nbsp;</span> <?php echo $langs->trans('TaskWontfinishInTime'); ?><br />
	<span style="background-color:orange;">&nbsp;&nbsp;&nbsp;&nbsp;</span> <?php echo $langs->trans('TaskMightNotfinishInTime'); ?><br />
	<span style="background-color:#CCCCCC;">&nbsp;&nbsp;&nbsp;&nbsp;</span> <?php echo $langs->trans('BarProgressionHelp'); ?>
	
</div>

		
		</div>
		
		<div style="display:none">
			
			<ul>
			<li id="task-blank">
				<header>|||</header>
				<div rel="content">
    				<span rel="project" style="display:none;"></span> <span rel="task-link">[<a href="#" rel="ref"> </a>] <span rel="label" class="classfortooltip" title="">label</span></span>
    				<div rel="time-end"></div>
    				
				</div> 
				<div class="loading"></div>
			</li>
			</ul>
			
		</div>
		
<?php

	llxFooter();
