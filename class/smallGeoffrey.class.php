<?php

class TSmallGeoffrey {

    function __construct($width, $nb_hour_before = 0, $nb_hour_after = 0) {
        
        $this->top = 0;
        $this->width = $width;
        $this->nb_hour_before = $nb_hour_before;
		$this->nb_hour_after = $nb_hour_after;
		
        $this->TBox = array();
        
        $this->debug = false;
    }

    function getMinY($fk_task_parent) {
	global $db, $conf; 
	    
        $yMin = 0; 
        if($fk_task_parent>0) {
	        // dans la même file ? 
	        foreach($this->TBox as &$box) {
	            if($box->taskid == $fk_task_parent) {
	                $yMin = $box->top + $box->height ;
                    	break;
	            }
	        }
	    
            if(empty($yMin)) {
                $sql = "SELECT t.grid_row,t.grid_height,t.planned_workload,t.progress,tex.fk_workstation 
                    FROM ".MAIN_DB_PREFIX."projet_task t 
                    LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (t.rowid=tex.fk_object)
                    WHERE t.rowid = ".$fk_task_parent." AND t.progress<100 AND t.planned_workload>0";

				if(empty($conf->global->SCRUM_ALLOW_ALL_TASK_IN_GRID)) $sql.=" AND tex.grid_use = 1";

                $res = $db->query($sql);    
                
                $obj = $db->fetch_object($res);
                if($obj) {
                    $yMin = $obj->grid_row + $obj->grid_height;
                }
                
            }
	           
            
	}
	    
	return array($yMin,0);
    }

    function addBox($top,$left,$height,$width, $taskid=0, $fk_task_parent=0, $TUser=array()) {
        
        $box = new stdClass;
        $box->top = $top;
        $box->left = $left;
        $box->height = $height;
        $box->width = $width;
        $box->taskid = $taskid;
        $box->fk_task_parent = $fk_task_parent;
        $box->$TUser = $TUser;
        
        $this->TBox[] = $box;
        
        usort($this->TBox, array('TSmallGeoffrey','sortBoxes'));
        
    }

    function sortBoxes(&$a, &$b) {
        
          if($a->top < $b->top) return -1;
          else if($a->top > $b->top) return 1;
          else {
              
              if($a->left < $b->left) return -1;
              elseif($a->left > $b->left) return 1; 
              else return 0;
          }
        
    }

    function getBoxes($y) { // récupère les boxes à cette hauteur
        $Tab = array();
        foreach($this->TBox as &$box) {
            
            if($box->top<=$y && $box->top + $box->height>$y) {
                $Tab[] = $box;
            }
            
        } 
        
        return $Tab;    
    
    }
        
    function noBoxeHere($y,$x, $TBox = array()) {
        if($this->debug) {
            print '<hr>noBoxeHere('.$y.','.$x.')';
            var_dump($TBox);
        }
        
        if(empty($TBox))$TBox=&$this->TBox;
        
        foreach($TBox as &$box) {
            
            if($box->left<=$x && $box->left + $box->width > $x && $box->top<=$y && $box->top + $box->height>$y ) { // il y a une boite ici
                if($this->debug){ print " y a déjà une boite là !";
                    var_dump($box);}
                return false;
            } 
            
        }
        
        if($this->debug) print "Rien ici !";
        return true;
        
    }   
        
