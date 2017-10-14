String.prototype.extractNumbers = function(returnType)

{

	var i, l = this.length, t = isNaN(returnType), r = "";

	

	for (i=0; i<l; i++)

	if (isNaN(this.charAt(i)) == t)

	r += this.charAt(i);

	 

	return r;

}

function sendFormNotCheck(pForm){

	var send_button = window.document.getElementById("submit"); // el boton que el formulario debe tener el id=submit

	if(send_button)send_button.disabled=true;

	return true;

}



function removeAllChilds(SelfForm,divDell) {
	     while(xVar=document.getElementById(divDell)){
  	   	     document.SelfForm.removeChild(xVar);

		  }
	    
	}



function sendForm(pForm){
	var len = pForm.length;
	var ok = true;

	for (var i=0 ;  i<len ; i++){
		var tmpObj=pForm.elements[i];
		var alt=tmpObj.getAttribute("checkfor");
		if (isEmpty(alt) && alt!=undefined){
			var altArray=alt.split("|");
			var cmd=altArray[0].split(":");

			for (var j=1; j< altArray.length; j++){
				var t=altArray[j].split(":");
				if (t[0]=="msg") msg=t[1];
			}

			ok = eval("is"+cmd[0])(tmpObj.value, cmd[1], tmpObj);

			if (!ok){
				errorOn(tmpObj, msg);
				return false;
				break;
			}

		}

	}
	var send_button = window.document.getElementById("submit"); // el boton que el formulario debe tener el id=submit
	if(send_button)send_button.disabled=true;	

	return ok;
}



function errorOn(pObj, pMsg){

	alert (pMsg);

}



function isEmail(s_email) {

	var r1 = new RegExp("(@.*@)|(\\.\\.)|(@\\.)|(^\\.)");

	var r2 = new RegExp("^.+\\@(\\[?)[a-zA-Z0-9\\-\\.]+\\.([a-zA-Z]{2,3}|[0-9]{1,3})(\\]?)$");

	return (!r1.test(s_email) && r2.test(s_email));

}



function isNotCero ( pString ) {

	return (pString!=0);

}



function isEmpty ( pString ) {

	return !isBlank(pString)

}



function isBlank ( pString ) {

	return Boolean( /^\s*$/.test( pString ) );

}



function isString ( pString){

	if (!isEmpty(pString)) return true;

	else return  isNaN (pString);

}



function isNumber ( pString){

	if (!isEmpty(pString)) return true;

	else if (!isString (pString)) return true;

		else return false;

}



function isBetween ( pString,  pValues){

	var aValues = pValues.split(",");

	var Min 	 = aValues[0];

	var Max	 = aValues[1];



if (!isEmpty(pString)) return true;

	else 

		if (isNumber){

			if (eval (pString) > Min && eval (pString) < Max) return true;

				else return false;

		}

		else return false;

}



function isEqual (pString, pTo){

	return (document.getElementById(pTo).value == pString);

}



function isDate (pString, pTo, pp){

//"date|mm-dd-yyyy"



	var date = pString.split ("-");

	month 	= eval (date[0]);

	day 	= eval (date[1]);

	year	= eval (date[2]);

		if (month > 12 ) return false;

		else 

		{

			if ( (month == 1 || month == 3 || month == 5 || month == 7 || month == 8 || month == 10 || month == 10) &&  day  > 31)

				return false;

			else 

				if ((month == 4 || month == 6 || month == 9 || month == 11) && day > 30 ) 

					return false;

				else 

					if (month ==2 && day > 29 ) 

							return false;

					else if (year < 1900   ) 

						return false;

		}

		return true;

	

}



function isCheckbox (pString, pValue, pObj){

	return (pObj.checked==pValue);

}



var counter = 0;



