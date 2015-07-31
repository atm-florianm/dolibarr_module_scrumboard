<?php
/* Copyright (C) 2007-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2007-2014 ATM Consulting <contact@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *   	\file       dev/skeletons/skeleton_page.php
 *		\ingroup    mymodule othermodule1 othermodule2
 *		\brief      This file is an example of a php page
 *		\version    $Id: skeleton_page.php,v 1.19 2011/07/31 22:21:57 eldy Exp $
 *		\author		Put author name here
 *		\remarks	Put here some comments
 */
// Change this following line to use the correct relative path (../, ../../, etc)
require '../config.php';
// Change this following line to use the correct relative path from htdocs (do not remove DOL_DOCUMENT_ROOT)
dol_include_once('/core/lib/admin.lib.php');
dol_include_once('/core/class/extrafields.class.php');

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

if($action=='save') {
	
	foreach($_REQUEST['TDivers'] as $name=>$param) {
		
		dolibarr_set_const($db, $name, $param);
		
        if($name == 'SCRUM_USE_PROJECT_PRIORITY' && $param == 1) {
            $extrafields=new ExtraFields($db);
            $default_value = array('options'=> array(0=>$langs->trans('Normal'), 1=>$langs->trans('Important')));
            $res = $extrafields->addExtraField('priority', 'Priorité', 'select', 1, 0, 'projet', false, false, '', serialize( $default_value ) );
        }
        /*else if($name == 'SCRUM_GROUP_TASK_BY_PRODUCT' && $param == 1) {
            $extrafields=new ExtraFields($db);
            $res = $extrafields->addExtraField('grou', 'Priorité', 'select', 1, 0, 'projet', false, false, '', serialize( $default_value ) );
        }*/
        
        
	}
	
	setEventMessage( $langs->trans('RegisterSuccess') );
}


llxHeader('','Gestion de scrumboard, à propos','');

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre('Scrumboard',$linkback,'setup');

showParameters();

