import gulp from 'gulp';

const { src, dest, parallel } = gulp;

import gutil from 'gulp-util';
import rename from 'gulp-rename';
import terser from 'gulp-terser';
import cssnano from 'gulp-cssnano';

//directories
const DIR_ASSETS_CSS = './assets/css';
const DIR_ASSETS_JS  = './assets/js';

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
    // 1) admin main
    await minifyJavaScriptFile({
        src: DIR_ASSETS_JS + '/admin/main.js',
        rename: 'main.min.js',
        dest: DIR_ASSETS_JS + '/admin'
    });

    // 2) main
    await minifyJavaScriptFile({
        src: DIR_ASSETS_JS + '/main.js',
        rename: 'main.min.js',
        dest: DIR_ASSETS_JS
    });

    // 3) TinyMCE
    await minifyJavaScriptFile({
        src: DIR_ASSETS_JS + '/tinymce.js',
        rename: 'tinymce.min.js',
        dest: DIR_ASSETS_JS
    });
}

/**
 * cssnano option.
 */
const CSSNANO_OPTS = {
    //don't change z-index values
    zindex: false
};

/**
 * Minimize CSS.
 *
 * @param {object} opts
 * @returns
 */
function minifyCSSFile(opts) {
    return src(opts.src)
        /*
        .pipe(sass.sync({
            //empty
        }))
        //autoprefix
        .pipe(sassAutoprefixerPlugin())
        */
        .pipe(rename(opts.rename))
        .pipe(cssnano(CSSNANO_OPTS))
        .pipe(dest(opts.dest));
}

/**
 * Build minified CSS.
 */
async function minifyAdminCSS() {
    // 1) main
    return minifyCSSFile({
        src: DIR_ASSETS_CSS + '/admin/main.css',
        rename: 'main.min.css',
        dest: DIR_ASSETS_CSS + '/admin'
    });
}

/**
 * Main build task.
 */
const buildTasks = parallel(
    minifyJavaScript,
    minifyAdminCSS
);

export default buildTasks;