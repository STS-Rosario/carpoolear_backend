var scriptSrc = $("script#scriptGA").attr("params");
params = new ParamsObject(scriptSrc);

var hoy = new Date();
var fin = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 1);

var _gaq = _gaq || [];
_gaq.push(['_setAccount', params.ga_id]);
_gaq.push(['_setVisitorCookieTimeout', fin - hoy]);
_gaq.push(['_setDomainName', '.lanacion.com.ar']);
_gaq.push(['_setCampaignCookieTimeout',0]);
//var lealtad = (LeerCookie("leal") != "") ? LeerCookie("leal") : "1";
//_gaq.push(['_setCustomVar',1,'lealtad',lealtad,2]);
if (!isNullOrEmpty(params.topico)) _gaq.push(['_setCustomVar',4,'tema',params.topico,3]);
_gaq.push(['_trackPageview',params.path]);

(function() {
var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
//ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
  ga.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js';
var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();