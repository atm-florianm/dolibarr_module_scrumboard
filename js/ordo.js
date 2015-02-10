TOrdonnancement = function() {
    
    this.TWorkstation = [];
    
    this.init = function() {
        /* initialise l'ordo sur la base de TWorkstation */
       
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
				
				alert($(ui.item).attr('task-id') );
				
				$.ajax({
					url : "./script/interface.php"
					,data: {
						json:1
						,put : 'coord'
						
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
	   
	    $ul.append('<li style="width:'+(w_column*task.needed_ressource-2)+'px" task-id="'+task.id+'" ordo-needed-ressource="'+task.needed_ressource+'" ordo-col="'+task.grid_col+'" ordo-row="'+task.grid_row+'" class="draggable" >'+$item.html()+'</li>');
	   
		$('li[task-id='+task.id+'] select[name=fk_workstation]').val(task.fk_workstation);
		
		if(duration < task.duration_effective) {
			
			$('li[task-id='+task.id+']').css('background-color','red');
			
		}
    };
    
    this.addWorkstation = function(w) {
        this.TWorkstation.push(w);
    };
    
    this.order = function() {
    	
    	
    	
    };
    
};

TWorkstation = function() {
    
    this.nb_ressource = 1;
    this.velocity = 1;
    this.id = 'idws';
    
};
