<?php
	require('../config.php');
?>
function project_velocity(id_project) {
	$.ajax({
		url : "./script/interface.php"
		,data: {
			json:1
			,get : 'velocity'
			,id_project : id_project
			,async:true
		}
		,dataType: 'json'
	})
	.done(function (data) {
		
		if(data.current) {
			$('td[rel=currentVelocity]').html(data.current);
		}
		if(data.inprogress) {
			$('span[rel=velocityInProgress]').html(data.inprogress);
		}
		if(data.todo) {
			$('span[rel=velocityToDo]').html(data.todo);
		}
		if(data.velocity) {
			$('#current-velocity').val(Math.round(data.velocity / 3600 * 100) / 100);
		}
				
	}); 
	
	
}
function project_get_tasks(id_project, status) {
	$('ul[rel="'+status+'"]').empty();
	
	$.ajax({
		url : "./script/interface.php"
		,data: {
			json:1
			,get : 'tasks'
			,status : status
			,id_project : id_project
			,async:false
		}
		,dataType: 'json'
	})
	.done(function (tasks) {
		
		$.each(tasks, function(i, task) {
			var l_status = status;
		
			if(status == 'todo' && task.scrum_status =='backlog' ) {
				l_status = 'backlog';
			}
			else if(status == 'finish' && task.scrum_status =='review' ) {
				l_status = 'review';
			}
			
			if($('tr[story-k='+task.story_k+']').length>0) {
				$ul = $('tr[story-k='+task.story_k+']').find('ul[rel="'+l_status+'"]');
			}
			else{
				$ul = $('tr[default-k=1]').find('ul[rel="'+l_status+'"]');
			}
		
			project_draw_task(id_project, task, $ul);
		});
				
	}); 
}
function project_create_task(id_project) {
	$.ajax({
		url : "./script/interface.php"
		,data: {
			json:1
			,put : 'task'
			,id_project : id_project
			,status:'idea'
		}
		,dataType: 'json'
	})
	.done(function (task) {
	
		<?php 
					if(!empty($conf->global->SCRUM_ADD_BACKLOG_REVIEW_COLUMN)) {
						echo '$ul = $(\'tr[default-k=1]\').find(\'ul[rel=backlog]\')';
					}
					else{
						
						echo '$ul = $(\'tr[default-k=1]\').find(\'ul[rel=todo]\')';
					}
		?>
		
		
		project_draw_task(id_project, task, $ul);
		project_develop_task(task.id);
	}); 
	
}
function project_draw_task(id_project, task, ul) {
	$('#task-blank').clone().attr('id', 'task-'+task.id).appendTo(ul);
	project_refresh_task(id_project, task);
}
function project_refresh_task(id_project, task) {
	
	$item = $('#task-'+task.id);
	
	
	$item.attr('task-id', task.id);
	
	$item.removeClass('idea todo inprogress finish backlog review');
	$item.addClass(task.status);
	
	var progress= Math.round(task.progress / 5) * 5 ; // round 5
	$item.find('[rel=progress]').val( progress ).attr('task-id', task.id).off( "change").on("change", function() {
			var id_projet = $('#scrum').attr('id_projet');
			var id_task = $(this).attr('task-id');		
			task=project_get_task(id_projet, id_task);
			task.progress = parseInt($(this).val());
			task.status = 'inprogress';
			task.story_k = $(this).closest('ul').attr('story-k');
			task.scrum_status = $(this).closest('ul').attr('rel');
			
			project_save_task(id_project, task);
		
		
	});
	$item.find('[rel=label]').html(task.label).attr("title", task.long_description).tipTip({maxWidth: "600px", edgeOffset: 10, delay: 50, fadeIn: 50, fadeOut: 50});
	$item.find('[rel=ref]').html(task.ref).attr("href", '<?php echo dol_buildpath('/projet/tasks/task.php?withproject=1&id=',1) ?>'+task.id);
	
	$item.find('[rel=time]').html(task.aff_time + '<br />' + task.aff_planned_workload).attr('task-id', task.id).off().on("click", function() {
		pop_time( $('#scrum').attr('id_projet'), $(this).attr('task-id'));
	});

	var percent_progress = Math.round(task.duration_effective / task.planned_workload * 100);
	if(percent_progress > 100) {
		$item.find('div.progressbar').css('background-color', '#dd0000');
		$item.find('div.progressbar').css('width', '100%');
		$item.find('div.progressbar').css('opacity', '1');
		$item.find('div.progressbaruser').css('height', '7px');	
	
	}
	else if(percent_progress > progress) {
		$item.find('div.progressbar').css('background-color', 'orange');
		$item.find('div.progressbar').css('width', percent_progress+'%');
		$item.find('div.progressbar').css('opacity', '1');
		$item.find('div.progressbaruser').css('height', '7px');	

	}
	else {
		$item.find('div.progressbar').css('width', percent_progress+'%');	
	
	}
	

	$item.find('div.progressbaruser').css('width', progress+'%');	
	
	if(progress<100 && (task.scrum_status=='todo' || task.scrum_status=='inprogress' ) ) {
		
		var t = new Date().getTime() /1000;
		
		if( task.time_date_end>0 && task.time_date_end<t) {
			$item.css('background-color','red');
		}	
		else if(task.time_date_delivery>0 && task.time_date_delivery>task.time_date_end) {
			$item.css('background-color','orange');
		}	
		
	}

	
}
function project_get_task(id_project, id_task) {
	var taskReturn="";
	$.ajax({
		url : "./script/interface.php"
		,data: {
			json:1
			,get : 'task'
			,id : id_task
			,id_project : id_project
		}
		,dataType: 'json'
		,async:false
	})
	.done(function (lTask) {
		//alert(lTask.name);
		taskReturn = lTask;
	}); 
	
	return taskReturn;
}
function project_init_change_type(id_project) {
	
    $('.task-list').sortable( {
    	connectWith: ".task-list"
    	, placeholder: "ui-state-highlight"
    	,receive: function( event, ui ) {
			task=project_get_task(id_project, ui.item.attr('task-id'));
			task.status = $(this).attr('rel');
			task.story_k = $(this).closest('ul').attr('story-k');
			task.scrum_status = $(this).closest('ul').attr('rel');
			
			$('#task-'+task.id).css('top','');
	        $('#task-'+task.id).css('left','');	
			$('#list-task-'+task.status).prepend( $('#task-'+task.id) );	
			console.log('#task-'+task.id+' --> '+'#list-task-'+task.status);	
			
			if(task.scrum_status=='backlog') task.status = 'todo';
			else if(task.scrum_status=='review') task.status = 'finish';
			
			project_save_task(id_project, task);
					        
	  }  
	  ,update:function(event,ui) {
	  	var sortedIDs = $( this ).sortable( "toArray" );
	  	
	  	var TTaskID=[];
	  	$.each(sortedIDs, function(i, id) {
	  		
	  		taskid = $('#'+id).attr('task-id');
	  		TTaskID.push( taskid );
	  	});
	  		
	  	$.ajax({
			url : "./script/interface.php"
			,data: {
				json:1
				,put : 'sort-task'
				,TTaskID : TTaskID
			}
			,dataType: 'json'
		});
	  	
	  }
    });

    
    
}
function project_getsave_task(id_project, id_task) {
	
	task = project_get_task(id_project, id_task);
	$item = $('#task-'+task.id);
	
	task.name = $item.find('[rel=name]').val();
	task.status = $item.find('[rel=status]').val();
	task.type = $item.find('[rel=type]').val();
	task.point = $item.find('[rel=point]').val();
	task.description = $item.find('[rel=description]').val();
	task.story_k = $item.closest('ul').attr('story-k');
	task.scrum_status = $item.closest('ul').attr('rel');
	
	if(task.scrum_status=='backlog') task.status = 'todo';
	else if(task.scrum_status=='review') task.status = 'finish';
	
	project_save_task(id_project, task);
}
function project_save_task(id_project, task) {
	$('#task-'+task.id).css({ opacity:.5 });
	$.ajax({
		url : "./script/interface.php"
		,data: {
			json:1
			,put : 'task'
			,id : task.id
			,status : task.status
			,id_project : id_project
			,label : task.label
			,progress : task.progress
			,story_k : task.story_k
			,scrum_status : task.scrum_status
		}
		,dataType: 'json'
		,type:'POST'
	})
	.done(function (task) {
		project_refresh_task(id_project, task);
		project_velocity(id_project);				
		$('#task-'+task.id).css({ opacity:1 });
	}); 
	
}
function project_develop_task(id_task) {
	$('#task-'+id_task+' div.view').toggle();
}
function project_loadTasks(id_projet) {
	
	project_get_tasks(id_projet ,  'todo');
	project_get_tasks(id_projet ,  'inprogress');
	project_get_tasks(id_projet ,  'finish');
	
}
function create_task(id_projet) {
	
	if($('#dialog-create-task').length==0) {
		$('body').append('<div id="dialog-create-task"></div>');
	}
	var url ="<?php echo  dol_buildpath('/projet/tasks.php?action=create&id=',1) ?>"+id_projet
		
	$('#dialog-create-task').load(url+" div.fiche form",function() {
		
		$('#dialog-create-task input[name=cancel]').remove();
		$('#dialog-create-task form').submit(function() {
			
			$.post($(this).attr('action'), $(this).serialize(), function() {
				project_loadTasks(id_projet);
			});
		
			$('#dialog-create-task').dialog('close');			
			
			return false;
	
			
		});
		
		$(this).dialog({
			title: "<?php echo $langs->trans('AddTask') ?>"
			,width:800
			,modal:true
		});
		
	});
}
		
