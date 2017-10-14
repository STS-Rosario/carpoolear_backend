window.addEvent("domready", function(){	
	$$('.nsp_main').each(function(module){
		var id = module.getProperty('id');
		var $G = $Gavick[id];
		var arts_actual = 0;
		var list_actual = 0;
		var arts_block_width = $E('.nsp_arts', module) ? $E('.nsp_arts', module).getSize().size.x : null;
		var links_block_width = $E('.nsp_links ul', module) ? $E('.nsp_links ul', module).getSize().size.x : null;
		var arts = $ES('.nsp_art', module);
		var links = $ES('.nsp_links .list li', module);
		var arts_per_page = $G['news_column'] * $G['news_rows'];
		var pages_amount = Math.ceil(arts.length / arts_per_page);
		var links_pages_amount = Math.ceil(links.length / $G['links_amount']);
		var auto_anim = module.hasClass('autoanim');
		var hover_anim = module.hasClass('hover');
		var anim_speed = $G['animation_speed'];
		var anim_interval = $G['animation_interval'];
		var animation = true;
		
		if(arts.length > 0){
			for(var i = 0; i < pages_amount; i++){
				var div = new Element('div',{"class" : "nsp_art_page"});
				div.setStyles({ "width" : arts_block_width+"px", "float" : "left" });
				div.injectBefore(arts[0]);
			}	
			
			var j = 0;
			for(var i = 0; i < arts.length; i++) {
				if(i % arts_per_page == 0 && i != 0) { j++; }
				if(window.ie) arts[i].setStyle('width', (arts[i].getStyle('width').toInt() - 0.2) + "%");
				arts[i].injectInside($ES('.nsp_art_page',module)[j]);
				if(arts[i].hasClass('unvisible')) arts[i].removeClass('unvisible');
			}
			
			var main_scroll = new Element('div',{"class" : "nsp_art_scroll1" });
			main_scroll.setStyles({ "width" : arts_block_width + "px", "overflow" : "hidden" });
			main_scroll.innerHTML = '<div class="nsp_art_scroll2"></div>';
			main_scroll.injectBefore($E('.nsp_art_page',module));
			var long_scroll = $E('.nsp_art_scroll2',module);
			long_scroll.setStyle('width','100000px');
			$ES('.nsp_art_page',module).injectInside(long_scroll);
			var art_scroller = new Fx.Scroll(main_scroll, {duration:$G['animation_speed'], wait:false, wheelStops:false});
		}
		
		if(links.length > 0){
			for(var i = 0; i < links_pages_amount; i++){
				var ul = new Element('ul');
				ul.setStyles({ "width" : links_block_width+"px", "float" : "left" });
				ul.setProperty("class","list");
				ul.injectTop($E('.nsp_links',module));
			}
			
			var k = 0;
			for(var i = 0; i < links.length; i++) {
				if(i % $G['links_amount'] == 0 && i != 0) { k++; }
				links[i].injectInside($ES('.nsp_links ul.list',module)[k]);
				if(links[i].hasClass('unvisible')) links[i].removeClass('unvisible');
			}
			$ES('.nsp_links ul.list',module)[$ES('.nsp_links ul.list',module).length - 1].remove();
			var link_scroll = new Element('div',{"class" : "nsp_link_scroll1" });
			link_scroll.setStyles({ "width" : links_block_width + "px", "overflow" : "hidden" });
			link_scroll.innerHTML = '<div class="nsp_link_scroll2"></div>';
			link_scroll.injectTop($E('.nsp_links',module));
			var long_link_scroll = $E('.nsp_link_scroll2',module);
			long_link_scroll.setStyle('width','100000px');
			$ES('.nsp_links ul.list',module).injectInside(long_link_scroll);
			var link_scroller = new Fx.Scroll(link_scroll, {duration:$G['animation_speed'], wait:false, wheelStops:false});
		}
		
		// top interface
		nsp_art_list(0, module, 'top');
		nsp_art_list(0, module, 'bottom');
		nsp_art_counter(0, module, 'top', pages_amount);
		nsp_art_counter(0, module, 'bottom', links_pages_amount);
		
		if($E('.nsp_top_interface .pagination', module)){
			$E('.nsp_top_interface .pagination', module).getElementsBySelector('li').each(function(item,i){
				item.addEvent(hover_anim ? 'mouseenter' : 'click', function(){
					art_scroller.scrollTo(i*arts_block_width, 0);
					arts_actual = i;
					
					if(window.opera){
			 			new Fx.Style($ES('.nsp_art_scroll2',module)[0], 'margin-left', {duration:$G['animation_speed'], wait:false}).start(-1 * arts_actual * arts_block_width);
					}
					
					nsp_art_list(i, module, 'top');
					nsp_art_counter(i, module, 'top', pages_amount);
					animation = false;
					(function(){animation = true;}).delay($G['animation_interval'] * 0.8);
				});	
			});
		}
		if($E('.nsp_top_interface .prev', module)){
			$E('.nsp_top_interface .prev', module).addEvent("click", function(){
				if(arts_actual == 0) arts_actual = pages_amount - 1;
				else arts_actual--;
				art_scroller.scrollTo(arts_actual * arts_block_width, 0);
				
				if(window.opera){
			 		new Fx.Style($ES('.nsp_art_scroll2',module)[0], 'margin-left', {duration:$G['animation_speed'], wait:false}).start(-1 * arts_actual * arts_block_width);	
				}
				
				nsp_art_list(arts_actual, module, 'top');
				nsp_art_counter(arts_actual, module, 'top', pages_amount);
				animation = false;
				(function(){animation = true;}).delay($G['animation_interval'] * 0.8);
			});
		}
		
		if($E('.nsp_top_interface .next', module)){
			$E('.nsp_top_interface .next', module).addEvent("click", function(){
				if(arts_actual == pages_amount - 1) arts_actual = 0;
				else arts_actual++;
				art_scroller.scrollTo(arts_actual * arts_block_width, 0);
				
				if(window.opera){
			 		new Fx.Style($ES('.nsp_art_scroll2',module)[0], 'margin-left', {duration:$G['animation_speed'], wait:false}).start(-1 * arts_actual * arts_block_width);	
				}
				
				nsp_art_list(arts_actual, module, 'top');
				nsp_art_counter(arts_actual, module, 'top', pages_amount);
				animation = false;
				(function(){animation = true;}).delay($G['animation_interval'] * 0.8);
			});
		}
		// bottom interface
		if($E('.nsp_bottom_interface .pagination', module)){
			$E('.nsp_bottom_interface .pagination', module).getElementsBySelector('li').each(function(item,i){
				item.addEvent(hover_anim ? 'mouseenter' : 'click', function(){
					link_scroller.scrollTo(i*links_block_width, 0);
					list_actual = i;
					
					if(window.opera){
			 			new Fx.Style($ES('.nsp_link_scroll2',module)[0], 'margin-left', {duration:$G['animation_speed'], wait:false}).start(-1 * list_actual * links_block_width);	
					}
					
					nsp_art_list(i, module, 'bottom', links_pages_amount);
				});	
			});
		}
		if($E('.nsp_bottom_interface .prev', module)){
			$E('.nsp_bottom_interface .prev', module).addEvent("click", function(){
				if(list_actual == 0) list_actual = links_pages_amount - 1;
				else list_actual--;
				link_scroller.scrollTo(list_actual * links_block_width, 0);
				
				if(window.opera){
		 			new Fx.Style($ES('.nsp_link_scroll2',module)[0], 'margin-left', {duration:$G['animation_speed'], wait:false}).start(-1 * list_actual * links_block_width);	
				}
				
				nsp_art_list(list_actual, module, 'bottom', links_pages_amount);
				nsp_art_counter(list_actual, module, 'bottom', links_pages_amount);
			});
		}
		if($E('.nsp_bottom_interface .next', module)){
			$E('.nsp_bottom_interface .next', module).addEvent("click", function(){
				if(list_actual == links_pages_amount - 1) list_actual = 0;
				else list_actual++;
				link_scroller.scrollTo(list_actual * links_block_width, 0);
				
				if(window.opera){
 					new Fx.Style($ES('.nsp_link_scroll2',module)[0], 'margin-left', {duration:$G['animation_speed'], wait:false}).start(-1 * list_actual * links_block_width);	
				}
				
				nsp_art_list(list_actual, module, 'bottom', links_pages_amount);
				nsp_art_counter(list_actual, module, 'bottom', links_pages_amount);
			});
		}
		
		if(auto_anim){
			(function(){
				if($E('.nsp_top_interface .next', module)){
					if(animation) $E('.nsp_top_interface .next', module).fireEvent("click");
				}else{
					if(arts_actual == pages_amount - 1) arts_actual = 0;
					else arts_actual++;
					art_scroller.scrollTo(arts_actual * arts_block_width, 0);
					
					if(window.opera){
				 		new Fx.Style($ES('.nsp_art_scroll2',module)[0], 'margin-left', {duration:$G['animation_speed'], wait:false}).start(-1 * arts_actual * arts_block_width);	
					}
					nsp_art_list(arts_actual, module, 'top');
					nsp_art_counter(arts_actual, module, 'top', pages_amount);
				}
			}).periodical($G['animation_interval']);
		}
	});
	
	function nsp_art_list(i, module, position){
		if($E('.nsp_'+position+'_interface .pagination', module)){
			$E('.nsp_'+position+'_interface .pagination', module).getElementsBySelector('li').setProperty('class', '');
			$E('.nsp_'+position+'_interface .pagination', module).getElementsBySelector('li')[i].setProperty('class', 'active');
		}
	}
	
	function nsp_art_counter(i, module, position, num){
		if($E('.nsp_'+position+'_interface .counter', module)){
			$E('.nsp_'+position+'_interface .counter span', module).innerHTML =  (i+1) + ' / ' + num;
		}
	}
});