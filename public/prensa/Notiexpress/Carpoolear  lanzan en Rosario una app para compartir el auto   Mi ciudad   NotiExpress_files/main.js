window.onload = doOnWindowLoad;

functionsOnLoad=new Array();

function doOnWindowLoad() {
	for(i=0;i<functionsOnLoad.length;i++) {
		functionsOnLoad[i]();
	}
}

function addToWindowOnload(func){
	functionsOnLoad.push(func);
}

// addToWindowOnload(checkCity); //check cookie and redirect

function getXMLHttpRequest(){
		var xmlhttp;
		if(window.XMLHttpRequest) { // no es IE
			xmlhttp = new XMLHttpRequest();
		}
		else { // Es IE o no tiene el objeto
			try {
				xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
			}
			catch (e) {
				alert('El navegador utilizado no esta soportado');
			}
		}
		return xmlhttp;
}

function launchJavascript(responseText) {
	var re = /<script.*?>((\n|.)*?)<\//igm;
	var ScriptFragment = '(?:<script.*?>)((\n|.)*?)(?:<\/script>)';
	var match = new RegExp(ScriptFragment, 'img');
	var scripts  = responseText.match(match);

	if (scripts) {
		var js = '';
		for(var s = 0; s < scripts.length; s++) {
			var match = new RegExp(ScriptFragment, 'im');
			js += scripts[s].match(match)[1];
		}
		eval(js);
	}
}

function MM_reloadPage(init) {  //reloads the window if Nav4 resized
  if (init==true) with (navigator) {if ((appName=="Netscape")&&(parseInt(appVersion)==4)) {
    document.MM_pgW=innerWidth; document.MM_pgH=innerHeight; onresize=MM_reloadPage; }}
  else if (innerWidth!=document.MM_pgW || innerHeight!=document.MM_pgH) location.reload();
}

MM_reloadPage(true);

function MM_openBrWindow(theURL,winName,features) { //v2.0
  window.open(theURL,winName,features);
}

function getCookie(name){
  var cname = name + "=";
  var dc = document.cookie;
  if (dc.length > 0) {
    begin = dc.indexOf(cname);
    if (begin != -1) {
      begin += cname.length;
      end = dc.indexOf(";", begin);
      if (end == -1) end = dc.length;
        return unescape(dc.substring(begin, end));
    } 
  }
  return null;
}

function SetCookie(cookieName,cookieValue,nDays) {
	var today = new Date();
	var expire = new Date();
	if (nDays==null || nDays==0) nDays=1;
	expire.setTime(today.getTime() + 3600000*24*nDays);
	document.cookie = cookieName+"="+escape(cookieValue) + ";expires="+expire.toGMTString();
}
function setRosario() {
	setCookieRosario();
	location.href="/";
}
function setBs() {
	setCookieBs();
	location.href='/?static=bs';
}
function setSalta() {
	setCookieSalta();
	location.href="/?static=salta";
}
function setSantiago() {
	setCookieSantiago();
	location.href='/?static=santiago';
}

function setCookieRosario() {
	SetCookie("defaultCity","rosario",10000);
}
function setCookieBs() {
	SetCookie("defaultCity","bs",10000);
}
function setCookieSantiago() {
	SetCookie("defaultCity","santiago",10000);
}
function setCookieSalta() {
	SetCookie("defaultCity","salta",10000);
}
function checkCity() {
	var procedencias = new Array(
		"http://noti.express.com.ar",
		"http://www.notiexpress.com.ar",
		"http://prueba.notiexpress.com.ar"
	);

	var mustCheck = true;

	for(i in procedencias) {
		if(document.referrer.indexOf(procedencias[i]) > -1) {
			mustCheck = false
		}
	}

	if (mustCheck == true) {
		var city = getCookie('defaultCity');
	
		if (city == 'bs') {
			location.href="/?static=bs";
		}
		else if (city == 'santiago') {
			location.href="/?static=santiago";
		}
		else if (city == 'salta') {
			location.href="/?static=salta";
		}	
		else {
			location.href="/";
		}
	}
	return true;
}

