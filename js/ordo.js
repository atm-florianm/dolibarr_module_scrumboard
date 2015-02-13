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
					$(item).find('header').css('background', 'lightblue'/* 'white url(./img/where.png) center'*/);
					
					taskid = $(item).attr('task-id');
					wsid = $(this).attr('ws-id');
					old_wsid = $(item).attr('ordo-ws-id');
					
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
    
    var sortTask = function(wsid) {
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
			order(wsid, $('ul[ws-id='+wsid+']').attr('ws-nb-ressource'));
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
			//duration-=task.duration_effective;
			height = duration / 3600 ;
		}

		
		
		if(height<1) height = 1;
	
		date=new Date(task.time_date_end * 1000);
		$item.find('[rel=time-end]').html(date.toLocaleDateString());
	
		$item.find('header').html(( '('+task.id+') '+ Math.round(height*100)/100)+'h');
	   
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
			$.each(tasks, function(i, task) {
			
				coef_time = height_day / nb_hour_per_day;
			
				task_top = coef_time * task.grid_row / TVelocity[task.fk_workstation];
			
				$li = $('li[task-id='+task.id+']');
				$li.css('position','absolute');
				
				
				var duration = task.planned_workload;
				var height = 1;
				if(duration>0) {
					height = Math.round( duration/TVelocity[task.fk_workstation]*coef_time  );
				}
				 
				$li.animate({
					top:task_top
					,left:(width_column * task.grid_col)
					,height: height
				}, 'fast','', resizeUL);
				
				$li.find('header').css({
					background:'#d9ffd2'
				});
				
				$li.find('span[rel=project]').html(task.grid_col+','+task.grid_row);
				 			
			/*	width_column = 200;
			    var height_day = 50;
			    var swap_time = 0.08;
			    var nb_hour_per_day*/
    
            });

			

		}); 
    	
    };
    
    var resizeUL = function() {
    	var max_height=0;
    	
    	$('li[task-id]').each(function(i,item) {
    		$li = $(item);
    		
    		h = parseInt($li.css('top') )+ parseInt($li.css('height'));
    		
    		if(max_height<h) {
				max_height=h+200;
			
			}
    	});
    	
    	$('ul[ws-id]').css('height', max_height);
    	
    };
    
};

TWorkstation = function() {
    
    this.nb_ressource = 1;
    this.velocity = 1;
    this.id = 'idws';
    
};
