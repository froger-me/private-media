import gulp from 'gulp';

const { src, dest, parallel } = gulp;

import gutil from 'gulp-util';
import rename from 'gulp-rename';
import terser from 'gulp-terser';
import cssnano from 'gulp-cssnano';
import zip from 'gulp-zip';
import fs from 'node:fs';

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
const promiseStream = stream => new Promise((resolve, reject) => {
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
    minifyAdminCSS,
    convertReadmeToMarkdown
);

export default buildTasks;

/**
 * Zip plugin.
 *
 * @returns
 */
function zipPrivateMedia() {
    return src([
        './**',

        //exclude node_modules
        '!**/node_modules{,/**}',

        //exclude file itseld
        '!./private-media.zip'
    ])
        .pipe(zip('private-media.zip'))
        .pipe(dest('.'));
}

export {
    zipPrivateMedia as zip
};

/**
 * Convert readme.txt to README.md.
 *
 * Based on code from https://github.com/stephenharris/wp-readme-to-markdown/blob/master/tasks/wp_readme_to_markdown.js.
 */
async function convertReadmeToMarkdown({ screenshot_url = false, pre_convert = null, post_convert = null }) {
    let readme = await fs.promises.readFile('./readme.txt', 'utf-8');

    //debug
    //console.dir(readme);

    //normalize
    readme = readme.replace(/\r?\n/g, '\n');

    //pre-convert
    readme = pre_convert?.(readme) || readme;

    /*
        * The following is a ported version of
        * {@see https://github.com/benbalter/WP-Readme-to-Github-Markdown}
        */

    // Convert Headings.
    readme = readme.replace(new RegExp('^=([^=]+)=*?[\\s ]*?$', 'gim'), '###$1###\n');
    readme = readme.replace(new RegExp('^==([^=]+)==*?[\\s ]*?$', 'mig'), '##$1##\n');
    readme = readme.replace(new RegExp('^===([^=]+)===*?[\\s ]*?$', 'gim'), '#$1#\n');

    // Parse contributors, donate link, etc.
    // eslint-disable-next-line no-control-regex
    const header_match = readme.match(new RegExp('([^##]*)(?:\n##|$)', 'm'));

    if (header_match?.length >= 1) {
        const header_search = header_match[1];
        // eslint-disable-next-line no-control-regex
        const header_replace = header_search.replace(new RegExp('^([^:\r\n*]{1}[^:\r\n#\\]\\[]+): (.+)','gim'), '**$1:** $2  ');

        readme = readme.replace(header_search, header_replace);
    }

    // Include WP.org profiles for contributors.
    const contributors_match = readme.match(new RegExp('(\\*\\*Contributors:\\*\\* )(.+)', 'm'));

    if (header_match?.length >= 1) {
        const contributors_search = contributors_match[0];
        let contributors_replace = contributors_match[1];
        const profiles = [];

        // Fill profiles.
        contributors_match[2].split(',').forEach(value => {
            value = value.trim().toLowerCase().replace(/ /g, '-');

            profiles.push('[' + value + '](https://profiles.wordpress.org/' + value + '/)');
        });

        contributors_replace += profiles.join(', ');

        // Add line break.
        contributors_replace += '  ';

        readme = readme.replace(contributors_search, contributors_replace);
    }

    // Guess plugin slug from plugin name.
    // @todo Get this from config instead?
    const _match = readme.match(new RegExp('^#([^#]+)#[\\s ]*?$', 'im'));

    // Process screenshots, if any.
    const screenshot_match = readme.match(new RegExp('## Screenshots ##([^#]*)', 'im'));

    if (screenshot_url && _match && screenshot_match?.length > 1) {
        const plugin = _match[1].trim().toLowerCase().replace(/ /g, '-');

        // Collect screenshots content.
        const screenshots = screenshot_match[1];

        // Parse screenshot list into array.
        const globalMatch = screenshots.match(new RegExp( '^[0-9]+\\. (.*)', 'gim'));
        const matchArray = [];
        let nonGlobalMatch;

        for (const i in globalMatch ) {
            nonGlobalMatch = globalMatch[i].match(new RegExp( '^[0-9]+\\. (.*)', 'im'));

            matchArray.push(nonGlobalMatch[1]);
        }

        // Replace list item with Markdown image syntax, hotlinking to plugin repo.
        // @todo Assumes .png, perhaps should check that file exists first?
        for (let i = 1; i <= matchArray.length; i++) {
            let url = screenshot_url;

            url = url.replace('{plugin}', plugin);
            url = url.replace('{screenshot}', 'screenshot-' + i);

            readme = readme.replace(globalMatch[i - 1], '### ' + i + '. ' +  matchArray[i - 1] + ' ###\n![' + matchArray[i - 1] + '](' + url + ')\n' );
        }
    }

    // Code blocks.
    // eslint-disable-next-line no-control-regex
    readme = readme.replace(new RegExp('^`$[\n\r]+([^`]*)[\n\r]+^`$', 'gm'), (codeblock, codeblockContents) => {
        const lines = codeblockContents.split('\n');

        // Add newline and indent all lines in the codeblock by one tab.
        return '\n\t' + lines.join('\n\t') + '\n'; //trailing newline is unnecessary but adds some symmetry.
    });

    // Remove duplicate new lines.
    readme = readme.replace(/\n{3,}\s*/g, '\n\n');

    // Add new line at end.
    if (readme.at(-1) !== '\n') {
        readme += '\n';
    }

    readme = post_convert?.(readme) || readme;

    // Write the destination file.
    await fs.promises.writeFile('./README.md', readme);
}