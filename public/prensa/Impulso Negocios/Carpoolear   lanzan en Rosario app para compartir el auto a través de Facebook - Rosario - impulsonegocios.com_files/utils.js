// JavaScript Document
//function to show iframe under menu 

 function showDivWithIFrameMenu(cantItems, iframeNumber, isExplorer, itemId){



	if (isExplorer) {	

		

		var dv = document.getElementById(itemId);

		var ifr = document.getElementById(iframeNumber); 



	if(ifr)//evita error de referencia a objeto nulo al cargar la pagina

	{

			ifr.style.width=110;

		

		switch (cantItems) {

		   case 1:

		      ifr.style.height = (cantItems * 26); 

		      break;

		   case 2:

		      ifr.style.height = (cantItems * 25); 

		      break;

		   case 3:

		      ifr.style.height = (cantItems * 24); 

		      break;

		   case 4:

		      ifr.style.height = (cantItems * 23); 

		      break;

		   case 5:

		      ifr.style.height = (cantItems *23); 

		      break;

		   default :

		      ifr.style.height = (cantItems * 18); 

		} 

				

		topPos=95;

		ifr.style.top =topPos-29;

		

		leftPos = dv.offsetLeft;

				

		ifr.style.left = leftPos;	

		

		ifr.style.zIndex = 17; 

		ifr.style.display = "none"; 

		

		if(dv)//evita error de referencia a objeto nulo al cargar la pagina

			dv.style.visibility	= "visible";  

	

		ifr.style.display  = "block"; 

	 }//if(fr)

	 		

	}

 }

//function to show iframe under menu  

 function showDivWithIFrameSubMenu(cantItems, iframeNumber, isExplorer,itemChildId, itemFatherId, isSubMenu){

	

	if (isExplorer) {	

		

		var posX = 1;

		var posY = 95;

		var dv = document.getElementById(itemFatherId);

		var ifr = document.getElementById(iframeNumber); 

		var itm = document.getElementById(itemChildId);

		

		posX += dv.offsetLeft;

		posY += itm.offsetTop-50;

		

		if(isSubMenu) 

			posX += 102;

			

		

		ifr.style.width=115;

		

		switch (cantItems) {

		   case 1:

		      ifr.style.height = (cantItems * 23); 

		      break;

		   case 2:

		      ifr.style.height = (cantItems * 25); 

		      break;

		   case 3:

		      ifr.style.height = (cantItems * 23); 

		      break;

		   case 4:

		      ifr.style.height = (cantItems * 20); 

		      break;

		   case 5:

		      ifr.style.height = (cantItems *19); 

		      break;

		   default :

		      ifr.style.height = (cantItems * 15); 

		} 

		

		ifr.style.top =posY;

		ifr.style.left = posX;	

		

		ifr.style.zIndex = 18; 

		ifr.style.display = "none"; 

		dv.style.visibility	= "visible";  

		ifr.style.display  = "block"; 

	}

 }

 //funcion utilizada para el 3er nivel de menu

 function showDivWithIFrameSubSubMenu(cantItems, iframeNumber, isExplorer,add, itemFatherId, isSubMenu){

	//alert(itemChildId);

	if (isExplorer) {	

		

		var posX = 1;

		var posY = 95;

		var dv = document.getElementById(itemFatherId);

		var ifr = document.getElementById(iframeNumber); 

		//var itm = document.getElementById(itemChildId);

		

		

		posX += dv.offsetLeft-10;

		//posY += itm.offsetTop;

		//posY=dv.offsetTop+add;

		posY=dv.offsetTop + add;

		

		//alert(itemFatherId + " " + posY);

		

		if(isSubMenu) 

			posX += 204;

			

		

		ifr.style.width=130;

		

		switch (cantItems) {

		   case 1:

		      ifr.style.height = (cantItems * 28); 

		      break;

		   case 2:

		      ifr.style.height = (cantItems * 32); 

		      break;

		   case 3:

		      ifr.style.height = (cantItems * 29); 

		      break;

		   case 4:

		      ifr.style.height = (cantItems * 25); 

		      break;

		   case 5:

		      ifr.style.height = (cantItems *24); 

		      break;

		   default :

		      ifr.style.height = (cantItems * 20); 

		} 

		

		ifr.style.top =posY;

		ifr.style.left = posX;	

		

		ifr.style.zIndex = 18; 

		ifr.style.display = "none"; 

		dv.style.visibility	= "visible";  

		ifr.style.display  = "block"; 

	}

 }

 

 function hideIframe(iframe){

//		alert("hideIframe");

		

		var ifr = document.getElementById(iframe);



		if(ifr)//evita error de referencia a objeto nulo al cargar la pagina

		 ifr.style.display  = "none";

}

 

 function hideDivWithIFrame(iframeNumber){

	var ifr = document.getElementById(iframeNumber); 

	ifr.style.visibility = "hidden"; 

	//ifr.style.display = "none"; 

 }