function showParameters() {
	global $db,$conf,$langs;
	
	$html=new Form($db);
	
	?><form action="<?=$_SERVER['PHP_SELF'] ?>" name="form1" method="POST" enctype="multipart/form-data">
		<input type="hidden" name="action" value="save" />
	<table width="100%" class="noborder" style="background-color: #fff;">
		<tr class="liste_titre">
			<td colspan="2"><?php echo $langs->trans('Parameters') ?></td>
		</tr>
		
		<tr>
			<td><?php echo $langs->trans('ActivateTitleDatePerDay') ?></td><td><?php
			
				if($conf->global->SCRUM_SEE_DELIVERYDATE_PER_DAY==0) {
					
					 ?><a href="?action=save&TDivers[SCRUM_SEE_DELIVERYDATE_PER_DAY]=1"><?=img_picto($langs->trans("Disabled"),'switch_off'); ?></a><?php
					
				}
				else {
					 ?><a href="?action=save&TDivers[SCRUM_SEE_DELIVERYDATE_PER_DAY]=0"><?=img_picto($langs->trans("Activated"),'switch_on'); ?></a><?php
					
				}
			
			?></td>				
		</tr>

		<tr>
			<td><?php echo $langs->trans('ActivateTitleDatePerWeek') ?></td><td><?php
			
				if($conf->global->SCRUM_SEE_DELIVERYDATE_PER_WEEK==0) {
					
					 ?><a href="?action=save&TDivers[SCRUM_SEE_DELIVERYDATE_PER_WEEK]=1"><?=img_picto($langs->trans("Disabled"),'switch_off'); ?></a><?php
					
				}
				else {
					 ?><a href="?action=save&TDivers[SCRUM_SEE_DELIVERYDATE_PER_WEEK]=0"><?=img_picto($langs->trans("Activated"),'switch_on'); ?></a><?php
					
				}
			
			?></td>				
		</tr>

		<tr>
			<td><?php echo $langs->trans('SetDeliveryDateByOtherTask') ?></td><td><?php
			
				if($conf->global->SCRUM_SET_DELIVERYDATE_BY_OTHER_TASK==0) {
					
					 ?><a href="?action=save&TDivers[SCRUM_SET_DELIVERYDATE_BY_OTHER_TASK]=1"><?=img_picto($langs->trans("Disabled"),'switch_off'); ?></a><?php
					
				}
				else {
					 ?><a href="?action=save&TDivers[SCRUM_SET_DELIVERYDATE_BY_OTHER_TASK]=0"><?=img_picto($langs->trans("Activated"),'switch_on'); ?></a><?php
					
				}
			
			?></td>				
		</tr>

		<tr>
			<td><?php echo $langs->trans('DefaultVelocity') ?></td>
			<td><input type="text" value="<?php echo $conf->global->SCRUM_DEFAULT_VELOCITY ?>" name="TDivers[SCRUM_DEFAULT_VELOCITY]" size="3" /><input type="submit" value="<?php echo $langs->trans('Modify'); ?>" name="bt_submit" /></td>				
		</tr>

        <tr>
            <td><?php echo $langs->trans('NumberOfDayForVelocity') ?></td>
            <td><input type="text" value="<?php echo $conf->global->SCRUM_VELOCITY_NUMBER_OF_DAY ?>" name="TDivers[SCRUM_VELOCITY_NUMBER_OF_DAY]" size="3" /><input type="submit" value="<?php echo $langs->trans('Modify'); ?>" name="bt_submit" /></td>               
        </tr>

        <tr>
            <td><?php echo $langs->trans('NumberOfWorkingHourInDay') ?></td>
            <td><input type="text" value="<?php echo $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY ?>" name="TDivers[TIMESHEET_WORKING_HOUR_PER_DAY]" size="3" /><input type="submit" value="<?php echo $langs->trans('Modify'); ?>" name="bt_submit" /></td>               
        </tr>
        <tr>
            <td><?php echo $langs->trans('ScheduleOfWorkingHourInDay') ?></td>
            <td><input type="text" value="<?php echo $conf->global->TIMESHEET_WORKING_SCHEDULE ?>" name="TDivers[TIMESHEET_WORKING_SCHEDULE]" size="80" /><input type="submit" value="<?php echo $langs->trans('Modify'); ?>" name="bt_submit" /></td>               
        </tr>

        <tr>
            <td><?php echo $langs->trans('DayNoWorking') ?></td>
            <td><input type="text" value="<?php echo $conf->global->TIMESHEET_DAYOFF ?>" name="TDivers[TIMESHEET_DAYOFF]" size="30" /><input type="submit" value="<?php echo $langs->trans('Modify'); ?>" name="bt_submit" /></td>               
        </tr>

        <tr>
            <td><?php echo $langs->trans('UseProjectPriority') ?></td><td><?php
            
                if($conf->global->SCRUM_USE_PROJECT_PRIORITY==0) {
                    
                     ?><a href="?action=save&TDivers[SCRUM_USE_PROJECT_PRIORITY]=1"><?=img_picto($langs->trans("Disabled"),'switch_off'); ?></a><?php
                    
                }
                else {
                     ?><a href="?action=save&TDivers[SCRUM_USE_PROJECT_PRIORITY]=0"><?=img_picto($langs->trans("Activated"),'switch_on'); ?></a><?php
                    
                }
            
            ?></td>             
        </tr>

		<tr>
            <td><?php echo $langs->trans('GroupTaskByProduct') ?></td><td><?php
            
                if($conf->global->SCRUM_GROUP_TASK_BY_PRODUCT==0) {
                    
                     ?><a href="?action=save&TDivers[SCRUM_GROUP_TASK_BY_PRODUCT]=1"><?=img_picto($langs->trans("Disabled"),'switch_off'); ?></a><?php
                    
                }
                else {
                     ?><a href="?action=save&TDivers[SCRUM_GROUP_TASK_BY_PRODUCT]=0"><?=img_picto($langs->trans("Activated"),'switch_on'); ?></a><?php
                    
                }
            
            ?></td>             
        </tr>
        <tr>
            <td><?php echo $langs->trans('ProductTolerance') ?></td>
            <td><input type="text" value="<?php echo $conf->global->SCRUM_GROUP_TASK_BY_PRODUCT_TOLERANCE ?>" name="TDivers[SCRUM_GROUP_TASK_BY_PRODUCT_TOLERANCE]" size="3" /><input type="submit" value="<?php echo $langs->trans('Modify'); ?>" name="bt_submit" /></td>               
        </tr>
        <tr>
            <td><?php echo $langs->trans('TimeMoreForPrevision') ?></td>
            <td><input type="text" value="<?php echo $conf->global->SCRUM_TIME_MORE_PREVISION ?>" name="TDivers[SCRUM_TIME_MORE_PREVISION]" size="3" /><input type="submit" value="<?php echo $langs->trans('Modify'); ?>" name="bt_submit" /></td>               
        </tr>
	</table>
	</form>
	
	<br /><br />
	<?php
}
?>

<table width="100%" class="noborder">
	<tr class="liste_titre">
		<td>A propos</td>
		<td align="center">&nbsp;</td>
		</tr>
		<tr class="impair">
			<td valign="top">Module développé par </td>
			<td align="center">
				<a href="http://www.atm-consulting.fr/" target="_blank">ATM Consulting</a>
			</td>
		</td>
	</tr>
</table>
<?php

// Put here content of your page
// ...

/***************************************************
* LINKED OBJECT BLOCK
*
* Put here code to view linked object
****************************************************/
//$somethingshown=$asset->showLinkedObjectBlock();

// End of page
$db->close();
llxFooter('$Date: 2011/07/31 22:21:57 $ - $Revision: 1.19 $');