    function isLargeEnougthEmptyPlace($y,$x, $h, $w, &$y_first_block_not_enougth_large) {
        if($this->debug) {print "<br />
        isLargeEnougthEmptyPlace($y,$x, $h, $w);";}
        
        $y_before = $y;
        $y_after = false;
        $x_before = 0;
        $x_after = $this->width;

        foreach($this->TBox as &$box) {
            
            if( $box->left + $box->width > $x && $box->left<=$x ) {
            	//var_dump($box, '<hr>');
                // boite au dessus ou au dessous ?
                if($box->top + $box->height<=$y && $box->top + $box->height>$y_before){
                    $y_before = $box->top + $box->height;
                }
                else if($box->top >= $y && ($box->top < $y_after || $y_after ===  false) ){
                    $y_after = $box->top;
                }
                
            }
            
            if($box->top + $box->height>$y && $box->top<=$y) {
                
                if($box->left + $box->width >= $x && $box->left < $x && $box->left + $box->width  > $x_before ){
                    $x_before = $box->left + $box->width; 
                    
                    if($this->debug){
                    print "(".($box->left + $box->width).") x_before = $x_before;";
                    var_dump($box);}
                    
                }
                else if($box->left > $x && $box->left < $x_after){                		
                	$x_after = $box->left;
					   if($this->debug){
                 		   print "({$box->left}) x_after= $x_after;";
                    		var_dump($box);}
                 
				}
            }
                        
            if(( $y_after!==false && $y_after - $y_before < $h) || $x_after - $x_before < $w) {
                if($y_first_block_not_enougth_large === false || $y_after>$y_first_block_not_enougth_large) {
                	$y_first_block_not_enougth_large = $y_after;
                }	
					
                if($this->debug) {
                    print "Pas assez grand ($y_first_block_not_enougth_large :: $y_before,$x_before => $y_after, $x_after)";
                    
                }
                   
				return false; // pas assez de place
            } 
        }
        if($this->debug) print "<br />Assez Grand($y,$x, $h, $w => $y_before, $y_after, $x_before, $x_after)";
        return true;
    }    
        
    function getNextPlace($h, $w, $fk_task_parent = 0, $y_min = 0) {
    	global $conf;
		
        if($this->debug) {
        	print "<hr><strong> getNextPlace($h, $w)";
			print '<br />'.$this->debug_info.'</strong><hr>';
		}
		
		if(!empty($conf->global->SCRUM_HEIGHT_DIVIDED_BY_RESSOURCE)) {
			$h = $h/$w; // hauteur divisé par nombre de ressource nécessaire
		}
		
		list($yParent,$xParent) = $this->getMinY($fk_task_parent);
		$yParent-=$this->nb_hour_before;
		$h+=$this->nb_hour_after;
		
		$y = max($this->top, $yParent, $y_min);
		
        $x = 0;
        
        //if(empty($this->TBox)) return array(0,0);
        
        $cpt_notFinishYet = 0;
         
        $nb_max = (count($this->TBox)+1) * 20;
        if($this->debug){var_dump($this->TBox);}
        while(true) {
            
           $TBox = $this->getBoxes($y);
           
           if($this->debug)var_dump($y, $TBox);
           $empty_place = false; 
           $less_next_y = false;
           $y_first_block_not_enougth_large = false;
		   
           for($x = 0; $x<=$this->width - $w; $x++) { // on parcours la largeur pour voir s'il y a un emplacement
           
               if($this->noBoxeHere($y,$x, $TBox)) {
                       
                  $empty_place = true;
                  if($this->isLargeEnougthEmptyPlace($y,$x, $h, $w, $y_first_block_not_enougth_large)) {
                        if($this->debug) print '...trouvé ('.$y.','.$x.') !<br />';    
                      return array($x,$y, $h); 
                      
                  }
                  
               }
               
           } 
           
		   //if(!$empty_place) $this->top = $y;
		    
           foreach($TBox as &$box) {
               if($less_next_y === false || $less_next_y>$box->top + $box->height)$less_next_y=$box->top + $box->height;
           } 
           
		   if($y_first_block_not_enougth_large === false && $less_next_y === false) $y++;
		   elseif($y_first_block_not_enougth_large === false) $y = $less_next_y;
		   elseif($less_next_y === false) $y = $y_first_block_not_enougth_large ;
		   else $y = min($y_first_block_not_enougth_large, $less_next_y); 
		   
		   /*
           if($less_next_y===false || $less_next_y == $y) {
               $y = $y + 1 ;
           }
           else{
               $y = $less_next_y;
           }*/
          
           if($this->debug) print '<br />$less_next_y : '.$less_next_y.'/'.$y.'/'.$y_first_block_not_enougth_large.'<br />';
           
           $cpt_notFinishYet++;
           if($cpt_notFinishYet>$nb_max) {
               if($this->debug) exit('infini');
			   return array(-0.5,99, $h);
           }
           
        }
        
    }
    
	static function setTaskWS(&$TIdTask, $taskid,$fk_workstation , $lvl = 0) {
	  global $db,$conf;
	
		  if($lvl>50) return array( );

		  $resultset = $db->query("SELECT t.fk_projet as fk_project, t.grid_col,t.grid_row,t.grid_height,tex.fk_workstation
			FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object = t.rowid)
			WHERE t.rowid=".$taskid."");
			
		  $task = $db->fetch_object($resultset);
	
		  $sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_extrafields SET
	            fk_workstation=".(int)$fk_workstation."
	        WHERE fk_object = ".(int)$taskid;
	        $res = $db->query($sql);
/*var_dump($db->affected_rows($res),$db);exit;
		  if($db->affected_rows($res) == 0) {
			$db->query("INSERT INTO ".MAIN_DB_PREFIX."projet_task_extrafields (fk_object, fk_workstation) VALUES (".$taskid.",".$fk_workstation.")");
var_dump($db);TODO ne détecte pas que l'extrafield n'est pas inséré pour les anciennes tâche pré-ordo 
		  }*/

		  $TIdTask[]=$taskid;
		  
		  if(!empty($conf->global->SCRUM_SNAP_MODE) && $conf->global->SCRUM_SNAP_MODE == 'SAME_PROJECT_AFTER') {
		  	$task->grid_row = round($task->grid_row,5);
			$task->grid_height = round($task->grid_height,5);
			  
			//var_dump($task->grid_row,$task->grid_row + $task->grid_height);
				$res = $db->query("SELECT t.rowid FROM ".MAIN_DB_PREFIX."projet_task t 
						LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object = t.rowid)
						WHERE t.fk_projet=".$task->fk_project." AND tex.fk_workstation= ".$task->fk_workstation." 
						AND t.grid_row>=".($task->grid_row-0.001)." AND t.grid_row<=".($task->grid_row + $task->grid_height + 0.001));
						
				while($obj = $db->fetch_object($res)) {
				//	var_dump($obj->rowid,$TIdTask);
					if(!in_array($obj->rowid, $TIdTask)) {
						
						$lvl++;
						
						self::setTaskWS($TIdTask,$obj->rowid, $fk_workstation, $lvl);
						//exit;
					}	
				}
				
		  }
		
		  
	}
	
}
