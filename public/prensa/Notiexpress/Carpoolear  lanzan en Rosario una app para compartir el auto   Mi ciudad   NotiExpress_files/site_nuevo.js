function getParameter(parameter){
	var url = location.href;
	var index = url.indexOf("?");
	index = url.indexOf(parameter,index) + parameter.length;
	if (url.charAt(index) == "="){
		var result = url.indexOf("&",index);
		if (result == -1){result=url.length;};
		var puesto = decodeURI(url.substring(index + 1,result))
		return puesto;
	}
} 
/////////////////////////////////////////////////////////////////////////////REDIREC MOBILE ///////////////////////////////////////////////////////////////////
if(document.cookie =! ""){
	var existCookie = 0;
	var thisCookie = document.cookie.split("; ");
	for(var k=0; k<thisCookie.length; k++){
		if(thisCookie[k].split("=")[0] == "fromMobile"){
			existCookie = 1;
		}
	}
	if(existCookie == 0){
			// http://detectmobilebrowsers.com/
			var urlRedir;
			if(location.href.indexOf('contentFront') == -1){
				urlRedir = '/static/webmovil.html?skin=webmovil';
			}
			else{
				urlRedir = location.href+'&skin=movil&force_skin=1';
			}
			
			(function(a,b){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4)))window.location=b})(navigator.userAgent||navigator.vendor||window.opera,urlRedir);
	}
}

// function redirectToMobil(){
// 	var thisCookie = document.cookie.split("; ");
// 	for(var i=0; i<thisCookie.length; i++){
// 		if(thisCookie[i].split("=")[0] == "fromMobile"){document.cookie = thisCookie[i].split("=")[0]+"="+thisCookie[i].split("=")[1]+"; expires=Thu, 01-Jan-1970 00:00:01 GMT; path=/";}
// 	}
// 	document.cookie = "fromMobile=0; path=/";
// 	location.href = "/";
// }

/////////////////////////////////////////////////////////////////////////////FIN REDIREC MOBILE ///////////////////////////////////////////////////////////////////

function showImagesNewsMediaContainer(newsCounter) {
	$('imagesNewsMediaContainer_'+newsCounter).style.display='block';
	$('videoNewsMediaContainer_'+newsCounter).style.display='none';
	$('imageSelector_'+newsCounter).className='mediaTypeSelectorOn';
	$('videoSelector_'+newsCounter).className='mediaTypeSelectorOff';
}

function showImagesNewsMediaContainerSocialesHome(newsCounter) {
	$('imagesNewsMediaContainerHome_'+newsCounter).style.display='block';
}
function showImagesNewsMediaContainerSociales(newsCounter) {
	$('imagesNewsMediaContainer_'+newsCounter).style.display='block';
}


function showVideoNewsMediaContainer(newsCounter) {
	$('videoNewsMediaContainer_'+newsCounter).style.display='block';
	$('imagesNewsMediaContainer_'+newsCounter).style.display='none';
	$('imageSelector_'+newsCounter).className='mediaTypeSelectorOff';
	$('videoSelector_'+newsCounter).className='mediaTypeSelectorOn';
}

function popUpBolsa(){
  window.open('http://cablehogar.com.ar/popups/bolsa_trabajo/', 'ventana', 'width=633,height=595,menubar=no,scrollbars=no,toolbar=no,location=no,resizable=no,top=100,left=250');
}

