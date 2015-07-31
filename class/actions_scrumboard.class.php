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
		
		if (in_array('ordercard',explode(':',$parameters['context']))) 
        {
        	?>
				<tr>
					<td>Fin de production prévisionnelle</td>
					<td rel="date_fin_prod">
				<?php
				
			
        	$res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."assetOf 
        		WHERE fk_commande = ".$object->id." AND status IN ('VALID','OPEN','CLOSE')");
			if($obj = $db->fetch_object($res)) {
				// l'of existe déjà et est valide
				
				$res = $db->query("SELECT MAX(date_estimated_end) as date_estimated_end 
					FROM ".MAIN_DB_PREFIX."projet_task t 
					LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
						WHERE tex.fk_of = ".$obj->rowid);
						
				if($obj = $db->fetch_object($res)) {
					$t = strtotime($obj->date_estimated_end);
					print dol_print_date($t,'day').img_info('Temps actuel présent dans l\'ordonnancement. Attention, peut-être revu à tout moment');
				}
				else {
					print 'Pas de tâche ordonnancée';
				}
				
			}
			else {
				
				print '<a href="javascript:simulOrdo('.$object->id.')">Simuler l\'ordonnancement</a>';
			}
			
			?>
			<script type="text/javascript">
				function simulOrdo(fk_commande) {
					$('td[rel="date_fin_prod"]').html("Patientez svp...");
					$.ajax({
						url:"<?php echo dol_buildpath('/scrumboard/script/interface.php', 1); ?>"
						,data:{
							get:'task-ordo-simulation'
							,fk_commande: fk_commande
						}
					}).done(function(data) {
						$('td[rel="date_fin_prod"]').html(data+'<?php 
								echo addslashes(img_info('Temps calculé. Ne peut être qu\'indicatif. Non contractuel')); 
						?>');
					});
					
				}
				
			</script>
			
			</td>
			</tr>
			<?php
			
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