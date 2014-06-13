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

	llxHeader('', $langs->trans('GridTasks') , '','',0,0, array('/scrumboard/js/scrum.js.php','/scrumboard/js/jquery.gridster.js'));

	$form = new Form($db);

?>
	<link rel="stylesheet" type="text/css" title="default" href="<?php echo dol_buildpath('/scrumboard/css/scrum.css',1) ?>">

		<div class="content">
	
			<table id="scrum">
				<tr>
					<td><?php echo $langs->trans('Users'); ?></td>
					<td><?php echo $langs->trans('Tasks'); ?></td>
				</tr>
				<tr>
					<td class="gridster" id="tasks">
						<ul id="list-task" class="task-list" rel="all-task">
						
						</ul>
					</td>
				</tr>
			</table>

<div>
	<span style="background-color:red;">&nbsp;&nbsp;&nbsp;&nbsp;</span> <?php echo $langs->trans('TaskWontfinishInTime'); ?><br />
	<span style="background-color:orange;">&nbsp;&nbsp;&nbsp;&nbsp;</span> <?php echo $langs->trans('TaskMightNotfinishInTime'); ?><br />
	<span style="background-color:#CCCCCC;">&nbsp;&nbsp;&nbsp;&nbsp;</span> <?php echo $langs->trans('BarProgressionHelp'); ?>
	
</div>

		
		</div>
		
		<div style="display:none">
			
			<ul>
			<li id="task-blank">
				<div class="progressbaruser"></div>
				<div class="progressbar"></div>
				<div class="actions">
				<select rel="progress" class="nodisplaybutinprogress">
					<?php
					for($i=5; $i<=95;$i+=5) {
						?><option value="<?php echo $i ?>"><?php echo $i ?>%</option><?php
					}
					?>
				</select>
				<span rel="time"></span>
				</div>
				
				<?php echo img_picto('', 'object_scrumboard@scrumboard') ?> [<a href="#" rel="ref"> </a>] <span rel="label" class="classfortooltip" title="">label</span> 
			</li>
			</ul>
			
		</div>
		
		
		<div id="saisie" style="display:none;"></div>
		<div id="reset-date" title="<?php echo $langs->trans('ResetDate'); ?>" style="display:none;">
			
			<p><?php echo $langs->trans('ResetDateWithThisVelocity'); ?> : </p>
			
			<input type="text" name="velocity" size="5" id="current-velocity" value"<?php echo $conf->global->SCRUM_DEFAULT_VELOCITY*3600; ?>" /> <?php echo $langs->trans('HoursPerDay') ?>
			
		</div>
		
		<script type="text/javascript">
			$(document).ready(function() {
				project_loadTasks(<?php echo $id_projet ?>);
				project_init_change_type(<?php echo $id_projet ?>);
				project_velocity(<?php echo $id_projet ?>);
			});
		</script>
		
<?php

	llxFooter();
