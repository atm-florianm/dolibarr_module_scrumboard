<?php
    require('../config.php');
?>
function TOrdonnancement() {
    
    this.TWorkstation = [];
    
    var TVelocity = [];
    var width_column = 200;
    var height_day = 50;
    var swap_time = 0.08; /* 5 minute */
    var nb_hour_per_day = 7;
    
    this.init = function(w_column, h_day,sw_time) {
        /* initialise l'ordo sur la base de TWorkstation */
       
       var ordo = this;
       
       width_column = w_column;
       height_day = h_day;
       swap_time = sw_time;
       
 	   $('.fixedHeader').makeFixed({
 	   	onFixed:function(el) {
 	   		var initLeft = parseInt( $(el).attr('data-mfx-left') );
 	   		var leftScroll = parseInt($(document).scrollLeft());
 	   		var newLeft = initLeft - leftScroll;
 	   		//console.log(initLeft,leftScroll,newLeft);
 	   		$(el).css({
 	   			left : newLeft
 	   		});
 	   	}	
 	   });

       
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
			
				ordo.addTask(task);
				
            });

			$('*.classfortooltip').tipTip({maxWidth: "600px", edgeOffset: 10, delay: 50, fadeIn: 50, fadeOut: 50});
			
			$('.connectedSortable>li').draggable({ 
				snap: true
				,containment: "table#scrum td#tasks table"
				,handle: "header"
				,helper: "original"
				,snapTolerance: 30
				, distance: 10
				,drag:function(event, ui) {
					
					$(this).css({
						'box-shadow': '1px 5px 5px #000'
						,transform: 'rotate(7deg) '
						
					});
				}
				,stop:function(event, ui) {
					/*sortTask($(this).attr('ordo-ws-id'));*/
					
					$(this).css({
						'box-shadow': 'none'
						,transform:'none'

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
			,method : 'POST'
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
    
    this.addTask = function(task) {
        $li = $('li#task-blank').clone();
				
		$li.attr('task-id', task.id);
		
		$li.find('[rel=label]').html(task.label).attr("title", task.long_description);
		$li.find('[rel=divers]').html(task.divers);
		
		$li.find('[rel=ref]').html(task.ref)
				.attr("href",'<?php echo dol_buildpath('/projet/tasks/task.php',1) ?>?id='+task.id+'&withproject=1');
		$li.find('[rel=task-link]').after(' <a href="javascript:OrdoQuickEditTask('+task.id+'); "><?php echo img_picto('', 'uparrow'); ?></a>');
		$li.find('[rel=project]').html(task.project.title);

		var duration = task.planned_workload;
		var height = 1;
		
		if(task.progress == 0 && task.duration_effective>0) { // calcul de la progression si non déclarée mais temps passé
			task.progress = Math.round( task.duration_effective / task.planned_workload * 100);
		}
		
		if(duration>0) {
			height = duration * (1- (task.progress / 100)) / 3600;
		}
		
		if(height<1) height = 1;
	
		date=new Date(task.time_date_end * 1000);
		if(task.time_date_end>0) $li.find('[rel=time-end]').html(date.toLocaleDateString());
		
		$li.find('header').html(task.project.title+' <span class="duration">'+(Math.round(duration / 3600 *100)/100)+'</span>h à <span class="progress">'+task.progress+'</span>%');
	   
	    $li.css('margin-bottom', Math.round( swap_time / nb_hour_per_day * height_day ));
		$li.css('width', Math.round( (width_column*task.needed_ressource)-2 ));
		
		var ordo_height = Math.round( height_day/TVelocity[task.fk_workstation]*(height/nb_hour_per_day)  );
		
		if(isNaN(ordo_height)) ordo_height = 100;
		
		$li.css('height', ordo_height);
		
		if(task.project.array_options.options_color!=null) {
			$li.css('background-color', task.project.array_options.options_color);
			$li.attr('ordo-project-color', task.project.array_options.options_color);
		}
		
		$li.attr('ordo-project-date-end', task.project_date_end);
		$li.attr('ordo-nb-hour', height);
		$li.attr('ordo-height', ordo_height);
		$li.attr('ordo-needed-ressource',task.needed_ressource); 
		$li.attr('ordo-col',task.grid_col); 
		$li.attr('ordo-row',task.grid_row); 
		$li.attr('ordo-ws-id',task.fk_workstation);
		$li.attr('ordo-fk-project',task.fk_project);
		$li.attr('ordo-progress',task.progress);
		$li.attr('ordo-planned-workload',task.planned_workload);
		$li.attr('ordo-duration-effective',task.duration_effective);
		
		 
		$li.find('a.split').click(function() {
			OrdoSplitTask(task.id, (duration/3600) * (task.progress / 100) ,duration/3600);
		});
		$li.find('div[rel=time-rest]').html(task.aff_time_rest);
		
		$li.mouseenter(function() {
			$(this).height($(this)[0].scrollHeight);
		})
		.mouseleave(function() {
			$(this).height($(this).attr('ordo-height'));
		});
		
		
		$li.attr('id', 'task-'+task.id);
		$li.addClass('draggable');
		
		//console.log(ordo_height, task.fk_workstation,$li);
		
		$ul = $('#list-task-'+task.fk_workstation);
	    $ul.append($li); 	
		
    };
    
    this.addWorkstation = function(w) {
        this.TWorkstation.push(w);
        
        TVelocity[w.id] = w.velocity;
        
    };
    
    this.Order = function(wsid, nb_ressource) {
    	order(wsid, nb_ressource);	
    }
    
    var order = function(wsid, nb_ressource) {
    	$("div.loading-ordo").show('slide', {direction: 'left'}, 500);
    	  
    	$("a[ws-id="+wsid+"]").css("color","white");
    	
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
			//console.log(tasks);document.ordo
			var coef_time = height_day / nb_hour_per_day;
			
			
			$("a[ws-id="+wsid+"]").css("color","");
			
			if(wsid>0) text_ws = $("a[ws-id="+wsid+"]").text()+" <?php echo $langs->transnoentities('ordonnanced') ?>" ;
			else text_ws="<?php echo $langs->transnoentities('OrdonnancementEnding') ?>"; 
			
			$.jnotify(text_ws, "3000", "false" ,{ remove: function (){} } );
			
			for(fk_worstation_jo in tasks['dayOff']) {
                if(fk_worstation_jo>0 && tasks['dayOff'][fk_worstation_jo].length>0) {
                    
                    $('ul[ws-id='+fk_worstation_jo+'] > li.dayoff').remove();
                    $.each(tasks['dayOff'][fk_worstation_jo], function(i, dof) {order
                              
                             var classOff = 'dayoff';
                             if(dof.class!=null)classOff+=' '+ dof.class;
                              
                             titleOff = '';
                             if(dof.title!=null)titleOff=dof.title; 
                              
                             $('ul[ws-id='+fk_worstation_jo+']').append('<li class="'+classOff+'" jouroff="'+i+'">'+titleOff+'</li>');
                         
                             $li = $('ul[ws-id='+fk_worstation_jo+'] > li[jouroff='+i+']');
                             //console.log(dof);
                             $li.css({
                                    top:dof.top * coef_time
                                    ,position:'absolute'
                                    ,width:(width_column * dof.nb_ressource)
                                    ,height: dof.height * coef_time
                             });
                        
                       
                    });
                    
                }
			    
			}
			
			
			
			var nb_tasks = tasks['tasks'].length;
			$.each(tasks['tasks'], function(i, task) {
				//console.log(task);
				task_top = coef_time * task.grid_row/* / TVelocity[task.fk_workstation]*/; // vélocité déjà dans le top 
			
				$li = $('li[task-id='+task.id+']');
				wsid = $li.attr('ordo-ws-id');
				$li.css('position','absolute');
				$li.attr('ordo-fktaskparent', task.fk_task_parent);
				$li.find('[rel=time-projection]').html(task.time_projection);
				
				$li.find('[rel=users]').empty();
				
				$li.attr('ordo-time-estimated-end',task.time_estimated_end);
				
				if(task.TUser!=null) {
					for(idUser in task.TUser) {
						var tUser = task.TUser[idUser];
						$li.find('[rel=users]').append('<div rel="user-check-'+task.id+'-'+idUser+'"><input taskid="'+task.id+'" userid="'+idUser+'" type="checkbox" id="TUser['+task.id+']['+idUser+']" name="TUser['+task.id+']['+idUser+']" value="1" onchange="OrdoToggleContact($(this));" '+(tUser.selected==1 ? 'checked="checked"':''  )+'/> <label for="TUser['+task.id+']['+idUser+']">'+tUser.name+'</label></div>' );
						
					}
					
				}
				
				var duration = task.planned_workload;
				var height = 1;
				
				if(task.grid_height) {
					height = task.grid_height*coef_time;					
				}
				else {
					if(duration>0) {
						height = Math.round( duration * (1- (task.progress / 100)) /TVelocity[task.fk_workstation]*coef_time  );
					}
				}
				//console.log('ordo', height);
				$li.attr('ordo-height', height);
				
				$li.css('width', Math.round( (width_column*task.needed_ressource)-2 ));
				$li.attr('ordo-needed-ressource',task.needed_ressource);
				
				$li.find('header span.progress').html(task.progress); 
				$li.attr('ordo-progress', task.progress);
				
				$li.find('[rel=label]').html(task.label);
				
				if(task.date_end>0) {
					if(task.time_estimated_end > task.date_end) {
						$('li[task-id='+task.id+']').addClass('taskLate');
					}
					else if(task.time_estimated_end > task.date_end - 86400) {
						$('li[task-id='+task.id+']').addClass('taskMaybeLate');
					}
					
				}
				
				current_position = $li.position();
				if(current_position && (current_position.top!=task_top || current_position.left!=width_column * task.grid_col || $li.height()!=height) ) {
					//console.log('animate',i, current_position, task_top, width_column * task.grid_col, $li.height(),height);
					$li.animate({
                        	top:task_top
                        	,left:(width_column * task.grid_col)
                        	,height: height
                    }
                    ,{	
                    	complete : function() {
                    		if(i+1 == nb_tasks || i == 0) {
                    			afterAnimationOrder();
                    		}
                    	}
                    	
                	});
					
				}
				
				$li.removeClass('loading');				
    
           });
           
            	
           $("div.loading-ordo").hide('slide', {direction: 'left'}, 500);

		}); 
    	
    };
    
    var afterAnimationOrder=function() {
    	resizeUL();
    	ToggleProject(0,true);
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
				max_height=h+1000;
			}
			
			if($li.attr('ordo-ws-id')>0) {
				var fk_project = $li.attr("ordo-fk-project");
				if(TProject[fk_project]==null) {
					TProject[fk_project]={
						name:''
						,tasks:[]
						,end:0
						,start:9999999999
						,hasLateTask:0
						,hasMaybeLateTask:0
						,planned_workload:0
						,duration_effective:0
						,progress : 0
					};
				}
				
				TProject[fk_project].name = $li.find('[rel=project]').html();
				TProject[fk_project].tasks.push($li.find('[rel=task-link]').html());

				TProject[fk_project].planned_workload+=parseInt($li.attr('ordo-planned-workload'));
				TProject[fk_project].duration_effective+=parseInt($li.attr('ordo-duration-effective'));	
				TProject[fk_project].progress = Math.round( TProject[fk_project].duration_effective / TProject[fk_project].planned_workload * 100 );

				TProject[fk_project].color = $li.attr('ordo-project-color');

				if($li.attr('ordo-project-date-end')>0) {
					TProject[fk_project].hasLateTask = TProject[fk_project].hasLateTask | ($li.attr('ordo-project-date-end')<$li.attr('ordo-time-estimated-end') ) ;
					TProject[fk_project].hasMaybeLateTask = TProject[fk_project].hasMaybeLateTask | ($li.attr('ordo-project-date-end') - 86400<$li.attr('ordo-time-estimated-end') ) ;
					
				}
				
				TProject[fk_project].tasks.push($li.find('[rel=task-link]').html());
				
				
				if(h>TProject[fk_project].end) TProject[fk_project].end = h;
				if(topLi<TProject[fk_project].start) TProject[fk_project].start = topLi;
				
			}
			
			
    	});
    	
    	$('ul.needToResize').css('height', max_height);

		$('li.dayoff').each(function(i, item) {
			if( parseInt($(item).css('top'))> max_height) {
				$(item).hide();
			}
			else{
				$(item).show();
			}
		});

		$('.day_delim').remove();
		
		date=new Date();
		
		var TJour = new Array( "Dimanche", "Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi" );
		
		for(i=0;i<max_height;i+=height_day) {
			var dayBlock = '<div style="height:'+height_day+'px; top:'+i+'px; right:0;width:'+(width_column-5)+'px; border-bottom:1px solid black; text-align:right;position:absolute;z-index:0;" class="day_delim">'+TJour[date.getDay()]+' '+date.toLocaleDateString()+'&nbsp;</div>';	
			$('#list-task-0').append(dayBlock);

			var dayBlock = '<div style="height:'+height_day+'px; top:'+i+'px; left:0;width:'+(width_column-5)+'px; border-bottom:1px solid black; text-align:left;position:absolute;z-index:0;" class="day_delim">'+TJour[date.getDay()]+' '+date.toLocaleDateString()+'</div>';	
			$('#list-projects').append(dayBlock);
		
			date.setDate(date.getDate() + 1);
			
		}

		$('#list-projects li').remove();
		$('#list-projects').css("width", TProject.length * 40);
		$('td.projects').css("width", TProject.length * 40);
		
		for(idProject in TProject) {

			project = TProject[idProject];
			
			$('#list-projects').append('<li fk-project="'+idProject+'" id="project-'+idProject+'" class="project start" style="text-align:left; position:relative; padding:10px; top:'+(project.start - 20)+'px;float:left; height:'+(project.end - project.start)+'px; width:20px;border-radius: 20px 20px 8px 8px; margin-right:5px;" onclick="ToggleProject('+idProject+')"><span style="transform: rotate(90deg);transform-origin: left top 0;display:block; white-space:nowrap; margin-left:15px;"><a href="<?php echo dol_buildpath('/projet/card.php',1) ?>?id='+idProject+'">'+project.name+'</a> '+project.progress+'%</span></li>');	
			
			
			if(project.hasLateTask) $('#list-projects li[fk-project='+idProject+']').addClass('projectLate');
			else if(project.hasMaybeLateTask) $('#list-projects li[fk-project='+idProject+']').addClass('projectMaybeLate');
			else if(project.planned_workload < project.duration_effective){
				 $('#list-projects li[fk-project='+idProject+']').addClass('projectMaybeLate');
			}
			else {
				if(project.color!=null && project.color!='') {
					$('#list-projects li[fk-project='+idProject+']').css('background', 'rgba(0, 0, 0, 0) linear-gradient(to bottom, #7cbc0a '+project.progress+'%, #666 '+(project.progress+1)+'%, #ccc '+(project.progress+2)+'%, '+project.color+' 100%) repeat scroll 0 0');
					
				}
				else{
					$('#list-projects li[fk-project='+idProject+']').css('background', 'rgba(0, 0, 0, 0) linear-gradient(to bottom, #7cbc0a '+project.progress+'%, #666 '+(project.progress+1)+'%, #ccc '+(project.progress+2)+'%, #ccc 100%) repeat scroll 0 0');
					
				}		
				
			}

		}
		
		wtable=0;
		$("#theGrid div").each(function() {
		    wtable+=parseInt($(this).css('width'))+5;
		});
    	$("#theGrid").css("min-width", wtable);
    	
    };
    
};