function showChannel4Popup() {
	document.getElementById('popupCanal4').style.display='block';
	document.getElementById('blockUI').style.display='block';
	
	if (/MSIE (\d+\.\d+);/.test(navigator.userAgent)){ // si es Internet Explorer
		document.getElementById('VIDEOCANAL4').controls.play();
	}
	else {
		document.getElementById('VIDEOCANAL4_nonIE').controls.play();
	}
}

function closeChannel4Popup() {
	
	if (/MSIE (\d+\.\d+);/.test(navigator.userAgent)){ // si es Internet Explorer
		document.getElementById('VIDEOCANAL4').controls.stop();
	}
	else {
		document.getElementById('VIDEOCANAL4_nonIE').controls.stop();
	}
	
	document.getElementById('popupCanal4').style.display='none';
	document.getElementById('blockUI').style.display='none';
}

function showVideoPopup(video) {
	var fo = new SWFObject("/swf/mediaPlayer.swf", "player", "325", "279", "8.0.15", "#ffffff", true);
	fo.addParam("allowScriptAccess", "always");
	fo.addParam("wmode", "opaque");
	fo.addParam("align", "middle");
	fo.addVariable("mediaPath", video);
	fo.addVariable("autoPlay", "yes");
	fo.write("mainFramePopupVideo");
	document.getElementById('popupVideo').style.display='block';
	document.getElementById('blockUI').style.display='block';
}

function showAudioPopup(audio) {
	var fo = new SWFObject("/swf/audioPlayer.swf", "player", "152", "21", "8.0.15", "#ffffff", true);
	fo.addParam("allowScriptAccess", "always");
	fo.addParam("wmode", "opaque");
	fo.addVariable("mediaPath", audio);
	fo.addVariable("autoPlay", "yes");
	fo.write("mainFramePopupVideo");
	document.getElementById('popupVideo').style.display='block';
	document.getElementById('blockUI').style.display='block';
}

function showMediaPopup(mediaMaxElements) {
	this.mediaElements=mediaMaxElements;
	this.currentElement=0;
	this.nextMedia=function() {
		if(this.mediaElements[this.currentElement + 1]) {
			this.currentElement=this.currentElement+1;
		}
		else {
			this.currentElement=0;
		}
		this._createImage(this.mediaElements[this.currentElement]);
	}
	this.prevMedia=function() {
		if(this.mediaElements[this.currentElement - 1]) {
			this.currentElement=this.currentElement - 1;
		}
		else {
			this.currentElement=this.mediaElements.length - 1;
		}
		this._createImage(this.mediaElements[this.currentElement]);
	}
	this._createImage=function(srcImg) {
		var imgLoadText = document.createElement('img');
		imgLoadText.setAttribute("align", 'absmiddle');
		imgLoadText.setAttribute("border", '0');
		imgLoadText.setAttribute("src", srcImg);
		document.getElementById('mainFramePopupMedia').replaceChild(imgLoadText,document.getElementById('mainFramePopupMedia').firstChild);
	}
	document.getElementById('popupMedia').style.display='block';
	document.getElementById('blockUI').style.display='block';
	this._createImage(this.mediaElements[this.currentElement]);
}
function closeMediaPopup() {
	document.getElementById('popupMedia').style.display='none';
	document.getElementById('blockUI').style.display='none';
}

function closeMediaVideo() {
	document.getElementById('mainFramePopupVideo').innerHTML = '&nbsp;';
	document.getElementById('popupVideo').style.display='none';
	document.getElementById('blockUI').style.display='none';
}
function closeMediaAudio() {
	document.getElementById('mainFramePopupVideo').innerHTML = '&nbsp;';
	document.getElementById('popupVideo').style.display='none';
	document.getElementById('blockUI').style.display='none';
}
MM_reloadPage(true);

function popUpBolsa(){
  window.open('http://cablehogar.com.ar/popups/bolsa_trabajo/', 'ventana', 'width=600,height=595,menubar=no,scrollbars=no,toolbar=no,location=no,resizable=no,top=100,left=250');
}
