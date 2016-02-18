<?php
class ActionsScrumboard
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */
      
    function formObjectOptions($parameters, &$object, &$action, $hookmanager) 
    {  
      	global $langs,$db,$conf;
		
		if ( (in_array('ordercard',explode(':',$parameters['context'])) || in_array('propalcard',explode(':',$parameters['context']))) 
			&& !empty($conf->of->enabled) && !empty($object->id) 
			) 
        {
        	?>
				<tr>
					<td>Fin de production prévisionnelle</td>
					<td rel="date_fin_prod">
				<?php
				
			
			$is_commande = in_array('ordercard',explode(':',$parameters['context']));
			
			if($is_commande) {
	        		$res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."assetOf 
	        		WHERE fk_commande = ".$object->id." AND status IN ('VALID','OPEN','CLOSE')");
	               
				    $TOfId=array();
				   	while($obj = $db->fetch_object($res)) {
				   		
						$TOfId[] = $obj->rowid;
						
				   	}
				   
					if(!empty($TOfId)) {
						// l'of existe déjà et est valide
						
						$res = $db->query("SELECT MAX(date_estimated_end) as date_estimated_end 
							FROM ".MAIN_DB_PREFIX."projet_task t 
							LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
								WHERE tex.fk_of IN (".implode(',',$TOfId).")");
								
							if($obj = $db->fetch_object($res)) {
								$t = strtotime($obj->date_estimated_end);
								print dol_print_date($t,'day').img_info('Temps actuel présent dans l\'ordonnancement. Attention, peut-être revu à tout moment');
							}
							else {
								print 'Pas de tâche ordonnancée ou restant à ordonnancer';
							}
					
					}
					else {
						print '<a href="javascript:simulOrdo('.$object->id.')">Simuler l\'ordonnancement</a>';
					}
				
			}
			else {
				
				print '<a href="javascript:simulOrdo('.$object->id.')">Simuler l\'ordonnancement</a>';
			}
			
			?>
			<script type="text/javascript">
				function simulOrdo(fk_object) {
					$('td[rel="date_fin_prod"]').html("Patientez svp...");
					$.ajax({
						url:"<?php echo dol_buildpath('/scrumboard/script/interface.php', 1); ?>"
						,data:{
							get:'task-ordo-simulation'
							,fk_object: fk_object
							,type_object : "<?php echo $is_commande ? 'order' : 'propal'	?>"
							
						}
					}).done(function(data) {
						$('td[rel="date_fin_prod"]').html(data+'<?php 
								echo addslashes(img_info('Temps calculé sur base automatique avec un lancement immédiat. Ne peut être qu\'indicatif. Non contractuel')); 
						?>');
					});
					
				}
				
			</script>
			
			</td>
			</tr>
			<?php
			
		}
		else if (in_array('projectcard',explode(':',$parameters['context']))) 
        {
        	
			if($object->id>0) {
			 
        	?>
				<tr>
					<td>Fin de production prévisionnelle</td>
					<td rel="date_fin_prod">
				<?php
				
				$res = $db->query("SELECT MAX(date_estimated_end) as date_estimated_end 
					FROM ".MAIN_DB_PREFIX."projet_task t 
					LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
						WHERE t.fk_projet = ".$object->id);
						
					if($obj = $db->fetch_object($res)) {
						$t = strtotime($obj->date_estimated_end);
						if($t != '-62169987208'){
							print dol_print_date($t,'day').img_info('Temps actuel présent dans l\'ordonnancement. Attention, peut-être revu à tout moment');
						}
						else {
							print 'Pas de tâche ordonnancée';
						}
					}
					else {
						print 'Pas de tâche ordonnancée';
					}
				
				?>
				</td></tr>
				<?php
				
			}
		}
		else if (in_array('actioncard',explode(':',$parameters['context']))) 
        {
        	
			$fk_task = 0;
			if($action!='create') {
				$object->fetchObjectLinked();
				
				//var_dump($object->linkedObjectsIds['task']);
				if(!empty($object->linkedObjectsIds['task'])) {
					list($key, $fk_task) = each($object->linkedObjectsIds['task']);	
				}
				
			}
			
			if($action == 'edit' || $action == 'create') {
	        	?>
				<script type="text/javascript">
					$('#projectid').after('<span rel="fk_task"></span>');
				
					$('#projectid').change(function() {
						
						var fk_project = $(this).val();
						
						$.ajax({
							url:"<?php echo dol_buildpath('/scrumboard/script/interface.php',1) ?>"
							,data: {
								get:"select-task"
								,fk_task:<?php echo $fk_task ?>
								,fk_project : fk_project
							}
								
						}).done(function(data) {
							$('span[rel=fk_task]').html(data);	
						});
						
						
						
						
					});
					$('#projectid').change();
				</script>	
				<?php				
			}
			else {
				dol_include_once('/projet/class/task.class.php');
				$task = new Task($db);
				$task->fetch($fk_task);
				if(!empty($task->id))
				{
				?>
				<tr>
					<td><?php echo $langs->trans('Task'); ?></td>
					<td rel="fk_task">
					<?php
						echo $task->getNomUrl(1).' '.$task->label;
					?>
					</td>
				</tr>
				<?php
				}
			}

		}
		return 0;
	}
     
	
    function formEditProductOptions($parameters, &$object, &$action, $hookmanager) 
    {
		
    	if (in_array('invoicecard',explode(':',$parameters['context'])))
        {
        	
        }
		
        return 0;
    }

	function formAddObjectLine ($parameters, &$object, &$action, $hookmanager) {
		
		global $db;
		
		if (in_array('ordercard',explode(':',$parameters['context'])) || in_array('invoicecard',explode(':',$parameters['context']))) 
        {
        	
        }

		return 0;
	}

	function printObjectLine ($parameters, &$object, &$action, $hookmanager){
		
		global $db;
		
		if (in_array('ordercard',explode(':',$parameters['context'])) || in_array('invoicecard',explode(':',$parameters['context']))) 
        {
        	
        }

		return 0;
	}
}