import gulp from 'gulp';

const { src, dest, parallel } = gulp;

import gutil from 'gulp-util';
import rename from 'gulp-rename';
import terser from 'gulp-terser';

//directories
const DIR_ASSETS_JS = './assets/js';

/**
 * Terser options
 */
const TERSER_OPTS = {
    compress: {
        top_retain: terserTopRetain,

        //file gets a little bit smaller
        passes: 5,

        //do not use sequences (use semicolons)
        sequences: false
    },

    output: {
        //use single quotes
        quote_style: 1
    },

    //show all warnings
    //warnings: true
};

/**
 * Select top function names to retain.
 *
 * @param {*} def
 */
function terserTopRetain(def) {
    //debug flags can be removed
    if (def.name.startsWith('DEBUG')) {
        return false;
    }

    //keep all
    return true;
}

/**
 * Promisify a stream (read or write).
 *
 * @param {*} stream
 */
export const promiseStream = stream => new Promise((resolve, reject) => {
    stream
        .on('end', resolve)
        .on('finish', resolve)
        .on('error', reject);
});

/**
 * Optimize and minify a JavaScript file.
 */
async function minifyJavaScriptFile(opts) {
    let res = src(opts.src);

    //terser
    res = res.pipe(terser(TERSER_OPTS));

    //error handler
    res = res.on('error', err => {
        //debug
        //console.log('=> UglifyJs failed: ' + err.message);

        gutil.log(gutil.colors.red('[Error]'), err.toString());
    });

    //rename
    if (opts.rename) {
        res = res.pipe(rename(opts.rename));
    }

    //dest
    if (opts.dest) {
        res = res.pipe(dest(opts.dest));
    }

    await promiseStream(res);
}

/**
 * Build minified JS files.
 */
async function minifyJavaScript() {
    // 1) main
    await minifyJavaScriptFile({
        src: DIR_ASSETS_JS + '/main.js',
        rename: 'main.min.js',
        dest: DIR_ASSETS_JS
    });

    // 2) TinyMCE
    await minifyJavaScriptFile({
        src: DIR_ASSETS_JS + '/tinymce.js',
        rename: 'tinymce.min.js',
        dest: DIR_ASSETS_JS
    });
}

//TODO build CSS

/**
 * Main build task.
 */
const buildTasks = parallel(
    minifyJavaScript
);

export default buildTasks;