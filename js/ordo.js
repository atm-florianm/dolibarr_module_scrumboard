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
			
		}); 
       
       
        $('.connectedSortable').sortable({
			connectWith: ".connectedSortable"
			,placeholder: "ui-state-highlight"
			,handle: "header"
			,receive: function(event, ui) { 
				
				$.ajax({
					url : "./script/interface.php"
					,data: {
						json:1
						,put : 'ws'
						,taskid:$(ui.item).attr('task-id')
						,fk_workstation:$(this).attr('ws-id')
						
					}
					,dataType: 'json'
				});
					
		    }
		    ,update:function(event, ui) {
		    	
		    	$.ajax({
					url : "./script/interface.php"
					,data: {
						json:1
						,put : 'sort-task-ws'
						,taskid:$(ui.item).attr('task-id')
						,TTaskID : $(this).sortable('toArray', {attribute:"task-id"})
						
					}
					,dataType: 'json'
				});
		    	
		    }
		});/*.disableSelection();*/
       
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
			height = Math.ceil( duration / 3600 );
		}

		
		
		if(height<1) height = 1;
	
		date=new Date(task.time_date_end * 1000);
		$item.find('[rel=time-end]').html(date.toLocaleDateString());
	
		$item.find('header').html(height+'h');
	   
	    $ul = $('#list-task-'+task.fk_workstation); 	
	   
	    $ul.append('<li style="width:'+(width_column*task.needed_ressource-2)+'px; height:'+(height_day*TVelocity[task.fk_workstation]*(height/nb_hour_per_day)  )+'px" task-id="'+task.id+'" id="task-'+task.id+'" ordo-needed-ressource="'+task.needed_ressource+'" ordo-col="'+task.grid_col+'" ordo-row="'+task.grid_row+'" class="draggable" >'+$item.html()+'</li>');
	   
		/*$('li[task-id='+task.id+'] select[name=fk_workstation]').val(task.fk_workstation);*/
		$li = $('li[task-id='+task.id+']');
		$li.css('margin-bottom', Math.round( swap_time / nb_hour_per_day * height_day ));
		
		if(duration < task.duration_effective) {
			
			$('li[task-id='+task.id+']').css('background-color','red');
			
		}
    };
    
    this.addWorkstation = function(w) {
        this.TWorkstation.push(w);
        
        TVelocity[w.id] = w.velocity;
        
    };
    
    this.order = function() {
    	
    	$.ajax({
			url : "./script/interface.php"
			,data: {
				json:1
				,get : 'tasks-ordo'
				,status : 'inprogress|todo'
				,gridMode : 1 
			}
			,dataType: 'json'
		})
		.done(function (tasks) {
			
			var max_height=0;
			
			$.each(tasks, function(i, task) {
			
				task_top = height_day / nb_hour_per_day * task.grid_row;
			
				$li = $('li[task-id='+task.id+']');
				$li.css('position','absolute');
				$li.css('top', task_top );
				$li.css('left', width_column * (task.grid_col-1) );
				
				if(max_height<task_top + parseInt($li.css('height'))) {
					max_height=task_top+ parseInt($li.css('height'))+200;
					$('ul[ws-id='+task.fk_workstation+']').css('height', max_height);
				}
				
				
				
			/*	width_column = 200;
			    var height_day = 50;
			    var swap_time = 0.08;
			    var nb_hour_per_day*/
    
    
            });

		}); 
    	
    };
    
};

TWorkstation = function() {
    
    this.nb_ressource = 1;
    this.velocity = 1;
    this.id = 'idws';
    
};
