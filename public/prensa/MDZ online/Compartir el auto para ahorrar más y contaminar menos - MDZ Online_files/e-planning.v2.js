var eplDoc = document; var eplLL = false;
var eS1 = 'ak1.img.e-planning.net';var eplArgs = { iIF:1,sV:schemeLocal() + "://ads.e-planning.net/" ,vV:"4",sI:eplsite,ss:eplsubsection,sec:eplsection,eIs:eplpositions};
function eplCheckStart() {
    if (document.epl) {
        var e = document.epl;
        if (e.eplReady()) {
            return true;
        } else {
            e.eplInit(eplArgs);
            if (eplArgs.custom) {
                for (var s in eplArgs.custom) {
                    document.epl.setCustomAdShow(s, eplArgs.custom[s]);
                }
            }
            return e.eplReady();        
        }
    } else {
        if (eplLL) return false;
        if (!document.body) return false; var eS2; var dc = document.cookie;
        var cookieName = ('https' === schemeLocal() ? 'EPLSERVER_S' : 'EPLSERVER') + '=';
        var ci = dc.indexOf(cookieName);
        if (ci != -1) {
            ci += cookieName.length; var ce = dc.indexOf(';', ci);
            if (ce == -1) ce = dc.length;
            eS2 = dc.substring(ci, ce);
        }
        var eIF = document.createElement('IFRAME');
        eIF.src = 'about:blank'; eIF.id = 'epl4iframe'; eIF.name = 'epl4iframe';
        eIF.width=0; eIF.height=0; eIF.style.width='0px'; eIF.style.height='0px';
        eIF.style.display='none'; document.body.appendChild(eIF);
        
        var eIFD = eIF.contentDocument ? eIF.contentDocument : eIF.document;
        eIFD.open();eIFD.write('<html><head><title>e-planning</title></head><bo'+'dy></bo'+'dy></html>');eIFD.close();
        var s = eIFD.createElement('SCRIPT');
        s.src = schemeLocal() + '://' + (eS2?eS2:eS1) +'/layers/epl-41.js';
        eIFD.body.appendChild(s);
        if (!eS2) {
            var ss = eIFD.createElement('SCRIPT');
            ss.src = schemeLocal() + '://ads.e-planning.net/egc/4/4ce4';
            eIFD.body.appendChild(ss);
        }
        eplLL = true;
        return false;
    }
}
eplCheckStart();
function eplSetAdM(eID,custF) {
    if (eplCheckStart()) {
        if (custF) { document.epl.setCustomAdShow(eID,eplArgs.custom[eID]); }
        document.epl.showSpace(eID);
    } else {
        var efu = 'eplSetAdM("'+eID+'", '+ (custF?'true':'false') +');';
        setTimeout(efu, 250);    
    }
}
function eplAD4M(eID,custF) {
    document.write('<div id="eplAdDiv'+eID+'"></div>');
    if (custF) {
        if (!eplArgs.custom) { eplArgs.custom = {}; }
        eplArgs.custom[eID] = custF;
    }
    eplSetAdM(eID, custF?true:false);
}
function schemeLocal() {
    if (document.location.protocol) {
        protocol = document.location.protocol;
    } else {
        protocol = window.top.location.protocol;
    }
    if (protocol) {
        if (protocol.indexOf('https') !== -1) {
            return 'https';
        } else {
            return 'http';
        }
    }
}