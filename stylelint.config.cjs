module.exports = {
  extends: ["stylelint-config-standard"],
  ignoreFiles: ["**/node_modules/**", "**/vendor/**"],
  rules: {
    "alpha-value-notation": "number",
    "color-function-notation": "legacy",
    "no-descending-specificity": null,
    "selector-class-pattern": null,
    "custom-property-pattern": null,
    "custom-property-empty-line-before": null,
    "media-feature-range-notation": null,
    "value-keyword-case": null
  }
};
