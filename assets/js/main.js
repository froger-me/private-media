/* global Pvtmed, console */
if ('undefined' !== typeof jQuery) {
	pvtmed_assets_fix();
} else {
	var jquery = document.getElementById('pvtmed-tinyMCE-script-0');

	jquery.onload = pvtmed_assets_fix;
}

function pvtmed_assets_fix() {
	jQuery(document).ready(function($) {

		$('img').each(function(index, el) {
			var img = $(el);
			$('<img/>').on('error', function() { handleError(img); }).attr('src', img.attr('src'));
		});

		$('video').on('error', function() { handleError($(this)); });
		$('audio').on('error', function() { handleError($(this)); });

		function handleError(media) {

			if (!media.hasClass('pvtmed-checked')) {
				var replace, re;

				if (-1 !== media.attr('src').indexOf(Pvtmed.privateUrlBase)) {
					replace = Pvtmed.privateUrlBase;
					re      = new RegExp(replace, 'g');
					
					if (Pvtmed.isAdmin) {
						window.alert( Pvtmed.brokenMessage + media.attr('src') );
					}
					console.log('Private Media plugin - Attempting to fix broken media ' + media.attr('src'));

					media.attr('src', media.attr('src').replace(re, Pvtmed.publicUrlBase));
					media.is('[srcset]') && media.attr('srcset', media.attr('srcset').replace(re, Pvtmed.publicUrlBase));

					console.log('Private Media plugin - Broken private media source changed to public source ' + media.attr('src'));
				} else if (-1 !== media.attr('src').indexOf(Pvtmed.publicUrlBase)) {
					replace = Pvtmed.publicUrlBase;
					re      = new RegExp(replace, 'g');
					
					if (Pvtmed.isAdmin) {
						window.alert( Pvtmed.brokenMessage + media.attr('src') );
					}
					console.log('Private Media plugin - Attempting to fix broken media ' + media.attr('src'));

					media.attr('src', media.attr('src').replace(re, Pvtmed.privateUrlBase));
					media.is('[srcset]') && media.attr('srcset', media.attr('srcset').replace(re, Pvtmed.privateUrlBase));

					console.log('Private Media plugin - Broken public media source changed to private source ' + media.attr('src'));
				}
			}
			media.addClass('pvtmed-checked');
		}
	});
}


