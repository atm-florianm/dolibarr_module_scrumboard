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

	$TWorkstation = array(
	
		0=>array('nb_ressource'=>1, 'velocity'=>1, 'background'=>'linear-gradient(to right,white, #ccc)', 'name'=>'Non ordonnancé') // base de 7h par jour
		,1=>array('nb_ressource'=>2, 'velocity'=>(5/7), 'background'=>'linear-gradient(to right,white, #660000)', 'name'=>'Stagiaire') // base de 7h par jour
		,2=>array('nb_ressource'=>2, 'velocity'=>(5.5/7), 'background'=>'linear-gradient(to right,white, #cccc00)', 'name'=>'devconfirme')
		,3=>array('nb_ressource'=>1, 'velocity'=>(2/7), 'background'=>'linear-gradient(to right,white,#00cc00)', 'name'=>'DSI')
	);

	$number_of_columns = 1 ;
	foreach($TWorkstation as $w_name=>$w_param) {
		$number_of_columns+=$w_param['nb_ressource'];
		
		
	}

	$number_of_ressource = 3;
	
	$hour_height = 1;
	
	$day_height =  $hour_height * 7;

	llxHeader('', $langs->trans('GridTasks') , '','',0,0, array('/scrumboard/js/scrum.js.php','/scrumboard/js/jquery.gridster.js'));

	$form = new Form($db);

?>
	

	<link rel="stylesheet" type="text/css" title="default" href="<?php echo dol_buildpath('/scrumboard/css/scrum.css',1) ?>">

		<div class="content">
	
			<table id="scrum">
				<tr>
					<td><?php echo $langs->trans('WorkStation') ?> - <?php echo $number_of_ressource.' ressources availables'; ?><br /><br /><br /></td>
				</tr>
				<tr>
					<td class="gridster" id="tasks" style="position:relative; width:100%">
						<table><tr>
						<?php
						$column_width = 200;
						
						_draw_grid($TWorkstation, $column_width);
						
						
						
						?></tr></table>
					</td>
				</tr>
			</table>
<?php

_js_grid($TWorkstation, $day_height, $column_width);

function _js_grid(&$TWorkstation, $day_height, $column_width) {
		?>		
				<script type="text/javascript">
				var gridster = [];
				var TVelocity = [];
				
				$(document).ready(function(){
					
					 <?php
					 	foreach($TWorkstation as $w_name=>$w_param) {
					 		?>
					 		TVelocity[<?php echo $w_name; ?>] = <?php echo $w_param['velocity']; ?>
					 		
						 		gridster["<?php echo $w_name; ?>"] = $("ul#list-task-<?php echo $w_name; ?>").gridster({
							          widget_base_dimensions: [<?php echo $column_width.','.$day_height  ?>]
							          ,widget_margins: [5, 5]
							          ,max_cols:<?php echo $w_param['nb_ressource']; ?>
							          ,min_cols:<?php echo $w_param['nb_ressource']; ?>
							          ,serialize_params: function($w, wgd) { 
							          	    
							          	    $w.find('header').html(wgd.size_y+'h')
							          	    
							          		return { 
							          			id:$w.attr('task-id'), 
							          			col: wgd.col, 
							          			row: wgd.row, 
							          			size_x: wgd.size_x, 
							          			size_y: wgd.size_y, 
							          			fk_workstation: '<?php echo $w_name; ?>'
							          		} 
							          }
							          ,draggable: {
							          	handle: 'header'
							          	,stop:function(e,ui,$widget) {
							          	
								          	var s = gridster["<?php echo $w_name; ?>"].serialize();
									
											$.post("./script/interface.php"
												,{
													json:1
													,put : 'coord'
													,coord : s
												}
												
											);
								          	
								          }
							          }
							          ,resize: {
							            enabled: true,
							            resize: function(e, ui, $widget) {
							              var s = gridster["<?php echo $w_name; ?>"].serialize();
							            },
							            stop: function(e, ui, $widget) {
							              var s = gridster["<?php echo $w_name; ?>"].serialize();
									
											$.post("./script/interface.php"
												,{
													json:1
													,put : 'resize'
													,coord : s
												}
												
											);
							            }
							          }
							        }).data('gridster');
							
								
					 		<?php
						}
					 ?>
					  
				  		 $.ajax({
									url : "./script/interface.php"
									,data: {
										json:1
										,get : 'tasks'
										,status : 'inprogress|todo'
										,gridMode : 1 
										,id_project : 0
										,async:false
									}
									,dataType: 'json'
								})
								.done(function (tasks) {
									
									$.each(tasks, function(i, task) {
									
										$item = $('li#task-blank');
										
										$item.attr('task-id', task.id);
										
										$item.find('[rel=label]').html(task.label).attr("title", task.long_description);
										$item.find('[rel=ref]').html(task.ref).attr("href", '<?php echo dol_buildpath('/projet/tasks/task.php?withproject=1&id=',1) ?>'+task.id);
										$item.find('[rel=project]').html(task.project.title);
						
										var duration = task.planned_workload;
										var height = 1;
										
										if(duration>0) {
											//duration-=task.duration_effective;
											height = Math.ceil( duration / 3600 );
										}
						
										
										
										if(height<1) height = 1;
									
										date=new Date(task.time_date_end * 1000);
										$item.find('[rel=time-end]').html(date.toLocaleDateString());
									
										$item.find('header').html(height+'h');
									   
									    $w = gridster[task.fk_workstation].add_widget( '<li task-id="'+task.id+'" class="draggable">'+$item.html()+'</li>', task.needed_ressource, height, task.grid_col, task.grid_row);
									
									    
									    if(task.fk_task_parent>0) {
                                           var rowparent = $('li[task-id='+task.fk_task_parent+']').attr('data-row');
                                           if(rowparent>=task.grid_row) {
                                               task.grid_row = rowparent+1;
                                               gridster[task.fk_workstation].move_widget_up($w, task.grid_row);
                                           }    
                                        }
									
										$('li[task-id='+task.id+'] select[name=fk_workstation]').val(task.fk_workstation);
										
										if(duration < task.duration_effective) {
											
											$('li[task-id='+task.id+']').css('background-color','red');
											
										}
										
						            });
						
									
						
									$('*.classfortooltip').tipTip({maxWidth: "600px", edgeOffset: 10, delay: 50, fadeIn: 50, fadeOut: 50})
						
								}); 
					 		  
				        
					    
				        
				});
				</script><?php	
	
}

function _draw_grid(&$TWorkstation, $column_width) {
	
	
	foreach($TWorkstation as $w_name=>$w_param) {
		$back = empty($w_param['background']) ? '' : 'background:'.$w_param['background'].';';
		$w_column = $column_width*$w_param['nb_ressource'];
		
		?><td valign="top" style="width:<?php echo round($w_column); ?>px; <?php echo $back; ?> border:1px solid #666;"><?php echo $w_param['name']; ?>
		
				<ul id="list-task-<?php echo $w_name; ?>" class="task-list" rel="all-task">
						
				</ul>
				
				
				
		</td><?php 
		
	}
		
							
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
				<span rel="project"></span> [<a href="#" rel="ref"> </a>] <span rel="label" class="classfortooltip" title="">label</span>
				<span rel="fk_workstation"><select name="fk_workstation"><?php
					foreach($TWorkstation as $w_id=>$w_param) {
							?><option value="<?php echo $w_id; ?>"><?php echo $w_param['name']; ?></option><?php
					}
				?></select></span>
				<div rel="time-end"></div> 
			</li>
			</ul>
			
		</div>
		
<?php

	llxFooter();
