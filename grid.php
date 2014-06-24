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

	$number_of_columns = 4;
	$day_height = 25; 

	llxHeader('', $langs->trans('GridTasks') , '','',0,0, array('/scrumboard/js/scrum.js.php','/scrumboard/js/jquery.gridster.js'));

	$form = new Form($db);

?>
	<style type="text/css">
		
		/*! gridster.js - v0.5.2 - 2014-06-16
		* http://gridster.net/
		* Copyright (c) 2014 ducksboard; Licensed MIT */
		
		.gridster {
		    position:relative;
		}
		
		.gridster > * {
		    margin: 0 auto;
		    -webkit-transition: height .4s, width .4s;
		    -moz-transition: height .4s, width .4s;
		    -o-transition: height .4s, width .4s;
		    -ms-transition: height .4s, width .4s;
		    transition: height .4s, width .4s;
		}
		
		.gridster .gs-w {
		    z-index: 2;
		    position: absolute;
		    text-align: left;
		}
		
		.ready .gs-w:not(.preview-holder) {
		    -webkit-transition: opacity .3s, left .3s, top .3s;
		    -moz-transition: opacity .3s, left .3s, top .3s;
		    -o-transition: opacity .3s, left .3s, top .3s;
		    transition: opacity .3s, left .3s, top .3s;
		}
		
		.ready .gs-w:not(.preview-holder),
		.ready .resize-preview-holder {
		    -webkit-transition: opacity .3s, left .3s, top .3s, width .3s, height .3s;
		    -moz-transition: opacity .3s, left .3s, top .3s, width .3s, height .3s;
		    -o-transition: opacity .3s, left .3s, top .3s, width .3s, height .3s;
		    transition: opacity .3s, left .3s, top .3s, width .3s, height .3s;
		}
		
		.gridster .preview-holder {
		    z-index: 1;
		    position: absolute;
		    background-color: #fff;
		    border-color: #fff;
		    opacity: 0.3;
		}
		
		.gridster .player-revert {
		    z-index: 10!important;
		    -webkit-transition: left .3s, top .3s!important;
		    -moz-transition: left .3s, top .3s!important;
		    -o-transition: left .3s, top .3s!important;
		    transition:  left .3s, top .3s!important;
		}
		
		.gridster .dragging,
		.gridster .resizing {
		    z-index: 10!important;
		    -webkit-transition: all 0s !important;
		    -moz-transition: all 0s !important;
		    -o-transition: all 0s !important;
		    transition: all 0s !important;
		}
		
		
		.gs-resize-handle {
		    position: absolute;
		    z-index: 1;
		}
		
		.gs-resize-handle-both {
		    width: 20px;
		    height: 20px;
		    bottom: -8px;
		    right: -8px;
		    background-image: url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pg08IS0tIEdlbmVyYXRvcjogQWRvYmUgRmlyZXdvcmtzIENTNiwgRXhwb3J0IFNWRyBFeHRlbnNpb24gYnkgQWFyb24gQmVhbGwgKGh0dHA6Ly9maXJld29ya3MuYWJlYWxsLmNvbSkgLiBWZXJzaW9uOiAwLjYuMSAgLS0+DTwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DTxzdmcgaWQ9IlVudGl0bGVkLVBhZ2UlMjAxIiB2aWV3Qm94PSIwIDAgNiA2IiBzdHlsZT0iYmFja2dyb3VuZC1jb2xvcjojZmZmZmZmMDAiIHZlcnNpb249IjEuMSINCXhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHhtbDpzcGFjZT0icHJlc2VydmUiDQl4PSIwcHgiIHk9IjBweCIgd2lkdGg9IjZweCIgaGVpZ2h0PSI2cHgiDT4NCTxnIG9wYWNpdHk9IjAuMzAyIj4NCQk8cGF0aCBkPSJNIDYgNiBMIDAgNiBMIDAgNC4yIEwgNCA0LjIgTCA0LjIgNC4yIEwgNC4yIDAgTCA2IDAgTCA2IDYgTCA2IDYgWiIgZmlsbD0iIzAwMDAwMCIvPg0JPC9nPg08L3N2Zz4=');
		    background-position: top left;
		    background-repeat: no-repeat;
		    cursor: se-resize;
		    z-index: 20;
		}
		
		.gs-resize-handle-x {
		    top: 0;
		    bottom: 13px;
		    right: -5px;
		    width: 10px;
		    cursor: e-resize;
		}
		
		.gs-resize-handle-y {
		    left: 0;
		    right: 13px;
		    bottom: -5px;
		    height: 10px;
		    cursor: s-resize;
		}
		
		.gs-w:hover .gs-resize-handle,
		.resizing .gs-resize-handle {
		    opacity: 1;
		}
		
		.gs-resize-handle,
		.gs-w.dragging .gs-resize-handle {
		    opacity: 0;
		}
		
		.gs-resize-disabled .gs-resize-handle {
		    display: none!important;
		}
		
		[data-max-sizex="1"] .gs-resize-handle-x,
		[data-max-sizey="1"] .gs-resize-handle-y,
		[data-max-sizey="1"][data-max-sizex="1"] .gs-resize-handle {
		    display: none !important;
		}
		.gridster * {
  margin:0;
  padding:0;
}

ul {
  list-style-type: none;
}



.controls {
    margin-bottom: 20px;
}

/*/
/* gridster
/*/

.gridster ul {
    background-color: #EFEFEF;
}

.gridster li {
    font-size: 1em;
    font-weight: bold;
    text-align: center;
    line-height: 100%;
}


.gridster {
    margin: 0 auto;

    opacity: .8;

    -webkit-transition: opacity .6s;
    -moz-transition: opacity .6s;
    -o-transition: opacity .6s;
    -ms-transition: opacity .6s;
    transition: opacity .6s;
}

.gridster .gs-w {
    background: #DDD;
    cursor: pointer;
}

.gridster .player {
    background: #BBB;
}


.gridster .preview-holder {
    border: none!important;
    background: red!important;
}


	</style>
	

	<link rel="stylesheet" type="text/css" title="default" href="<?php echo dol_buildpath('/scrumboard/css/scrum.css',1) ?>">

		<div class="content">
	
			<table id="scrum">
				<tr>
					<td><?=$langs->trans('WorkStation') ?> - <?php echo $number_of_columns.' ressources availables'; ?></td>
				</tr>
				<tr>
					<td class="gridster" id="tasks">
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
			//	size_x, size_y, col, row
				$item = $('li#task-blank');
				$item.find('[rel=label]').html(task.label).attr("title", task.long_description);
				$item.find('[rel=ref]').html(task.ref).attr("href", '<?php echo dol_buildpath('/projet/tasks/task.php?withproject=1&id=',1) ?>'+task.id);
				$item.find('[rel=project]').html(task.project.title);



				var duration = task.planned_workload;
				if(duration<task.duration_effective) duration = task.duration_effective;
				else duration-=task.duration_effective;
			
				height = Math.ceil( duration / 3600 );
				if(height<1) height = 1;
			
				date=new Date(task.time_date_end * 1000);
				$item.find('[rel=time-end]').html(date.toLocaleDateString());
			
			    gridster.add_widget('<li task-id="'+task.id+'">'+$item.html()+'</li>', 1, height, task.grid_col, task.grid_row);
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
