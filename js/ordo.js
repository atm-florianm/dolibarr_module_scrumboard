TOrdonnancement = function() {
    
    this.TWorkstation = [];
    
    var TVelocity = [];
    var width_column = 200;
    var height_day = 50;
    var swap_time = 0.08; /* 5 minute */
    var nb_hour_per_day = 7;
    
    this.init = function(w_column, h_day,sw_time) {
        /* initialise l'ordo sur la base de TWorkstation */
       
       width_column = w_column;
       height_day = h_day;
       swap_time = sw_time;
       
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
			
				addTask(task);
				
            });

			$('*.classfortooltip').tipTip({maxWidth: "600px", edgeOffset: 10, delay: 50, fadeIn: 50, fadeOut: 50});
			
			$('.connectedSortable>li').draggable({ 
				snap: true
				,containment: "table#scrum td#tasks table"
				,handle: "header"
				,snapTolerance: 30
				, distance: 10
				,drag:function(event, ui) {
					
					$(this).css({
						border:'10px solid grey'
						/*,'box-shadow': '1px 5px 5px #000'*/
						,'z-index' : '999'
					});
				}
				,stop:function(event, ui) {
					/*sortTask($(this).attr('ordo-ws-id'));*/
					
					$(this).css({
						border:'1px solid black'
						,'box-shadow': 'none'
						
					});
				}
			 });
			
			$('ul.droppable').droppable({
				drop:function(event,ui) {
					
					item = ui.draggable;
					
					taskid = $(item).attr('task-id');
					wsid = $(this).attr('ws-id');
					old_wsid = $(item).attr('ordo-ws-id');
					
					if($(this).attr('ws-nb-ressource')< $(item).attr('ordo-needed-ressource')) {
						alert("Il n'y a pas assez de ressource sur ce poste pour poser cette tâche.");
						
						return false;
					}
					
					/*$(item).find('header').css('background', 'lightblue');*/
					$(item).addClass('loading');
					
					$(item).attr('ordo-ws-id', $(this).attr('ws-id'));
					$(item).appendTo($(this));
					$(item).css('left',0);
					
					$.ajax({
						url : "./script/interface.php"
						,data: {
							json:1
							,put : 'ws'
							,taskid:taskid
							,fk_workstation:$(this).attr('ws-id')
							
						}
						,dataType: 'json'
					}).done(function() {
						sortTask(wsid);
						if(wsid!=old_wsid)order(old_wsid);	
					});
						
					
					
					
				}
			});
			
			order();
		}); 
       
    };
    
    var sortTask = function(wsid, notReOrderAfter) {
    	var TTaskID=[];
		$('ul li[ordo-ws-id='+wsid+']').each(function(i,item){
			t = parseInt( $(item).css('top') ) / (height_day / nb_hour_per_day);
			TTaskID.push( $(item).attr('task-id')+'-'+t);
		});
			
		$.ajax({
			url : "./script/interface.php"
			,data: {
				json:1
				,put : 'sort-task-ws'
				,TTaskID : TTaskID
				
			}
			,dataType: 'json'
		}).done(function() {
			if(!notReOrderAfter) {
				order(wsid, $('ul[ws-id='+wsid+']').attr('ws-nb-ressource'));	
			}
			
		});
    };
    
    var addTask = function(task) {
        $item = $('li#task-blank');
				
		$item.attr('task-id', task.id);
		
		$item.find('[rel=label]').html(task.label).attr("title", task.long_description);
		$item.find('[rel=ref]').html(task.ref).attr("href", http+'/projet/tasks/task.php?id='+task.id+'&withproject=1');
		$item.find('[rel=project]').html(task.project.title);

		var duration = task.planned_workload;
		var height = 1;
		
		if(duration>0) {
			height = duration * (1- (task.progress / 100)) / 3600;
		}
		
		if(height<1) height = 1;
	
		date=new Date(task.time_date_end * 1000);
		$item.find('[rel=time-end]').html(date.toLocaleDateString());
	
		$item.find('header').html(task.project.title+' '+(Math.round(duration / 3600 *100)/100)+'h à '+task.progress+'%');
	   
	    $ul = $('#list-task-'+task.fk_workstation); 	
	   
	    $ul.append('<li task-id="'+task.id+'" id="task-'+task.id+'" class="draggable" >'+$item.html()+'</li>');
	   
		/*$('li[task-id='+task.id+'] select[name=fk_workstation]').val(task.fk_workstation);*/
		$li = $('li[task-id='+task.id+']');
		$li.css('margin-bottom', Math.round( swap_time / nb_hour_per_day * height_day ));
		$li.css('width', Math.round( (width_column*task.needed_ressource)-2 ));
		
		$li.css('height', Math.round( height_day/TVelocity[task.fk_workstation]*(height/nb_hour_per_day)  ));
		
		$li.attr('ordo-nb-hour', height);
		$li.attr('ordo-needed-ressource',task.needed_ressource); 
		$li.attr('ordo-col',task.grid_col); 
		$li.attr('ordo-row',task.grid_row); 
		$li.attr('ordo-ws-id',task.fk_workstation);
		$li.attr('ordo-fk-project',task.fk_project); 
		
		
		/*$li.find('div[rel=time-end]').html(TVelocity[task.fk_workstation]);*/
		
		
		if(duration < task.duration_effective) {
			
			$('li[task-id='+task.id+']').css('background-color','red');
			
		}
    };
    
    this.addWorkstation = function(w) {
        this.TWorkstation.push(w);
        
        TVelocity[w.id] = w.velocity;
        
    };
    
    var order = function(wsid, nb_ressource) {
    	
    	$.ajax({
			url : "./script/interface.php"
			,data: {
				json:1
				,get : 'tasks-ordo'
				,status : 'inprogress|todo'
				,gridMode : 1 
				,fk_workstation:wsid
				,nb_ressource:nb_ressource
			}
			,dataType: 'json'
		})
		.done(function (tasks) {
			/*console.log(tasks);*/
			var nb_tasks = tasks.length;
			
			$.each(tasks, function(i, task) {
			
				coef_time = height_day / nb_hour_per_day;
			
				task_top = coef_time * task.grid_row/* / TVelocity[task.fk_workstation]*/; // vélocité déjà dans le top 
			
				$li = $('li[task-id='+task.id+']');
				wsid = $li.attr('ordo-ws-id');
				$li.css('position','absolute');
				$li.attr('ordo-fktaskparent', task.fk_task_parent);
				
				var duration = task.planned_workload;
				var height = 1;
				if(duration>0) {
					height = Math.round( duration * (1- (task.progress / 100)) /TVelocity[task.fk_workstation]*coef_time  );
				}
			
				if(i>10) {
					 
					 $li.css({
                        	top:task_top
                        	,left:(width_column * task.grid_col)
                        	,height: height
                	 });
					 
					 if(i+1 == nb_tasks) {
					 	afterAnimationOrder();
					 }
					 
				}
				else {
					
					$li.animate({
                        	top:task_top
                        	,left:(width_column * task.grid_col)
                        	,height: height
                    }
                    ,{	
                    	complete : function() {
                    		if(i+1 == nb_tasks) {
                    			afterAnimationOrder();
                    		}
                    	}
                    	
                	});

				}	 
				$li.fadeTo(400,1);
				$li.removeClass('loading');				
    
           });
           
            	
           

		}); 
    	
    };
    
    var afterAnimationOrder=function() {
    	resizeUL();
    	/*reOrderTaskWithConstraint();*/	
        
    };
    
    var reOrderTaskWithConstraint = function() {
    	
    	TWorkstationToOrder=[];
    	
    	$('li[ordo-ws-id]').each(function(i,item) {
				var fk_task_parent = $(item).attr('ordo-fktaskparent');
				if(fk_task_parent>0) {
					
					$li = $('li[task-id='+fk_task_parent+']');
					if($li.length>0) {
						
						top1 = parseFloat($(item).css('top'));
						top2 = parseFloat( $li.css('top') )+parseFloat($li.css('height'));
						
						if(top1<top2) {
							$(item).css({
								top:top2
							});
							
							TWorkstationToOrder[$(item).attr('ordo-ws-id')]= 1;
						}
						
					}
					
				}
    	});
    	
    	for(wsid in TWorkstationToOrder) {
    		sortTask(wsid,true);	
    	}
    }; 
    
    var resizeUL = function() {
    	var max_height=0;
    	
    	var TProject=[];
    	
    	$('li[task-id]').each(function(i,item) {
    		$li = $(item);
    		
    		var topLi = parseInt($li.css('top') ) ;
    		var h = topLi + parseInt($li.css('height'));
    		
    		if(max_height<h) {
				max_height=h+200;
			}
			
			if($li.attr('ordo-ws-id')>0) {
					
				if(TProject[$li.attr("ordo-fk-project")]==null) {
					TProject[$li.attr("ordo-fk-project")]={
						name:''
						,tasks:[]
						,end:0
						,start:9999999999
					};
				}
				
				TProject[$li.attr("ordo-fk-project")].name = $li.find('[rel=project]').html();
				TProject[$li.attr("ordo-fk-project")].tasks.push($li.find('[rel=task-link]').html());
				if(h>TProject[$li.attr("ordo-fk-project")].end) TProject[$li.attr("ordo-fk-project")].end = h;
				if(topLi<TProject[$li.attr("ordo-fk-project")].start) TProject[$li.attr("ordo-fk-project")].start = topLi;
				
			}
			
			
    	});
    	
    	$('ul.needToResize').css('height', max_height);

		$('.day_delim').remove();
		
		date=new Date();
		
		var TJour = new Array( "Dimanche", "Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi" );
		var TDayOff = new Array( 0,6 );
		
		for(i=0;i<max_height;i+=height_day) {
				
			$('#list-task-0').append('<div style="height:'+(height_day-1)+'px; border-bottom:1px solid black; text-align:right;" class="day_delim">'+TJour[date.getDay()]+' '+date.toLocaleDateString()+'</div>');
		
			date.setDate(date.getDate() + 1);
			while($.inArray(date.getDay(),TDayOff)>-1 ) {
				date.setDate(date.getDate() + 1);
			}
		}	

		$('#list-projects li').remove();
		for(idProject in TProject) {

			project = TProject[idProject];
			$('#list-projects').append('<li id="project-'+idProject+'" class="project start" style="text-align:left; position:absolute; padding:10px; top:'+project.start+'px"><a href="javascript:ToggleProject('+idProject+')">'+project.name+'</a></li>');	
			$('#list-projects').append('<li class="project" style="text-align:left; position:absolute; padding:10px; top:'+project.end+'px"><a href="javascript:ToggleProject('+idProject+')">'+project.name+'</a></li>');	

		}
    	
    };
    
};

TWorkstation = function() {
    
    this.nb_ressource = 1;
    this.velocity = 1;
    this.id = 'idws';
    
};

ToggleProject = function(fk_project) {
	
	if($('#project-'+fk_project).hasClass('justMe')) {
		$('#project-'+fk_project).removeClass('justMe');
		
		$('li[task-id]').each(function(i,item) {
	    	$li = $(item);
	    	$li.fadeTo(400,1);
	 	});
	 	
	}
	else{
		$('#project-'+fk_project).addClass('justMe');
		
		$('li[task-id][ordo-fk-project!='+fk_project+']').each(function(i,item) {
	    	$li = $(item);
	    	$li.fadeTo(400,.2);
	 	});
		
	}
};
