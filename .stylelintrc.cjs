module.exports = {
  extends: ['stylelint-config-standard'],
  plugins: ['stylelint-order'],
  ignoreFiles: ['**/node_modules/**', '**/vendor/**', '**/dist/**'],
  rules: {
    'alpha-value-notation': 'number',
    'color-function-notation': 'legacy',
    'custom-property-empty-line-before': null,
    'custom-property-pattern': null,
    'declaration-block-no-redundant-longhand-properties': true,
    'declaration-no-important': true,
    'media-feature-range-notation': null,
    'no-descending-specificity': null,
    'order/properties-alphabetical-order': true,
    'selector-class-pattern': null,
    'selector-max-id': 0,
    'value-keyword-case': null,
  },
};