function loadComments(idNew, idCategory, template){
	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Updater({success:'related_comments',failure:'',exception:''}, '/index.cgi', {
						parameters: 'accion=getPlugin&pluginName=get_related_news&new_id='+ idNew +'&template='+template+'&category_id='+idCategory+'&orderby=codigo&_nocache='+rand,
						method: 'get',
						evalScripts: true,
						evalJS: true,
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {
// 							$('LoaderTicket').style.display = 'none';
// 							$('ticket').style.display = 'block';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});

}

function addComment(){
	document.getElementById('commentBlock').style.display = 'block';
	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Request('/index.cgi', {
						parameters:Form.serialize('formComentario')+'&formAlta=comentarios_rosario&related_news_id='+newsArticleId+'&_nocache='+rand,
// '/index.cgi?accion=getPlugin&pluginName=do_add_news&template=plugin_comentario.tmpl&_nocache='+rand,
						method: 'post',
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {

							alert("Su comentario ha sido cargado correctamente.");
							document.getElementById('commentBlock').style.display = 'none';
							Form.reset('formComentario');
// 							$('LoaderTicket').style.display = 'none';
// 							$('ticket').style.display = 'block';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); document.getElementById('commentBlock').style.display = 'none';}
					});
	
}

function getFormAbuso(idNew, titleNew, formAlta){
	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Updater({success:'popUpAbuso',failure:'',exception:''}, '/index.cgi', {
						parameters: 'accion=getPlugin&pluginName=get_template&related_news_id='+ idNew +'&template=form_abuso.tmpl&_nocache='+rand,
						method: 'get',
						evalScripts: true,
						evalJS: true,
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {
							var marginTop = getScrollXY()[1]+20;
							$('mainForm').style.top = marginTop+'px';
							$('blockUI').style.display = 'block';
							$('popUpAbuso').style.display = 'block';
							$('related_news_id').value = idNew;
							$('h2_new_abuso').value = titleNew+'(Abuso)';
							$('form_alta').value = formAlta;
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});

}

function reportarAbuso(){
    var frm = $('formAbuso');
    if (frm['des_new'].value != ''){
	var div = $('mainForm').parentNode;
	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Request('/index.cgi', {
						parameters:Form.serialize('formAbuso')+'&formAlta='+$('form_alta').value,
// '/index.cgi?accion=getPlugin&pluginName=do_add_news&template=plugin_comentario.tmpl&_nocache='+rand,
						method: 'post',
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {

							alert("El abuso ha sido reportado.");
							Form.reset('formComentario');
							$('blockUI').style.display = 'none';
							div.style.display = 'none';
// 							$('LoaderTicket').style.display = 'none';
// 							$('ticket').style.display = 'block';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});
    }
    else{
	alert('Debe ingresar el motivo del reporte de abuso');
    }
}

function getScrollXY() { // determina posicion scroll de pantalla
  var scrOfX = 0, scrOfY = 0;
  if( typeof( window.pageYOffset ) == 'number' ) {
    //Netscape compliant
    scrOfY = window.pageYOffset;
    scrOfX = window.pageXOffset;
  } else if( document.body && ( document.body.scrollLeft || document.body.scrollTop ) ) {
    //DOM compliant
    scrOfY = document.body.scrollTop;
    scrOfX = document.body.scrollLeft;
  } else if( document.documentElement && ( document.documentElement.scrollLeft || document.documentElement.scrollTop ) ) {
    //IE6 standards compliant mode
    scrOfY = document.documentElement.scrollTop;
    scrOfX = document.documentElement.scrollLeft;
  }
  return [scrOfX, scrOfY];
}

function activaSolapa(divId){
	imgSolapaActiva = document.getElementById(divId+'_image');
	var contentActivo = document.getElementById(divId+'Content');
	var containerSolapas = document.getElementById('ranking_container');
	var containerPosition = document.getElementById('content_ranking');
	var i;

	for (i=0;i < containerSolapas.childNodes.length;i++) {
		if ((containerSolapas.childNodes[i].id != divId) && (containerSolapas.childNodes[i].className == 'solapa') && (containerSolapas.childNodes[i].id)){
		var imgSolapaInactiva = document.getElementById(containerSolapas.childNodes[i].id+'_image');
		imgSolapaInactiva.src = '/images/'+containerSolapas.childNodes[i].id+'_inactiva2.gif';
		}
	}

	for (i=0;i < containerPosition.childNodes.length;i++) {
		if((containerPosition.childNodes[i].id != divId+'Content') && (containerPosition.childNodes[i].id)){
		containerPosition.childNodes[i].style.display = 'none';
		}
	} 

	imgSolapaActiva.src = '/images/'+divId+'_activa2.gif';
	contentActivo.style.display = 'block';
}

function activaSolapaCartelera(divId){
		imgSolapaActiva = document.getElementById(divId+'_image');
		var contentActivo = document.getElementById(divId+'Content');
		var containerSolapas = document.getElementById('ranking_container_cartelera');
		var containerPosition = document.getElementById('content_ranking_cartelera');
		var i;

		for (i=0;i < containerSolapas.childNodes.length;i++) {
		    if ((containerSolapas.childNodes[i].id != divId) && (containerSolapas.childNodes[i].className == 'solapa') && (containerSolapas.childNodes[i].id)){
			var imgSolapaInactiva = document.getElementById(containerSolapas.childNodes[i].id+'_image');
			imgSolapaInactiva.src = '/images/cartelera/'+containerSolapas.childNodes[i].id+'_inactiva.jpg';
		    }
		}
	
		for (i=0;i < containerPosition.childNodes.length;i++) {
		    if((containerPosition.childNodes[i].id != divId+'Content') && (containerPosition.childNodes[i].id)){
			containerPosition.childNodes[i].style.display = 'none';
		    }
		} 

		if(divId == 'music'){
			document.getElementById('theater_image').src = '/images/cartelera/theater_inactiva2.jpg';
		}

		imgSolapaActiva.src = '/images/cartelera/'+divId+'_activa.jpg';
		contentActivo.style.display = 'block';
	}


function activaSolapaViejo(solapaActiva){
	$(solapaActiva+'_content').style.display = 'block';
	$(solapaActiva).style.backgroundColor ='#E1EEF7';

	for (var i=0; i<solapas.length; i++){
		if (solapas[i] != solapaActiva){
			$(solapas[i]+'_content').style.display = 'none';
			$(solapas[i]).style.backgroundColor ='#EEEEEE';
		}
	}

}

function showImagesWidget(newsCounter) {
	$('imagesNewsMediaContainer_'+newsCounter).style.display='block';

	if ($('videoNewsMediaContainer_'+newsCounter)){
		$('videoNewsMediaContainer_'+newsCounter).style.display='none';
	}

	$('videos_widget_'+newsCounter).src = "/images/solapa_videos_inactiva.jpg";
	$('fotos_widget_'+newsCounter).src = "/images/solapa_fotos_activa.jpg"
}

function showVideoWidget(newsCounter) {
	$('videoNewsMediaContainer_'+newsCounter).style.display='block';
	$('imagesNewsMediaContainer_'+newsCounter).style.display='none';
	$('videos_widget_'+newsCounter).src = "/images/solapa_videos_activa.jpg";
	$('fotos_widget_'+newsCounter).src = "/images/solapa_fotos_inactiva.jpg"
}

function initNewsMediaWidget(newsMediaContainer, imageNewsCounter, videoNewsCounter,videoSelector,imageSelector, idWidget) {
	var showWidget = 1;
	if ((imageNewsCounter <= 0) && (videoNewsCounter <= 0) ){
		showWidget = 0;
	}
	else {
		if (videoNewsCounter <= 0){
			$('videos_widget_'+idWidget).src = "/images/solapa_videos_inactiva.jpg";
			$('fotos_widget_'+idWidget).src = "/images/solapa_fotos_activa.jpg"
		}
		else{
			$('videos_widget_'+idWidget).src = "/images/solapa_videos_activa.jpg";
			$('fotos_widget_'+idWidget).src = "/images/solapa_fotos_inactiva.jpg"
		}
	}

	if (showWidget == 0) {
		$(newsMediaContainer).style.display = 'none';
	}
}

function changeAudioWidget(path, idDiv) {
	var mainWindowNewsMedia = document.getElementById(idDiv);
// 	mainWindowNewsMedia.style.display = 'block';
	mainWindowNewsMedia.parentNode.style.display = 'block';
	var fo = new SWFObject("/swf/audioPlayer.swf", "player", "220", "25", "8.0.15", "#D8DCDF", true);
	fo.addParam("allowScriptAccess", "always");
	fo.addParam("wmode", "opaque");
	fo.addVariable("mediaPath", path);
	fo.addVariable("autoPlay", "no");
	fo.write(idDiv);
}

function changeVideoWidget(path, idDiv, imagePath) {
//     var mainWindowNewsMedia = document.getElementById(idDiv);
//     mainWindowNewsMedia.style.display = 'block';
	var divPlayer = $(idDiv);
	
	divPlayer.innerHTML = '<object id="playerDetail_'+idDiv+'" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" name="playerDetail_'+idDiv+'" width="310" height="267"><param name="movie" value="/mediaplayer-5.3/player.swf" /><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="flashvars" value="file='+path+'&image='+imagePath+'" /><embed type="application/x-shockwave-flash" id="detail_embed_'+idDiv+'" name="detail_embed_'+idDiv+'" src="/mediaplayer-5.3/player.swf" width="310" height="267" allowscriptaccess="always" allowfullscreen="true" flashvars="file='+path+'&image='+imagePath+'"/></object>';
    
}

function loadCommentsForo(idNew, idCategory){
	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Updater({success:'comments_foro',failure:'',exception:''}, '/index.cgi', {
						parameters: 'accion=getPlugin&pluginName=get_related_news&new_id='+ idNew +'&template=respuestas_foro.tmpl&category_id=' + idCategory + '&orderby=codigo&ordermode=desc&cantidad=100&_nocache='+rand,
						method: 'get',
						evalScripts: true,
						evalJS: true,
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {
// 							$('LoaderTicket').style.display = 'none';
// 							$('ticket').style.display = 'block';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});

}

function getPopUpResponse(idNew){
	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Updater({success:'popUpResponse',failure:'',exception:''}, '/index.cgi', {
						parameters: 'accion=getPlugin&pluginName=get_template&template=responder_foro.tmpl&_nocache='+rand,
						method: 'get',
						evalScripts: true,
						evalJS: true,
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {
							var marginTop = getScrollXY()[1]+20;
							$('mainFormResponse').style.top = marginTop+'px';
							$('blockUI').style.display = 'block';
							$('popUpResponse').style.display = 'block';
							$('related_response_id').value = idNew;
// 							$('h2_new_abuso').value = titleNew+'(Abuso)';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});

}


function addResponseForo(){
	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Request('/index.cgi', {
						parameters:Form.serialize('formRespuesta')+'&formAlta=respuestas_foro',
// '/index.cgi?accion=getPlugin&pluginName=do_add_news&template=plugin_comentario.tmpl&_nocache='+rand,
						method: 'post',
						evalScripts: true,
						evalJS: true,
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {

							alert("Su respuesta ha sido cargada correctamente.");
							closePopUpResponse();
// 							Form.reset('formComentario');
// 							$('LoaderTicket').style.display = 'none';
// 							$('ticket').style.display = 'block';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});
	
}

function closePopUpResponse(){
    $('blockUI').style.display = 'none';
    $('mainFormResponse').style.display = 'none';
}

function verificarRespuesta() {
    if (validador_respuesta.exec()) {
// 	var today = new Date();
// 	var mes = today.getMonth() + 1;
// 	$('fecha_alta_respuesta').value = today.getFullYear()+'-'+mes+'-'+today.getDate()+' '+today.getHours()+':'+today.getMinutes()+':'+today.getSeconds();	
  	addResponseForo();
    }
    else{
	    return false;
    }
}

function getCommentInfo(idNew){
    var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
    new Ajax.Updater({success:'last_response_'+idNew,failure:'',exception:''}, '/index.cgi', {
			    parameters: 'accion=getPlugin&pluginName=get_related_news&new_id='+ idNew +'&template=last_response.tmpl&destacado=no&category_id=131&orderby=codigo&ordermode=desc&cantidad=1&_nocache='+rand,
			    method: 'get',
			    evalScripts: true,
			    evalJS: true,
			    onCreate: function(){},
			    onComplete: function(transport) {},
			    onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
			});

    new Ajax.Updater({success:'counter_'+idNew,failure:'',exception:''}, '/index.cgi', {
			    parameters: 'accion=getPlugin&pluginName=get_quantity_comments&new_id='+ idNew +'&category_id=131&_nocache='+rand,
			    method: 'get',
			    evalScripts: true,
			    evalJS: true,
			    onCreate: function(){},
			    onComplete: function(transport) {},
			    onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
			});

}

function addNew(){
	var rand = Math.round(100*Math.random()); //  IE CACHE FIX
	new Ajax.Request('/index.cgi', {
						parameters:Form.serialize('formAltaFront')+'&form_alta=alta_contenido_rosario&'+ rand,
						method: 'post',
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {
							alert("Su noticia ha sido cargada correctamente. Gracias por participar.");
							Form.reset('formAltaFront');
// 							$('LoaderTicket').style.display = 'none';
// 							$('ticket').style.display = 'block';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});
	
}

function contarCaracteres(e, cantidad){
 	var texto = document.getElementById('des_new').value
 	cant_caracteres = cantidad - texto.length;

	if(cant_caracteres < 0){
		alert('El contenido no puede tener más de '+cantidad+' caracteres');
		document.getElementById('caracteres_restantes').innerHTML = 0;
	}
	else{
		document.getElementById('caracteres_restantes').innerHTML = cant_caracteres;
	}
}



function loadWidget(idNew){
	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Updater({success:'first_news',failure:'',exception:''}, '/index.cgi', {
						evalScripts:true,
						parameters: 'accion=getPlugin&pluginName=get_noticias&tam_foto=max&news_id=' + idNew + '&template=noticia_socialesdetails.tmpl&_nocache='+rand,
						method: 'get',
						onCreate: function(){
						},
						onComplete: function(transport) {
							
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});
}





function getScrollWidgets(){
 var page=0;

	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Updater({success:'related_comments',failure:'',exception:''}, '/index.cgi', {

// 		accion=getPlugin&pluginName=get_noticias&template=noticia_sociales_vertical_widget.tmpl&id_grupo=153&tam_foto=med&destacado=no&page=0

						parameters: 'accion=getPlugin&pluginName=get_noticias&template=noticia_sociales_vertical_widget.tmpl&id_grupo=153&tam_foto=med&destacado=no&page='+page,

// 						parameters: 'accion=getPlugin&pluginName=get_related_news&new_id='+ idNew +'&template='+template+'&category_id='+idCategory+'&orderby=codigo&_nocache='+rand,

						method: 'get',
						evalScripts: true,
						evalJS: true,
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {
// 							$('LoaderTicket').style.display = 'none';
// 							$('ticket').style.display = 'block';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});

}

function getScrollWidgetsRevista(){
 var page=0;

	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Updater({success:'related_comments',failure:'',exception:''}, '/index.cgi', {

// 		accion=getPlugin&pluginName=get_noticias&template=noticia_sociales_vertical_widget.tmpl&id_grupo=153&tam_foto=med&destacado=no&page=0

						parameters: 'accion=getPlugin&pluginName=get_noticias&template=widget_listado_revista.tmpl&id_grupo=98&tam_foto=med&destacado=no&page='+page,

// 						parameters: 'accion=getPlugin&pluginName=get_related_news&new_id='+ idNew +'&template='+template+'&category_id='+idCategory+'&orderby=codigo&_nocache='+rand,

						method: 'get',
						evalScripts: true,
						evalJS: true,
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {
// 							$('LoaderTicket').style.display = 'none';
// 							$('ticket').style.display = 'block';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});

}


function getScrollWidgets(actionType){
    var page= document.getElementById('currentNewsPage');
    var currentPage;

    if(actionType == 'Prev'){
	currentPage = parseInt(page.value) - 1;
    }
    else{
	currentPage = parseInt(page.value) + 1;
    }

 if (currentPage >= 0){
    page.value = currentPage;
  }
  else{
      return 0;
  }


	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Updater({success:'widgetNoticiaSocialesVerticalContainer',failure:'',exception:''}, '/index.cgi', {

// 		accion=getPlugin&pluginName=get_noticias&template=noticia_sociales_vertical_widget.tmpl&id_grupo=153&tam_foto=med&destacado=no&page=0

						parameters: 'accion=getPlugin&pluginName=get_noticias&template=noticia_sociales_vertical_widget.tmpl&id_grupo=153&tam_foto=med&destacado=no&page='+page.value,

// 						parameters: 'accion=getPlugin&pluginName=get_related_news&new_id='+ idNew +'&template='+template+'&category_id='+idCategory+'&orderby=codigo&_nocache='+rand,

						method: 'get',
						evalScripts: true,
						evalJS: true,
						onCreate: function(){
// 							$('LoaderTicket').style.display = 'block';
// 							if ($('ticket')){$('ticket').style.display = 'none';}
						},
						onComplete: function(transport) {
// 							$('LoaderTicket').style.display = 'none';
// 							$('ticket').style.display = 'block';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});

}

function getPopUp() {
	if(document.getElementById('container_popup_home')){
		document.getElementById('container_popup_home').style.display='block';
	}
}

 function cerrar(){
 	document.getElementById('container_popup_home').style.display='none';
	document.getElementById('spots').innerHTML = '';
}

function showMovieDetails(){
	var urlPagina = document.location.href;

	var params = urlPagina.substring(urlPagina.indexOf("?")+1,urlPagina.length);

	//Fijamos el sepador entre parametros
	var delimitador = '&';

	var arrayParams= params.split(delimitador);
	for (var i=0;i<arrayParams.length;i++){
		var variable = arrayParams[i].substring(0,arrayParams[i].indexOf('='));		

		if(variable == 'posicion'){
			var pos = arrayParams[i].substring(arrayParams[i].indexOf('=')+1,arrayParams[i].length);

			popUpMovieDetails('popupDetailMovie_'+pos);
		}
	}

}

//////////////////////////////// Cookie Ciudad ////////////////////////////////////////
var ciudad;
expireDate = new Date;
expireDate.setMonth(expireDate.getMonth()+6);
 
function selectRosario(){
	ciudad = "rosario";
	document.cookie = "CiudadNueva=" + ciudad + ";expires=" + expireDate.toGMTString();
	window.location = "/";
}

function selectSalta(){
	ciudad = "salta";
	document.cookie = "CiudadNueva=" + ciudad + ";expires=" + expireDate.toGMTString();
	window.location = "/?static=salta";
}

function selectSantiago(){
	ciudad = "santiago";
	document.cookie = "CiudadNueva=" + ciudad + ";expires=" + expireDate.toGMTString();
	window.location = "/?static=santiago";
}
function selectBsAs(){
	ciudad = "bs";
	document.cookie = "CiudadNueva=" + ciudad + ";expires=" + expireDate.toGMTString();
	window.location = "/?static=bs";
} 
function popUpSelectCiudad(){
	var rand = Math.round(100*Math.random()); //  IE CACHE FIX 
	new Ajax.Updater({success:'popUpSelectCiudad',failure:'',exception:''}, '/index.cgi', {
						parameters: 'accion=getPlugin&pluginName=get_template&template=select_ciudad.tmpl&_nocache='+rand,
						method: 'get',
						evalScripts: true,
						evalJS: true,
						onCreate: function(){
						},
						onComplete: function(transport) {
							var marginTop = getScrollXY()[1]+10;
							$('contSelecCiudad').style.top = marginTop+'px';
							$('blockUI').style.display = 'block';
							$('popUpSelectCiudad').style.display = 'block';
						},
						onFailure: function(){ alert('Ha ocurrido un error en el sistema. Vuelva a intentar la operación por favor.'); }
					});

}
function cerrarSelectCiudad(){
	$('blockUI').style.display = 'none';
	$('popUpSelectCiudad').style.display = 'none';
}
///////////////////////////////////// Fin Cookie Ciudad ////////////////////////////////

function activarDatos(idOption){
/*	if(){
		
	}
	Effect.BlindDown(idOption);
			$('preguntas_licenciatura').style.display = 'none';

			Effect.BlindUp('estudio_postgrado',{ duration: 0.4 });
	$(idOption)
	alert('Dispara funcion'+idOption);*/	
}

function loginFacebook(){
	FB.api('/me', function(user) {
			if(user.id){
				$('fbLogout').style.display = 'block'
				$('btnFacebook').style.display = 'none';
				$('usuario2').value = user.name;
				$('usuario1').value = user.email;
				$('datosUsuario').innerHTML = user.name + ' está usando <b>Notiexpress</b>';
				$('imgPerfil').src = 'http://graph.facebook.com/' + user.id + '/picture';
				Effect.BlindDown('loginFacebook');
			}
		});
}


function logoutFacebook(){
	FB.logout(function(response) {
	// user is now logged out
		Effect.BlindUp('loginFacebook');
		Effect.BlindDown('btnFacebook');
		$('fbLogout').style.display = 'none'
		$('usuario2').value = '';
		$('usuario1').value = '';
		$('datosUsuario').innerHTML = '';
		$('imgPerfil').src = '';
		
	});
}

function habilitarCarga(){
	if($('terminos').checked){
		$('enviar').disabled = false;
	}
	else{
		$('enviar').disabled = true;
	}
}

function setIframeYoutubeSize(idIframeContent,width,height){
	
      for (var i = 0 ;i < $$('#'+idIframeContent+' iframe').length;i++){
			$$('#'+idIframeContent+' iframe[src*="youtube"]')[i].setAttribute('width',width);
			$$('#'+idIframeContent+' iframe[src*="youtube"]')[i].setAttribute('height',height);
      }
      
      for (var i = 0 ;i < $$('#'+idIframeContent+' div iframe').length;i++){
			$$('#'+idIframeContent+' iframe[src*="youtube"]')[i].setAttribute('width',width);
			$$('#'+idIframeContent+' iframe[src*="youtube"]')[i].setAttribute('height',height);
      }
      
}





