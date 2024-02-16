module.exports = {
    env: {
        browser: true,
        es6: true,
        node: true
    },
    globals: {
        window: true,
        document: true,
        $: true,
        jQuery: true
    },
    extends: [
        'eslint:recommended'
    ],
    'parserOptions': {
        ecmaVersion: 2022,
        sourceType: 'module',
        requireConfigFile: false
    },
    'rules': {
        'indent': [
            'error',
            4,
            {
                'SwitchCase': 1
            }
        ],
        'linebreak-style': [
            'error',
            'unix'
        ],
        'quotes': [
            'error',
            'single',
            {
                'allowTemplateLiterals': true
            }
        ],
        'semi': [
            'error',
            'always'
        ],
        'no-console': 'off',
        'no-unused-vars': [ 'error', { 'argsIgnorePattern': '^_' }]
    }
};