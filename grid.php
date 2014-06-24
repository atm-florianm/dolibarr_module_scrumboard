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

	$number_of_ressource = 3;
	$number_of_columns = $number_of_ressource +1 ;
	$day_height = 25; 

	llxHeader('', $langs->trans('GridTasks') , '','',0,0, array('/scrumboard/js/scrum.js.php','/scrumboard/js/jquery.gridster.js'));

	$form = new Form($db);

?>
	

	<link rel="stylesheet" type="text/css" title="default" href="<?php echo dol_buildpath('/scrumboard/css/scrum.css',1) ?>">

		<div class="content">
	
			<table id="scrum">
				<tr>
					<td style="width: 200px;"><?php echo $langs->trans('Garage') ?></td>
					<td><?php echo $langs->trans('WorkStation') ?> - <?php echo $number_of_ressource.' ressources availables'; ?></td>
				</tr>
				<tr>
					<td class="gridster" id="tasks" colspan="2">
						<ul id="list-task" class="task-list" rel="all-task">
						
						</ul>
					</td>
				</tr>
			</table>
<script type="text/javascript">
var gridster;


$(document).ready(function(){
	
	
	  gridster = $("ul#list-task").gridster({
          widget_base_dimensions: [200, <?php echo $day_height ?>]
          ,widget_margins: [5, 5]
          ,max_cols:<?php echo $number_of_columns; ?>
          ,min_cols:<?php echo $number_of_columns; ?>
          ,serialize_params: function($w, wgd) { 
          	    
          		return { 
          			id:$w.attr('task-id'), 
          			col: wgd.col, 
          			row: wgd.row, 
          			size_x: wgd.size_x, 
          			size_y: wgd.size_y 
          		} 
          }
          ,draggable: {
          	stop:function(e,ui,$widget) {
          	
	          	var s = gridster.serialize();
		
				$.post("./script/interface.php"
					,{
						json:1
						,put : 'coord'
						,coord : s
					}
					
				);
	          	
	          }
          }
        }).data('gridster');

     
       $.ajax({
			url : "./script/interface.php"
			,data: {
				json:1
				,get : 'tasks'
				,status : 'inprogress|todo'
				,id_project : 0
				,async:false
			}
			,dataType: 'json'
		})
		.done(function (tasks) {
			
			gridster.remove_all_widgets();
            
			$.each(tasks, function(i, task) {
			
				$item = $('li#task-blank');
				
				$item.attr('task-id', task.id);
				
				$item.find('[rel=label]').html(task.label).attr("title", task.long_description);
				$item.find('[rel=ref]').html(task.ref).attr("href", '<?php echo dol_buildpath('/projet/tasks/task.php?withproject=1&id=',1) ?>'+task.id);
				$item.find('[rel=project]').html(task.project.title);



				var duration = task.planned_workload;
				if(duration>0) {
					
					if(duration<task.duration_effective) duration = task.duration_effective;
					else duration-=task.duration_effective;
				
					height = Math.ceil( duration / 3600 );
					
				}
				
				if(height<1) height = 1;
			
				date=new Date(task.time_date_end * 1000);
				$item.find('[rel=time-end]').html(date.toLocaleDateString());
			
				gridster.add_widget( '<li task-id="'+task.id+'">'+$item.html()+'</li>', 1, height, task.grid_col, task.grid_row);
				
				if(task.grid_col==1) {
					$('li[task-id='+task.id+']').addClass('garage');
				}
				
            });


			$('*.classfortooltip').tipTip({maxWidth: "600px", edgeOffset: 10, delay: 50, fadeIn: 50, fadeOut: 50})

		}); 
        
});
</script>

<div>
	<span style="background-color:red;">&nbsp;&nbsp;&nbsp;&nbsp;</span> <?php echo $langs->trans('TaskWontfinishInTime'); ?><br />
	<span style="background-color:orange;">&nbsp;&nbsp;&nbsp;&nbsp;</span> <?php echo $langs->trans('TaskMightNotfinishInTime'); ?><br />
	<span style="background-color:#CCCCCC;">&nbsp;&nbsp;&nbsp;&nbsp;</span> <?php echo $langs->trans('BarProgressionHelp'); ?>
	
</div>

		
		</div>
		
		<div style="display:none">
			
			<ul>
			<li id="task-blank">
				<?php echo img_picto('', 'object_scrumboard@scrumboard') ?> <span rel="project"></span> [<a href="#" rel="ref"> </a>] <span rel="label" class="classfortooltip" title="">label</span>
				<div rel="time-end"></div> 
			</li>
			</ul>
			
		</div>
		
<?php

	llxFooter();
