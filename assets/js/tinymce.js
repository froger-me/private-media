/* global tinymce, Pvtmed */
(function (tinymce) {

    //check tinyMCE is loaded
    if (!tinymce) {
        return;
    }

    function patchEditor(editor) {
        editor.on('init, SetContent', function () {
            let docHead = editor.getDoc().head,
                scriptId,
                scriptElm;

            scriptId  = editor.dom.uniqueId();
            scriptElm = editor.dom.create('script', {
                id: scriptId,
                type: 'text/javascript'
            }, 'var Pvtmed = ' + JSON.stringify(Pvtmed) + ';');

            docHead.appendChild(scriptElm);

            for (let i = 0; i < Pvtmed.scriptUrls.length; i++) {
                scriptId  = 'pvtmed-tinyMCE-script-' + i;
                scriptElm = editor.dom.create('script', {
                    id: scriptId,
                    type: 'text/javascript',
                    src: Pvtmed.scriptUrls[i]
                });

                docHead.appendChild(scriptElm);
            }
        });
    }

    tinymce.on('SetupEditor', function (e) {
        patchEditor(e.editor);
    });

    tinymce.PluginManager.add('pvtmed', patchEditor);

})(window.tinymce);

jQuery(document).ready(function (_$) {
    if (!window.tinymce) {
        return;
    }

    tinymce.PluginManager.add('pvtmed', function (editor, _url) {
        editor.on('init', function () {
            //empty
        });
    });
});