// INICIO FUNCIONES PARA EMULAR POPUPS (con divs ocultos) 

function openPopup(div_id){

	var popups = document.getElementById("popups");			

	var pops=popups.getElementsByTagName("div");

	for (var i=0; i< pops.length;  i++)

		pops[i].style.display = 'none' ;



	diviframe 	= document.getElementById(div_id);			

	diviframe.style['display'] 	= 'block';

}



function openImgPopup(div_id, imgPath){

	var popups = document.getElementById("popups");			

	var pops=popups.getElementsByTagName("div");

	//for (var i=0; i< pops.length;  i++)		pops[i].style.display = 'none' ;



	diviframe 	= document.getElementById(div_id);			

	diviframe.style['display'] 	= 'block';

	diviframe.childNodes[0].childNodes[0].src=imgPath;



}



function 	close_me (div_id){

	diviframe 	= document.getElementById(div_id);			

	diviframe.style['display'] 	= 'none';

}

// FIN FUNCIONES PARA EMULAR POPUPS (con divs ocultos)/

//-->

function doPrint() {

	window.document.getElementById ? browser=2 : window.document.all ? browser=1 : browser=0;

	browser==2 ? window.print() : print_window();

}



function expand(div_id, ico_id){

  divBar  = document.getElementById(div_id);      

  ico     = document.getElementById(ico_id);      



  if (divBar.style['display'] == 'none'){

    divBar.style['display']   = 'block';

    ico.src = "modules/admin/images/" + ico_id +"_on.gif";  

  }

  else{

    divBar.style['display']   = 'none';

    ico.src = "modules/admin/images/" + ico_id +"_off.gif";

  }

}



function getBrowserName(){

	var idString = navigator.userAgent.toLowerCase();

	

	if (idString.indexOf('konqueror')+1) browser = "Konqueror";

	else if (idString.indexOf('safari')+1) browser = "Safari";

	else if (idString.indexOf('omniweb')+1) browser = "OmniWeb";

	else if (idString.indexOf('opera')+1) browser = "Opera";

	else if (idString.indexOf('firefox')+1) browser = "Firefox";

	else if (idString.indexOf('webtv')+1) browser = "WebTV";

	else if (idString.indexOf('icab')+1) browser = "iCab";

	else if (idString.indexOf('msie')+1) browser = "Internet Explorer";

	else if (!idString.indexOf('compatible')+1) browser = "Netscape Navigator";

	else browser = "An unknown browser";



	return browser;

}





function Get_Cookie( name ) {

	var start = document.cookie.indexOf( name + "=" );

	var len = start + name.length + 1;

	if ( ( !start ) && ( name != document.cookie.substring( 0, name.length ) ) )

		return null;

	

	if ( start == -1 ) return null;

	var end = document.cookie.indexOf( ";", len );

	if ( end == -1 ) end = document.cookie.length;

	return unescape( document.cookie.substring( len, end ) );

}



/* para borrar una cookie se puede usar así:

Modify_Cookie('bblastvisit','/','','0','0')

*/

function Modify_Cookie(name, path, domain,expires,secure) {

	if ( Get_Cookie( name ) ) document.cookie = name + "=" +

		( ( path ) ? ";path=" + path : "") +

		( ( domain ) ? ";domain=" + domain : "" ) +

		( ( expires ) ? ";expires=" + expires : "" ) +

		( ( secure ) ? ";secure=" + secure : "" ) + ";";

}





// FIN FUNCIONES PARA faq (con divs ocultos)/



function expand_faq(div_id, ico_id){

  divBar  = document.getElementById(div_id);      

  ico     = document.getElementById(ico_id);      



  if (divBar.style['display'] == 'none'){

    divBar.style['display']   = 'block';

    ico.style['display']   = 'none';

	

  }

  else{

    divBar.style['display']   = 'none';

    ico.style['display']   = 'block';

  }

}



/* FIX FLASH JAVASCRIPT */

 //v1.1

//Copyright 2006 Adobe Systems, Inc. All rights reserved.

function AC_AX_RunContent(){

  var ret = AC_AX_GetArgs(arguments);

  AC_Generateobj(ret.objAttrs, ret.params, ret.embedAttrs);

}



