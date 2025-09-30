module.exports = {
    extends: ['stylelint-config-standard'],
    ignoreFiles: ['**/node_modules/**', '**/vendor/**', '**/dist/**'],
    rules: {
        'alpha-value-notation': 'number',
        'color-function-notation': 'legacy',
        'custom-property-empty-line-before': null,
        'custom-property-pattern': null,
        'media-feature-range-notation': null,
        'no-descending-specificity': null,
        'selector-class-pattern': null,
        'value-keyword-case': null
    }
};
