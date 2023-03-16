/* global Pvtmed, wp */

jQuery(document).ready(function ($) {
    //debug
    //console.dir(Pvtmed);

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

    /**
     * Display a lock icon on private files.
     */
    function showAttachmentPrivateIcon() {
        if (!wp.media.view.Attachment) {
            return;
        }

        //debug
        //console.dir(wp.media.view.Attachment);
        //console.dir(wp.media.view.Attachment.prototype.template);

        const templateNode = $('#tmpl-attachment');

        if (!templateNode) {
            return;
        }

        const template = templateNode.text();

        //debug
        //console.dir(templateNode);
        //console.dir(templateNode.text());
        //console.dir(templateNode.html());

        //modify image part
        let html = '';
        let lastPos = 0;

        // eslint-disable-next-line no-constant-condition
        while (true) {
            //find img element
            const startPos = template.indexOf('<img ', lastPos);

            if (startPos === -1) {
                break;
            }

            let endPos = template.indexOf(' />', startPos + 4);

            if (endPos === -1) {
                break;
            }

            endPos += 3;

            html += template.substring(lastPos, startPos);
            lastPos = endPos;

            //modify
            const imgHtml = template.substring(startPos, endPos);

            html += imgHtml;
            html += '<# if ( data.privateMedia ) { #>';
            html += '<span class="dashicons dashicons-lock"></span>';
            html += '<# } #>';
        }

        html += template.substring(lastPos);

        //debug cbxx
        console.dir(html);

        //store in DOM
        const script = document.createElement('script');

        script.type = 'text/html';
        script.id = 'tmpl-attachment-pvtmed';
        script.innerHTML = '<div class="pvtmed-attachment">' + html + '</div>';

        document.head.appendChild(script);

        /*
        $('body').append($('<script type="text/html" id="tmpl-attachment-pvtmed" />', {
            html
        }));
        */

        //use new template
        wp.media.view.Attachment.prototype.template = wp.media.template('attachment-pvtmed');
    }

    showAttachmentPrivateIcon();
});