function moreFieldsDiv(param){



  counter++;

	

  var newFields = window.document.getElementById('read_' + param).cloneNode(true);



  newFields.id = '';



  newFields.style.display = 'block';

  

  var newField = newFields.childNodes;

  for (var i=0; i<newField.length; i++) {

    var obj = newField[i];

  	var elementos = new Array();

    

    if (obj.tagName == "DIV"){ 



        elementos = obj.childNodes;

      

	    for (var x=0; x<elementos.length; x++){

	    	

	    	var theName = elementos[x].id;

	        

		    if ((theName) && (elementos[x].type == "text"))

	    		elementos[x].id = theName + counter;

    	}

    

    }	

  

  }







  var insertHere = document.getElementById('write_'+param);



  insertHere.parentNode.insertBefore(newFields, insertHere);



  if(browser=='Internet Explorer')



    insertHere.parentNode.parentNode.style.display='block';



}



function moreFields(param){



  counter++;

  var newFields = window.document.getElementById('read_' + param).cloneNode(true);

  newFields.id = '';

  newFields.style.display = 'block';

  var newField = newFields.childNodes;

  for (var i=0; i<newField.length; i++) {

    var theName = newField[i].id;



    if (theName)newField[i].id = theName + counter;

  }



  var insertHere = document.getElementById('write_'+param);

  insertHere.parentNode.insertBefore(newFields, insertHere);

  if(browser=='Internet Explorer')

    insertHere.parentNode.parentNode.style.display='block';

}



function moreFieldsRet(param, quanty){

  counter++;

  var newFields = window.document.getElementById('read_' + param).cloneNode(true);

  newFields.id = '';

  newFields.style.display = 'block';

  var newField = newFields.childNodes;

  for (var i=0; i<newField.length; i++) {

    var theName = newField[i].id;

    if (theName)newField[i].id = theName + (counter+quanty);

  }



  var insertHere = document.getElementById('write_'+param);

  insertHere.parentNode.insertBefore(newFields, insertHere);

  if(browser=='Internet Explorer')

    insertHere.parentNode.parentNode.style.display='block';

  return('id_module'+(counter+quanty));   

}



var detect = navigator.userAgent.toLowerCase();

var OS,browser,version,total,thestring;



if (checkIt('konqueror'))

{

browser = "Konqueror";

OS = "Linux";

}

else if (checkIt('safari')) browser = "Safari"

else if (checkIt('omniweb')) browser = "OmniWeb"

else if (checkIt('opera')) browser = "Opera"

else if (checkIt('webtv')) browser = "WebTV";

else if (checkIt('icab')) browser = "iCab"

else if (checkIt('msie')) browser = "Internet Explorer"

else if (!checkIt('compatible'))

{

browser = "Netscape Navigator"

version = detect.charAt(8);

}

else browser = "An unknown browser";



if (!version) version = detect.charAt(place + thestring.length);



if (!OS)

{

if (checkIt('linux')) OS = "Linux";

else if (checkIt('x11')) OS = "Unix";

else if (checkIt('mac')) OS = "Mac"

else if (checkIt('win')) OS = "Windows"

else OS = "an unknown operating system";

}



function checkIt(string)

{

place = detect.indexOf(string) + 1;

thestring = string;

return place;

}



var counter2 = 0;



function moreFields2(paramRead,paramWrite){

counter2++;

	var newFields = document.getElementById('read_' + paramRead).cloneNode(true);

	newFields.id = 'visor';

	newFields.style.display = 'block';

	var newField = newFields.childNodes;

	for (var i=0; i<newField.length; i++) {

	var theName = newField[i].id;

	if (theName)newField[i].id = theName + counter2;

	}



	var insertHere = document.getElementById('write_'+paramWrite);

	insertHere.parentNode.insertBefore(newFields, insertHere);

	//if(browser!='Netscape Navigator')

	if(browser=='Internet Explorer')

		insertHere.parentNode.parentNode.style.display='block';

}



function hideDiv(id){

		window.document.getElementById(id).style.display = 'none';	

		return false;

}



function ShowDiv(id){

		window.document.getElementById(id).style.display = 'block';	

		return false;

}

