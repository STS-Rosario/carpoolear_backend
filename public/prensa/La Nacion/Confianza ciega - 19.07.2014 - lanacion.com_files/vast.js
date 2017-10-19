var publicidadVast = {
       "/scripts/publicidad/vast/ova-jw.swf": {
          "player": {
             "modes": {
                "linear": {
                   "controls": {"enableMute": false, "enableVolume": false }
                }
             }
          },
          "ads": {
             "notice": {"message": "<p class='smalltext' align='right'>El video se verá en _countdown_ segundos</p>"},
             "controls": {
                "skipAd": {"enabled": true, "showAfterSeconds": 5, "html": "Omitir&nbsp;anuncio",
                           "region": {
                                "id": "my-new-skip-ad-button",
                                "verticalAlign": 12,
                                "horizontalAlign": 3,
                                "backgroundColor": "#49BBE0",
                                "opacity": 0.8,
                                "borderRadius": 12,
                                "padding": "0 0 0 4",
                                "width": 96,
                                "height": 22
                            } 
                }
             },
             "clickSign": {
                "enabled": true, 
                "verticalAlign": "center",
                "horizontalAlign": "center",
                "width": 0,
                "height": 0,
                "opacity": 0,
                "borderRadius": 0,
                "html": ""
             },  
             "schedule": [{"position": "pre-roll", "tag": "http://ads.e-planning.net/eb/4/39aa/20b064448d61f656?o=v&ma=1&vv=2" }]
          },
          "debug": {
           "levels":"none"
           //   "levels": "fatal, config, vast_template, vpaid, http_calls, playlist, api"
         }
       }
     };