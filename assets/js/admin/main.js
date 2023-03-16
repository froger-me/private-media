/* global Pvtmed, wp */

jQuery(document).ready(function ($) {
    const label   = $('.compat-field-pvtmed th').html();
    const content = $('.compat-field-pvtmed .field').html();
    const row     = label + content;

    $('.compat-field-pvtmed').html(row);

    $(document).ajaxComplete(function (e, xhr, settings) {
        const data = decodeURIComponent(settings.data);

        if (-1 !== data.indexOf('action=save-attachment-compat')) {
            const input           = $('.attachment-info [data-setting="url"] input');
            const containsPrivate = (-1 !== input.val().indexOf(Pvtmed.privateUrlBase));
            const containsPublic  = (-1 !== input.val().indexOf(Pvtmed.publicUrlBase));
            const toPublic        = ( -1 === data.indexOf('pvtmed') );
            const toPrivate       = ( -1 !== data.indexOf('pvtmed') );
            const hasChanged      = (containsPrivate && toPublic) || (containsPublic && toPrivate);

            if ( hasChanged ) {
                const sourceBase      = (toPrivate) ? Pvtmed.publicUrlBase : Pvtmed.privateUrlBase;
                const destinationBase = (toPrivate) ? Pvtmed.privateUrlBase : Pvtmed.publicUrlBase;
                const src             = input.val().replace(sourceBase, destinationBase);

                input.val(src);

                if (0 < $('.media-modal-content video').length) {

                    $('.media-modal-content video').each(function(index, el) {
                        const video = $(el);

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

    const getConfirm = function () {
        const r = window.confirm(Pvtmed.deactivateConfirm);

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

    //modify grid template
    //cbxx TODO
    if (wp.media.view.Attachment) {
        //debug cbxx
        //console.dir(wp.media.view.Attachment);
        //console.dir(wp.media.view.Attachment.prototype.template);

        const template = $('#tmpl-attachment');

        //debug cbxx
        console.dir(template);
        console.dir(template.text());
        console.dir(template.html());
    }
});