function AC_AX_GetArgs(args){

  var ret = new Object();

  ret.embedAttrs = new Object();

  ret.params = new Object();

  ret.objAttrs = new Object();

  for (var i=0; i < args.length; i=i+2){

    var currArg = args[i].toLowerCase();    



    switch (currArg){	

      case "pluginspage":

      case "type":

      case "src":

        ret.embedAttrs[args[i]] = args[i+1];

        break;

      case "data":

      case "codebase":

      case "classid":

      case "id":

      case "onafterupdate":

      case "onbeforeupdate":

      case "onblur":

      case "oncellchange":

      case "onclick":

      case "ondblClick":

      case "ondrag":

      case "ondragend":

      case "ondragenter":

      case "ondragleave":

      case "ondragover":

      case "ondrop":

      case "onfinish":

      case "onfocus":

      case "onhelp":

      case "onmousedown":

      case "onmouseup":

      case "onmouseover":

      case "onmousemove":

      case "onmouseout":

      case "onkeypress":

      case "onkeydown":

      case "onkeyup":

      case "onload":

      case "onlosecapture":

      case "onpropertychange":

      case "onreadystatechange":

      case "onrowsdelete":

      case "onrowenter":

      case "onrowexit":

      case "onrowsinserted":

      case "onstart":

      case "onscroll":

      case "onbeforeeditfocus":

      case "onactivate":

      case "onbeforedeactivate":

      case "ondeactivate":

        ret.objAttrs[args[i]] = args[i+1];

        break;

      case "width":

      case "height":

      case "align":

      case "vspace": 

      case "hspace":

      case "class":

      case "title":

      case "accesskey":

      case "name":

      case "tabindex":

        ret.embedAttrs[args[i]] = ret.objAttrs[args[i]] = args[i+1];

        break;

      default:

        ret.embedAttrs[args[i]] = ret.params[args[i]] = args[i+1];

    }

  }

  return ret;

}



///



//v1.0

//Copyright 2006 Adobe Systems, Inc. All rights reserved.

function AC_AddExtension(src, ext)

{

  if (src.indexOf('?') != -1)

    return src.replace(/\?/, ext+'?'); 

  else

    return src + ext;

}



function AC_Generateobj(objAttrs, params, embedAttrs) 

{ 

  var str = '<object ';

  for (var i in objAttrs)

    str += i + '="' + objAttrs[i] + '" ';

  str += '>';

  for (var i in params)

    str += '<param name="' + i + '" value="' + params[i] + '" /> ';

  str += '<embed ';

  for (var i in embedAttrs)

    str += i + '="' + embedAttrs[i] + '" ';

  str += ' ></embed></object>';



  document.write(str);

}



function AC_FL_RunContent(){

  var ret = 

    AC_GetArgs

    (  arguments, ".swf", "movie", "clsid:d27cdb6e-ae6d-11cf-96b8-444553540000"

     , "application/x-shockwave-flash"

    );

  AC_Generateobj(ret.objAttrs, ret.params, ret.embedAttrs);

}



function AC_SW_RunContent(){

  var ret = 

    AC_GetArgs

    (  arguments, ".dcr", "src", "clsid:166B1BCA-3F9C-11CF-8075-444553540000"

     , null

    );

  AC_Generateobj(ret.objAttrs, ret.params, ret.embedAttrs);

}



function AC_GetArgs(args, ext, srcParamName, classid, mimeType){

  var ret = new Object();

  ret.embedAttrs = new Object();

  ret.params = new Object();

  ret.objAttrs = new Object();

  for (var i=0; i < args.length; i=i+2){

    var currArg = args[i].toLowerCase();    



    switch (currArg){	

      case "classid":

        break;

      case "pluginspage":

        ret.embedAttrs[args[i]] = args[i+1];

        break;

      case "src":

      case "movie":	

        args[i+1] = AC_AddExtension(args[i+1], ext);

        ret.embedAttrs["src"] = args[i+1];

        ret.params[srcParamName] = args[i+1];

        break;

      case "onafterupdate":

      case "onbeforeupdate":

      case "onblur":

      case "oncellchange":

      case "onclick":

      case "ondblClick":

      case "ondrag":

      case "ondragend":

      case "ondragenter":

      case "ondragleave":

      case "ondragover":

      case "ondrop":

      case "onfinish":

      case "onfocus":

      case "onhelp":

      case "onmousedown":

      case "onmouseup":

      case "onmouseover":

      case "onmousemove":

      case "onmouseout":

      case "onkeypress":

      case "onkeydown":

      case "onkeyup":

      case "onload":

      case "onlosecapture":

      case "onpropertychange":

      case "onreadystatechange":

      case "onrowsdelete":

      case "onrowenter":

      case "onrowexit":

      case "onrowsinserted":

      case "onstart":

      case "onscroll":

      case "onbeforeeditfocus":

      case "onactivate":

      case "onbeforedeactivate":

      case "ondeactivate":

      case "type":

      case "codebase":

        ret.objAttrs[args[i]] = args[i+1];

        break;

      case "width":

      case "height":

      case "align":

      case "vspace": 

      case "hspace":

      case "class":

      case "title":

      case "accesskey":

      case "name":

      case "id":

      case "tabindex":

        ret.embedAttrs[args[i]] = ret.objAttrs[args[i]] = args[i+1];

        break;

      default:

        ret.embedAttrs[args[i]] = ret.params[args[i]] = args[i+1];

    }

  }

  ret.objAttrs["classid"] = classid;

  if (mimeType) ret.embedAttrs["type"] = mimeType;

  return ret;

}