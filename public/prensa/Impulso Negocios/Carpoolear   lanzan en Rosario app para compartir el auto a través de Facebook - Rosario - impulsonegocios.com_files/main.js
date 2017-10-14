/*On document ready*/
$('document').ready(function(){ 
	/*Verify visit cookie*/
	if (getCookie('IN_visit') != '1') {
		setCookie("IN_visit", '1', 365);
		$('#modal_redes').modal('show');			
	}

	 dinamicReloj();

});

/*Cookie's functions*/
	/*Set Cookie*/
	function setCookie(cname, cvalue, exdays) {
	    var d = new Date();
	    d.setTime(d.getTime() + (exdays*24*60*60*1000));
	    var expires = "expires="+d.toUTCString();
	    document.cookie = cname + "=" + cvalue + "; " + expires + "; path=/";
	}
	/*Get Cookie*/
	function getCookie(cname) {
	    var name = cname + "=";
	    var ca = document.cookie.split(';');
	    for(var i=0; i<ca.length; i++) {
	        var c = ca[i];
	        while (c.charAt(0)==' ') c = c.substring(1);
	        if (c.indexOf(name) == 0) return c.substring(name.length,c.length);
	    }
	    return "";
	}


 function dinamicReloj(){

        momentoActual = new Date();
        hora = momentoActual.getHours();
        minuto = momentoActual.getMinutes();


           /*ESTRCUTURA AM - PM*/
 
        if (hora>=12) {
         jornada = ' PM';
        }else{
         jornada = ' AM';
        } 

        if (hora>12) {
            hora -=12;
        }    

        if (hora<10) { /*dos cifras para la hora*/
            hora="0"+hora;
        }
        if (minuto<10) { /*dos cifras para el minuto*/
            minuto="0"+minuto;
        }
 
	        horaImprimible = hora + ":" + minuto + jornada;      

		    setTimeout("dinamicReloj()",1000);

	        $(".clima_hora").html(horaImprimible);

}