TWorkstation = function() {
    
    this.nb_ressource = 1;
    this.velocity = 1;
    this.id = 'idws';
    
};

toggleWorkStation = function (fk_ws, justMe) {
	
	if(justMe!=null && justMe == true) {
	    $('div[id^="columm-ws-"]').hide();
	    $('#columm-ws-'+fk_ws).show();
        $('span[id^="columm-header1"]').addClass('hiddenWS');
        $('#columm-header1-'+fk_ws).removeClass('hiddenWS');
	}
	else if($('#columm-ws-'+fk_ws).is(':visible')) {
		$('#columm-wsordoQuickEditTask-'+fk_ws).hide();
		$('#columm-header1-'+fk_ws).addClass('hiddenWS');
	}
	else{
		$('#columm-ws-'+fk_ws).show();
		$('#columm-header1-'+fk_ws).removeClass('hiddenWS');
	}
	
	$('div[id^="columm-ws-"]').each(function(i, item) {
	    
	    var id = $(item).find('ul.task-list').attr('ws-id');
	    var visible = ($(item).css('display') != 'none' ) ? 1 : 0;
	    document.cookie="WSTogle["+id+"]="+visible;
	    
	});
	
	
	
};

printWorkStation = function (fk_ws) {
	
	$('#printedFrame,#printedTask').remove();
	$('body').append('<ul id="printedTask"></ul>');
	
	$('ul[ws-id='+fk_ws+']>li[task-id]').each(function (i,item) {
		
		$(item).clone()
			.removeAttr("style")
			.removeAttr("id")
			.removeClass("draggable ui-draggable")
			.appendTo("#printedTask");
	});
	
	$("#printedTask").find(".button,.picto").remove();
	
	$("#printedTask").find("[rel=users]>div>input").not(":checked").each(function(i,item) {
		
		var taskid = $(item).attr('taskid');
		var userid = $(item).attr('userid');
		$('#printedTask div[rel="user-check-'+taskid+'-'+userid+'"]').remove();
		
	});
	
	$('<iframe id="printedFrame" name="printedFrame">').appendTo("body").ready(function(){
	    setTimeout(function(){
	    	
	    	$('#printedFrame').contents().find('body').append('<link rel="stylesheet" type="text/css" title="default" href="<?php echo dol_buildpath('/scrumboard/css/scrum.css',2) ?>">');
	    	$('#printedFrame').contents().find('body').append($("#printedTask"));
	        window.frames["printedFrame"].focus();
			window.frames["printedFrame"].print();
	    },50);
	    
	});
	
	
	
};

