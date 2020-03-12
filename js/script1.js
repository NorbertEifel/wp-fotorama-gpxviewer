"use strict";

(function (window, document, undefined) {
    var marker = null, map;
    var mapdiv = document.getElementById("map0");
    var img = document.querySelector("#Bilder img");
    var figcaption = document.querySelector("#Bilder figcaption");
    var buttons = document.querySelectorAll("#Bilder button");
    var images = [], nr = 0, ct = 0;
    window.JB = window.JB || {};
    window.JB.GPX2GM = window.JB.GPX2GM || {};

    function setImage(nr) {
    if(nr < 0) nr = images.length - 1;
    if(nr >= images.length) nr = 0;
    console.log(nr);
    img.src = images[images[nr].marker.nr].src;
    figcaption.innerHTML = images[nr].text;
    if(marker) JB.RemoveElement(marker);
    marker = map.Marker({lat:images[nr].coord.lat,lon:images[nr].coord.lon},JB.icons.Kreis)[0];
    return nr;
    }

    buttons[0].onclick = function(){ nr = setImage(nr-1); };
    buttons[1].onclick = function(){ nr = setImage(nr+1); };

    JB.GPX2GM.callback = function(pars) {
    console.log(pars.type);
    if(pars.type == "Map_n") {
        map = mapdiv.makeMap.GetMap();
    }
    if(pars.type == "created_Marker_Bild") {
        images[ct] =  {src: pars.src, text: pars.text, marker: pars.marker, coord: pars.coord};
        pars.marker.nr = ct;
        if(ct==0) {
        setImage(ct);
        }
        ct++;
        return;
    }
    if(pars.type == "click_Marker_Bild") {
        nr = pars.marker.nr;
        nr = setImage(nr);
        return false;
    }
    return true;
    }

    // 1. Initialize fotorama manually.
    var $fotoramaDiv = jQuery('#fotorama').fotorama();

    // 2. Get the API object.
    var fotorama = $fotoramaDiv.data('fotorama');

    // 3. Inspect it in console.
    //console.log(fotorama);
	
	
	jQuery('#move').click(function() {
		var index = fotorama.activeIndex;
		var length = fotorama.size-1;
		if (index == length) {
			index = -1;
			}
		index++;
		fotorama.show(index);
		//console.log('Bild verschoben');
	});
	
	jQuery('#turn').click(function() {
		fotorama.reverse();
		//console.log('umgedreht');
	});
})(window, document);