/* global Pvtmed */
jQuery(document).ready(function($) {
	var label   = $('.compat-field-pvtmed th').html(),
		content = $('.compat-field-pvtmed .field').html(),
		row     = label + content;

	$('.compat-field-pvtmed').html(row);

	$(document).ajaxComplete( function (e, xhr, settings) {
		var data = decodeURIComponent(settings.data);

		if (-1 !== data.indexOf('action=save-attachment-compat')) {
			var input           = $('.attachment-info [data-setting="url"] input'),
				containsPrivate = (-1 !== input.val().indexOf(Pvtmed.privateUrlBase)),
				containsPublic  = (-1 !== input.val().indexOf(Pvtmed.publicUrlBase)),
				toPublic        = ( -1 === data.indexOf('pvtmed') ),
				toPrivate       = ( -1 !== data.indexOf('pvtmed') ),
				hasChanged      = (containsPrivate && toPublic) || (containsPublic && toPrivate);

			if ( hasChanged ) {
				var sourceBase      = (toPrivate) ? Pvtmed.publicUrlBase : Pvtmed.privateUrlBase,
					destinationBase = (toPrivate) ? Pvtmed.privateUrlBase : Pvtmed.publicUrlBase,
					src             = input.val().replace(sourceBase, destinationBase);

				input.val(src);

				if (0 < $('.media-modal-content video').length) {

					$('.media-modal-content video').each(function(index, el) {
						var video = $(el);

						video.attr('src', src);
						video.find('source').attr('src', src);
					});
				}

				input.removeClass('pvtmed-highlight').addClass('pvtmed-highlight');

				setTimeout(function(){
					input.removeClass('pvtmed-highlight');
				},3500);
			}
		}
	});

	var getConfirm = function() {
		var r = window.confirm(Pvtmed.deactivateConfirm);

		return r;
	};

	$('.wp-list-table.plugins tr[data-slug="private-media"] .deactivate a').on( 'click', function() {
		return getConfirm();
	});

	if ($('.wp-list-table.plugins tr[data-slug="private-media"] input[type="checkbox"]').prop('checked') &&
		('deactivate-selected' === $('#bulk-action-selector-top').val() || 'deactivate-selected' === $('#bulk-action-selector-bottom').val())) {
		$('.plugins-php #bulk-action-form').on('submit', function(e) {
			e.preventDefault();

			if (getConfirm()) {
				$(this).submit();
			}
		});
	}
});