ToggleProject = function(fk_project, showAll) {
	
	$('li[task-id]').each(function(i,item) {
    	$li = $(item);
    	$li.fadeTo(400,1);
 	});
	 	
	if(fk_project==0) {
		$('li.project').removeClass('justMe');
	} 	
	else if($('#project-'+fk_project).hasClass('justMe') || showAll == true) {
		$('#project-'+fk_project).removeClass('justMe');
	}
	else{
		$('#project-'+fk_project).addClass('justMe');
		
		$('li[task-id][ordo-fk-project!='+fk_project+']').each(function(i,item) {
	    	$li = $(item);
	    	$li.fadeTo(400,.2);
	 	});
		
	}
};

OrdoToggleContact = function($check) {
	
	if($check.is(':checked')) {
		
		$check.attr('disabled', 'disabled');
		
		$.ajax({
				url : "./script/interface.php"
				,data: {
					json:1
					,put : 'set-user-task'
					,taskid : $check.attr('taskid')
					,userid : $check.attr('userid')
				}
				,dataType: 'json'
		}).done(function() {
			$check.removeAttr('disabled');
		});
		
		
	}
	else {
		$check.attr('disabled', 'disabled');
		
		$.ajax({
				url : "./script/interface.php"
				,data: {
					json:1
					,put : 'remove-user-task'
					,taskid : $check.attr('taskid')
					,userid : $check.attr('userid')
				}
				,dataType: 'json'
		}).done(function() {
			$check.removeAttr('disabled');
		});
		
	}
	
	
};

