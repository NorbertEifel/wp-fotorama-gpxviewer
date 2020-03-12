"use strict";

(function () {
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
	
})();

