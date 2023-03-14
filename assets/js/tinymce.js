/* global tinymce, Pvtmed */
(function (tinymce) {

  function patchEditor(editor) {
    editor.on('init, SetContent', function () {
		var docHead = editor.getDoc().head,
			scriptId,
			scriptElm;

		scriptId  = editor.dom.uniqueId();
		scriptElm = editor.dom.create( 'script', {
			id: scriptId,
			type: 'text/javascript'
		}, 'var Pvtmed = ' + JSON.stringify(Pvtmed) + ';' );

		docHead.appendChild( scriptElm );

		for (var i = 0; i < Pvtmed.scriptUrls.length; i++) {
			scriptId  = 'pvtmed-tinyMCE-script-' + i;
			scriptElm = editor.dom.create( 'script', {
				id: scriptId,
				type: 'text/javascript',
				src: Pvtmed.scriptUrls[i]
			} );

			docHead.appendChild( scriptElm );
		}
    });
  }

  tinymce.on('SetupEditor', function (e) {
    patchEditor(e.editor);
  });

  tinymce.PluginManager.add('pvtmed', patchEditor);
})(tinymce);

























jQuery(document).ready(function($) {
	tinymce.PluginManager.add( 'pvtmed', function ( editor, url ) {
        editor.on( 'init', function () {
        	
        } );
    } );

});