OrdoSplitTask = function(taskid, min, max) {
	console.log(taskid, min, max);
	
	$('#splitSlider').remove();
    $('body').append('<div id="splitSlider"><div><label></label></div><div style="padding:20px;position:relative;" ><div rel="slide"></div></div></div>');
	
	$('#splitSlider').dialog({
		title:"Sélectionnez comment diviser la tâche"
		,modal:true
		,draggable: false
		,resizable: false
		,buttons:[
            {
              text: 'Split',
              click: function() {
                  
                $.ajax({
                   url : "script/interface.php"
                   ,data:{
                       'put':'split'
                       ,'taskid':taskid
                       ,'tache1':$("#splitSlider label").attr("tache1")
                       ,'tache2':$("#splitSlider label").attr("tache2")
                       
                   } 
                }).done(function(task) {
                    document.ordo.addTask(task);
                    
                    $li = $('li#task-'+taskid);
                    document.ordo.order( $li.attr("ordo-ws-id"), $li.attr("ordo-needed-ressource")  );
                });  
                  
                $( this ).dialog( "close" );
              }
            }
          ]
	});
	
	 $( "div[rel=slide]" ).slider({
		min:min
		,max:max
		,step:0.25
		,slide:function(event,ui) {
			var val = Math.round( ui.value * 100 ) / 100;
			$("#splitSlider label").html("Reste sur tâche actuelle : "+ val +"h<br />Sur la tâche créée : "+(max - val)+"h"  );
			
			$("#splitSlider label").attr("tache1", val);
			$("#splitSlider label").attr("tache2", max - val);
		}
	});
	
};

OrdoQuickEditTask = function(fk_task) {
    	
    $li = $('li#task-'+fk_task); 
    
	pop_edit_task(fk_task,'document.ordo.Order('+$li.attr('ordo-ws-id')+','+$li.attr('ordo-needed-ressource')+')');
	
};

OrdoReorderAll = function() {
    	
    	alert('OrdoReorderAll, pas écrit ça !');
};