function pop_time(id_project, id_task) {
	$("#saisie")
				.load('<?php echo dol_buildpath('/projet/tasks/time.php',2) ?>?id='+id_task+' div.fiche form'
				,function() {
					$('textarea[name=timespent_note]').attr('cols',25);
					
					$('#saisie form').submit(function() {
						
						$.post( $(this).attr('action')
							, {
								token : $(this).find('input[name=token]').val()
								,action : 'addtimespent'
								,id : $(this).find('input[name=id]').val()
								,withproject : 0
								,time : $(this).find('input[name=time]').val()
								,timeday : $(this).find('input[name=timeday]').val()
								,timemonth : $(this).find('input[name=timemonth]').val()
								,timeyear : $(this).find('input[name=timeyear]').val()
								
								<?php if((float) DOL_VERSION > 3.6) {
									?>
									,progress : $(this).find('select[name=progress]').val()
									<?php
								}
								?>
								
								,userid : $(this).find('[name=userid]').val()
								,timespent_note : $(this).find('textarea[name=timespent_note]').val()
								,timespent_durationmin : $(this).find('[name=timespent_durationmin]').val()
								,timespent_durationhour : $(this).find('[name=timespent_durationhour]').val()
								
							}
							
						) .done(function(data) {
							/*
							 * Récupération de l'erreur de sauvegarde du temps
							 */
							jStart = data.indexOf("$.jnotify(");
							
							if(jStart>0) {
								jStart=jStart+11;
								
								jEnd = data.indexOf('"error"', jStart) - 10; 
								message = data.substr(jStart,  jEnd - jStart).replace(/\\'/g,'\'');
								$.jnotify('<?php echo $langs->trans('TimeAdded') ?>');
							}
							else {
								$.jnotify('<?php echo $langs->trans('TimeAdded') ?>', "ok");
								project_velocity(id_project);	
							}
							
						});
						
						$("#saisie").dialog('close');
						
						
						task = project_get_task(id_project, id_task);
						task.status = 'inprogress';
						project_refresh_task(id_project, task);
	
						return false;
					
					});
				}
				)
				.dialog({
					modal:true
					,minWidth:1200
					,minHeight:200
					,title:$('li[task-id='+id_task+'] span[rel=label]').text()
				});
}

function reset_the_dates(id_project) {
	
	var velocity = parseFloat($('#current-velocity').val());
	$.ajax({
		url : "./script/interface.php"
		,data: {
			json:1
			,put : 'reset-date-task'
			,id_project : id_project
			,velocity : velocity
		}
		,dataType: 'json'
		,type:'POST'
		,async:false
	})
	.done(function (task) {
		project_loadTasks(id_project);
		project_velocity(id_project);				
	}); 
	
}

function reset_date_task(id_project) {
	$("#reset-date").dialog({
			modal:true
			,minWidth:400
			,minHeight:200
			,buttons: [ 
				{ text: "<?php echo $langs->trans('Yes'); ?>", click: function() { reset_the_dates(id_project); $( this ).dialog( "close" ); } } 
				, { text: "<?php echo $langs->trans('No'); ?>", click: function() { $( this ).dialog( "close" ); } }
			] 
	});
}

