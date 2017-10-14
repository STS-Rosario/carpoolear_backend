
horoscopo={};horoscopo.select=function(sign){var items=vsm.object('horoscope-signs');for(var i=0,s=items.childNodes.length;i<s;i++){vsm.removeClass(items.childNodes[i],'selected');}
items=vsm.object('horoscope-icons-items');for(var i=0,s=items.childNodes.length;i<s;i++){console.log(items.childNodes[i]);vsm.removeClass(items.childNodes[i],'selected');}
vsm.addClass('horoscope-item-'+sign,'selected');vsm.addClass('horoscope-icon-'+sign,'selected');}
if(vsm.browser.msie<=6)window.location.href='/1/ie8/upgrade.html';function searchOnFocus(){vsm.object('search').className='focus';var i=vsm.object('inputsearch');if(i&&i.value=='Buscar')i.value='';searchOnChange();}
function searchOnChange(){var s=vsm.object('search');var i=vsm.object('inputsearch');if(!i)return;s.className=((i.value.length>=4)?'focus ready':'focus');}
function searchOnBlur(){vsm.object('search').className='';var i=vsm.object('inputsearch');if(i&&i.value=='')i.value='Buscar';}
function submitSearch(form){var i=vsm.object('inputsearch');if(!i)return;if(i.value.length<4||i.value=='Buscar'){alert('Debe ingresar al menos 4 caracteres para realizar una búsqueda');return false;}
vsm.object('search').className='focus ready';vsm.object('search-button').className+=' busy';}
function showLogin(loginjs){vsm.loadScript(loginjs);vpl.loginForm();}
function hitStatCounter(){document.createElement('img').src='http://c29.statcounter.com/2830019/0/fe95ccd1/0/';document.createElement('img').src='http://c.statcounter.com/5181437/0/a58e9757/1/';}
function hitAnalyticsEvent(name,event){if(typeof _gaq=='object'&&typeof _gaq.push=='function'){_gaq.push(['_trackEvent',name,event]);}}
var personaje={};personaje.show=function(){vsm.replaceClass('personaje-button','visible','hidden');vsm.replaceClass('personaje-button-back','visible','hidden');vsm.replaceClass('personaje-button-loading','hidden','visible');vsm.replaceClass('header-elements','visible','hidden');vsm.replaceClass('header-news','visible','hidden');var pp=vsm.object('personaje-photo');if(pp&&pp.src.substring(pp.src.length-7)=='1x1.gif'){pp.src=pp.getAttribute('data-path');vsm.ajaxCall(pp.getAttribute('data-hiturl'));_gaq.push(['_trackPageview','PERSONAJE.PORTADA']);hitStatCounter();}else{personaje.onLoad();}
return false;}
personaje.onLoad=function(){var pp=vsm.object('personaje-photo');if(pp&&pp.src.substring(pp.src.length-7)=='1x1.gif')return;var h=vsm.object('header');if(h.className.indexOf('expanded')!=-1){vsm.replaceClass('personaje-photo','hidden','visible');vsm.replaceClass('personaje-content','hidden','visible');vsm.replaceClass('personaje-button','visible','hidden');vsm.replaceClass('personaje-button-back','hidden','visible');vsm.replaceClass('personaje-button-loading','visible','hidden');}else{vsm.object('header').className+=' personaje';setTimeout(function(){vsm.replaceClass('personaje-photo','hidden','visible');vsm.replaceClass('personaje-content','hidden','visible');vsm.replaceClass('personaje-button','visible','hidden');vsm.replaceClass('personaje-button-back','hidden','visible');vsm.replaceClass('personaje-button-loading','visible','hidden');},700);}}
personaje.hide=function(){vsm.replaceClass('header','personaje','');vsm.replaceClass('personaje-photo','visible','hidden');vsm.replaceClass('personaje-content','visible','hidden');vsm.replaceClass('personaje-button','hidden','visible');vsm.replaceClass('personaje-button-back','visible','hidden');vsm.replaceClass('personaje-button-loading','visible','hidden');vsm.replaceClass('header-elements','hidden','visible');vsm.replaceClass('header-news','hidden','visible');return false;}
var ranking={}
ranking.show=function(position,url){var w=vsm.object(position+'-ranking-wrapper');if(w&&w.offsetHeight!=0){vsm.replaceClass(w,' visible','');return;}
if(w&&w.offsetHeight==0){document.onclick=function(e){var el=window.event.srcElement||e.target;for(var itm=el;itm;itm=itm.parentNode){if(itm.id==position+'-ranking-wrapper')return;if(itm.className&&itm.className.indexOf('ranking-menu')!=-1)return;}
vsm.replaceClass(w,' visible','');document.onclick="";}
w.className+=' visible';}
w.innerHTML='<div class="busyicon '+position+'ranking"></div>';ranking.load(url);}
ranking.load=function(url){vsm.ajaxCall(url,'ranking.onLoad','ranking.onLoad');}
ranking.eplloaded=false;ranking.onLoad=function(response){var pos='header';var w=vsm.object(pos+'-ranking-wrapper');if(!w||w.offsetHeight==0){pos='footer'
w=vsm.object(pos+'-ranking-wrapper');}
if(w&&w.offsetHeight>0){w.innerHTML=response;_gaq.push(['_trackPageview','RANKING.'+pos]);hitStatCounter();if(!ranking.eplloaded){eplAD4Sync("rankad"+pos,"ANCHOCOMPLETO",{t:1,timeout:0,ma:1,custF:null,sd:"4cfd!RANKING!http://ads.e-planning.net/!!"});ranking.eplloaded=true;}else{eplDoc.epl.reloadSpace("ANCHOCOMPLETO");}}}
services={}
services.gadget={}
services.gadget.onLoad=function(response){if(response){var s=vsm.object('services');if(!s)return;s.innerHTML=response;s.className='';}}
services.show=function(){hitAnalyticsEvent('Menu servicios','Apertura');weather.extended.close();vsm.replaceClass('services-wrapper','hidden','visible');vsm.replaceClass('services-box','hidden','visible');services.load();}
services.load=function(){vsm.ajaxCall('/1/servicios/servicios.vnc','services.onLoad');}
services.onLoad=function(response){var w=vsm.object('services-box');if(!w)return;w.innerHTML=response;}
services.close=function(){hitAnalyticsEvent('Menu servicios','Cerrar');vsm.replaceClass('services-wrapper','visible','hidden');vsm.replaceClass('services-box','visible','hidden');}
var weather={}
weather.gadget={}
weather.gadget.onLoad=function(response){if(response){var w=vsm.object('weather');if(!w)return;if(response.substring(0,1)!='<')return;w.innerHTML=response;w.className='';}}
weather.cities={}
weather.cities.show=function(){weather.extended.close();vsm.replaceClass('weather-cities','hidden','visible');vsm.replaceClass('weather-cities-wrapper','hidden','visible');weather.cities.load();}
weather.cities.load=function(){vsm.ajaxCall('/1/clima/cities.vnc','weather.cities.onLoad');}
weather.cities.onLoad=function(response){var w=vsm.object('weather-cities');if(!w)return;w.innerHTML=response;}
weather.cities.close=function(){vsm.replaceClass('weather-cities-wrapper','visible','hidden');vsm.replaceClass('weather-cities','visible','hidden');}
weather.extended={}
weather.extended.show=function(){hitAnalyticsEvent('Menu clima','Apertura');services.close();vsm.replaceClass('weather-extended-wrapper','hidden','visible');vsm.replaceClass('weather-extended','hidden','visible');weather.extended.load();}
weather.extended.load=function(){vsm.ajaxCall('/1/clima/extended.vnc','weather.extended.onLoad');}
weather.extended.onLoad=function(response){var w=vsm.object('weather-extended');if(!w)return;w.innerHTML=response;}
weather.extended.changeTab=function(part){for(i=0;i<=10;){if(i!=part){vsm.replaceClass('weatherPart'+i,'visible','hidden');var btn=document.getElementById('marcado'+i);btn.className='';}else{vsm.replaceClass('weatherPart'+part,'hidden','visible');var btn=document.getElementById('marcado'+part);btn.className="selected";}
i=i+5}
return false;}
weather.extended.close=function(){vsm.replaceClass('weather-extended-wrapper','visible','hidden');vsm.replaceClass('weather-extended','visible','hidden');}
weather.showCity=function(id){var sel=document.getElementById(id);if(sel[sel.selectedIndex].value.length>0){window.location='/1/clima/index.vnc?id='+sel[sel.selectedIndex].value;}
return false;}
function openwindow(url,windowname,width,height,resize){var parameters=""
if(width!=null){parameters+="width="+width+",left="+(screen.availWidth/2-width/2)+","}
if(height!=null){parameters+="height="+height+",top="+(screen.availHeight/2-height/2)+","}
parameters+="scrollbars=no, status=0, ";if(resize!=null){parameters+="resizable=yes,"}
var popupwin=window.open(url,windowname,parameters);popupwin.focus();}
var pollyn={}
pollyn.vote=function(itemID,optID){var optionsHTML=vsm.object('polloptions-'+itemID);var pollcaptcha=vsm.object('pollcaptcha-'+itemID);optionsHTML.className+=' hidden';var loadingHTML=vsm.object('pollloading-'+itemID);vsm.replaceClass(loadingHTML,'hidden','visible');var option=document.getElementById('pollopt-'+itemID);option.value=optID;if(pollcaptcha){pollcaptcha.innerHTML='';grecaptcha.render('pollcaptcha-'+itemID,{'sitekey':pollcaptcha.getAttribute('data-sitekey'),'callback':function(response){pollyn.captchaResponse(response,itemID)}});setTimeout(function(){vsm.addClass(pollcaptcha.parentNode,'visible');vsm.replaceClass(loadingHTML,'visible','hidden');},2000);}else{pollyn.submit(itemID);}};pollyn.captchaResponse=function(response,itemID){var loadingHTML=vsm.object('pollloading-'+itemID);var pollcaptcha=vsm.object('pollcaptcha-'+itemID);var pollcaptchainput=vsm.object('pollcaptcha-input'+itemID);pollcaptchainput.value=response;vsm.removeClass(pollcaptcha.parentNode,'visible');vsm.replaceClass(loadingHTML,'hidden','visible');pollyn.submit(itemID);};pollyn.submit=function(itemID){var frm=vsm.object('pollfrm-'+itemID);vsm.ajaxForm(frm,function(response){pollyn.voteOK(itemID,response)},function(response){pollyn.voteError(itemID,response)});}
pollyn.loadResults=function(itemID,url){var optionsHTML=document.getElementById('polloptions-'+itemID);optionsHTML.className+=' hidden';var loadingHTML=document.getElementById('pollloading-'+itemID);vsm.replaceClass(loadingHTML,'hidden','visible');vsm.ajaxCall(url,function(response){pollyn.voteOK(itemID,response)},function(){pollyn.voteOK(itemID,'No se pudo cargar los resultados de la encuesta')});return false;};pollyn.goBack=function(itemID){var link=itemID;for(;itemID;itemID=itemID.parentNode){if(itemID.getAttribute('data-itemid')){itemID=itemID.getAttribute('data-itemid');break;}}
if(!itemID)link.parentNode.removeChild(link);var optionsHTML=document.getElementById('polloptions-'+itemID);var resultsHTML=document.getElementById('pollresults-'+itemID);resultsHTML.className+=' hidden';vsm.replaceClass(optionsHTML,'hidden','');return false;}
pollyn.voteOK=function(itemID,response){var resultsHTML=document.getElementById('pollresults-'+itemID);var loadingHTML=document.getElementById('pollloading-'+itemID);vsm.replaceClass(resultsHTML,'hidden','loading');resultsHTML.innerHTML=response;if(getCookie('poll-'+resultsHTML.getAttribute('data-ot')+"-"+resultsHTML.getAttribute('data-oid')))resultsHTML.removeChild(resultsHTML.childNodes[1]);loadingHTML.className+=' hidden';vsm.replaceClass(loadingHTML,'visible','hidden');setTimeout(function(){vsm.replaceClass(resultsHTML,'loading','');},1);}
pollyn.voteError=function(itemID,response){var optionsHTML=vsm.object('polloptions-'+itemID);var loadingHTML=vsm.object('pollloading-'+itemID);var errorHTML=vsm.object('pollerror-'+itemID);errorHTML.innerHTML=response;vsm.removeClass(errorHTML,'hidden');loadingHTML.className+=' hidden';vsm.replaceClass(optionsHTML,'hidden','');setTimeout(function(){vsm.addClass(errorHTML,'hidden')},3000);}
var poll={}
poll.loadResults=function(itemID,url){var optionsHTML=document.getElementById('polloptions-'+itemID);optionsHTML.className+=' hidden';var loadingHTML=document.getElementById('pollloading-'+itemID);vsm.replaceClass(loadingHTML,'hidden','visible');vsm.ajaxCall(url,function(response){poll.voteOK(itemID,response)},function(){poll.voteOK(itemID,'No se pudo cargar los resultados de la encuesta')});return false;}
poll.voteOK=function(itemID,response){var resultsHTML=document.getElementById('pollresults-'+itemID);var loadingHTML=document.getElementById('pollloading-'+itemID);vsm.replaceClass(resultsHTML,'hidden','loading');resultsHTML.innerHTML=response;if(getCookie('poll-'+resultsHTML.getAttribute('data-ot')+"-"+resultsHTML.getAttribute('data-oid'))){link=document.getElementById('polllink-'+itemID);if(link)(link.parentNode).removeChild(link);}
loadingHTML.className+=' hidden';setTimeout(function(){vsm.replaceClass(resultsHTML,'loading','');},1);}
poll.voteError=function(itemID,response){var optionsHTML=document.getElementById('polloptions-'+itemID);var loadingHTML=document.getElementById('pollloading-'+itemID);loadingHTML.className+=' hidden';vsm.replaceClass(optionsHTML,'hidden','');alert(response);}
poll.goBack=function(itemID){var link=itemID;for(;itemID;itemID=itemID.parentNode){if(itemID.getAttribute('data-itemid')){itemID=itemID.getAttribute('data-itemid');break;}}
if(!itemID)link.parentNode.removeChild(link);var optionsHTML=document.getElementById('polloptions-'+itemID);var resultsHTML=document.getElementById('pollresults-'+itemID);resultsHTML.className+=' hidden';vsm.replaceClass(optionsHTML,'hidden','');return false;}
poll.vote=function(itemID,optID){var optionsHTML=document.getElementById('polloptions-'+itemID);var pollcaptcha=vsm.object('pollcaptcha-'+itemID);optionsHTML.className+=' hidden';var loadingHTML=document.getElementById('pollloading-'+itemID);vsm.replaceClass(loadingHTML,'hidden','');var option=document.getElementById('pollopt-'+itemID);option.value=optID;if(pollcaptcha){pollcaptcha.innerHTML='';grecaptcha.render('pollcaptcha-'+itemID,{'sitekey':pollcaptcha.getAttribute('data-sitekey'),'callback':function(response){poll.captchaResponse(response,itemID)}});setTimeout(function(){vsm.addClass(pollcaptcha.parentNode,'visible');vsm.replaceClass(loadingHTML,'visible','hidden');},2000);}else{poll.submit(itemID);}}
poll.captchaResponse=function(response,itemID){var loadingHTML=vsm.object('pollloading-'+itemID);var pollcaptcha=vsm.object('pollcaptcha-'+itemID);var pollcaptchainput=vsm.object('pollcaptcha-input'+itemID);pollcaptchainput.value=response;vsm.removeClass(pollcaptcha.parentNode,'visible');vsm.replaceClass(loadingHTML,'hidden','visible');poll.submit(itemID);};poll.submit=function(itemID){var frm=vsm.object('pollfrm-'+itemID);vsmAjaxForm(frm,function(response){poll.voteOK(itemID,response)},function(response){poll.voteError(itemID,response)});}