var eplDoc = document; var eplLL = false;
var eS1 = 'us.img.e-planning.net';
var bannerEpl = "";

function eplCheckStart() {
	if (document.epl) {
		var e = document.epl;
		if (e.eplReady()) {
			return true;
		} else {
			e.eplInit(eplArgs);
			return e.eplReady();		
		}
	} else {
		if (eplLL) return false;
		if (!document.body) return false; var eS2; var dc = document.cookie; var ci = dc.indexOf("EPLSERVER=");
		if (ci != -1) {
			ci += 10; var ce = dc.indexOf(';', ci);
			if (ce == -1) ce = dc.length;
			eS2 = dc.substring(ci, ce);
		}
		
		var eIF = document.createElement('IFRAME');
		eIF.src = 'about:blank'; eIF.id = 'epl4iframe'; eIF.name = 'epl4iframe';
		eIF.width=0; eIF.height=0; eIF.style.width='0px'; eIF.style.height='0px';
		eIF.style.display='none'; document.body.appendChild(eIF);
		
		var eIFD = eIF.contentDocument ? eIF.contentDocument : eIF.document;
		eIFD.open();eIFD.write('<html><head><title>e-planning</title></head><bo'+'dy></bo'+'dy></html>');eIFD.close();
		var s = eIFD.createElement('SCRIPT'); s.src = 'http://' + (eS2?eS2:eS1) +'/layers/epl-41.js';
		eIFD.body.appendChild(s);
		if (!eS2) {
			var ss = eIFD.createElement('SCRIPT');
			ss.src = 'http://ads.e-planning.net/egc/4/3f63';
			eIFD.body.appendChild(ss);
		}
		eplLL = true;
		return false;
	}
}

eplCheckStart();
function eplSetAd(eID,custF) {
	if (eplCheckStart()) {
		if (custF) { document.epl.setCustomAdShow(eID,eplArgs.custom[eID]); }
		document.epl.showSpace(eID);
		
	} else {
		var efu = 'eplSetAd("'+eID+'", '+ (custF?'true':'false') +');';
		setTimeout(efu, 250);
		
	}
}

function eplAD4M(eID,custF) {
	document.write('<div id="eplAdDiv'+eID+'"></div>');
	if (custF) {
	    if (!eplArgs.custom) { eplArgs.custom = {}; }
	    eplArgs.custom[eID] = custF;
	}
	eplSetAd(eID, custF?true:false);
}

