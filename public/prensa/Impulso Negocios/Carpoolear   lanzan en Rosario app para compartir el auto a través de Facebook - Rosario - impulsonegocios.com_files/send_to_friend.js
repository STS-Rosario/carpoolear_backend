

			$(document).ready(function(){
				$(".social_mail").click(function (){ $('#social_mail_envio').slideDown(); },function (){} );				

				$("#hide_send_to_friend").click(function (){
					$('#social_mail_envio').slideUp();
				});
				
				$('#show_send_to_friend').click(function(){
					$('#stf_msg').html('').hide();
					$('#frm_stf_block').slideDown('slow',function(){$('#tuNombre').focus();});
				});
				 
				$('#send_to_friend').click(function(){
					sendStf_form();
				});
				 
			});			
			
			function sendStf_form(){				
				$.ajax({
					type: "POST",
					url: "../../../../modules/mails/phpmailer_mail.php",
					ajaxStart: $('#stf_loading').show(),
				    data: "_jquery=1&type=form&_tuNombre="+$("#tuNombre").val()+"&_name_from="+$("#tuNombre").val()+"&_mail_from="+$("#Temail").val()+"&_mail_to="+$("#mail_to").val()+"&_design="+$("#design").val()+"&_subject="+$("#subject").val()+"&_name_to="+$("#name_to").val()+"&__Temail="+$("#Temail").val()+"&__contentTitle="+$("#contentTitle").val()+"&__cnt_id="+$("#cnt_id").val(),
					success: function(msg){
						if(msg != 0){
							$('#stf_loading').hide();
							$('#frm_stf_block').slideUp('slow');
							$('#stf_msg').html('<span class=stf_ok>Email enviado con éxito.</span>').fadeIn('slow');
						}else{
							$('#stf_msg').html('<span class=stf_error>Error enviando email. Inténtelo nuevamente.</span>').fadeIn('slow');
						}						
					}
				});				
				return false;
			}
 
	
	
