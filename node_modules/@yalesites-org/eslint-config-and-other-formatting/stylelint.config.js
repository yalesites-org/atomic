module.exports = {
  extends: ['stylelint-config-standard-scss', 'stylelint-config-prettier'],
  plugins: ['stylelint-prettier'],
  rules: {
    'at-rule-no-unknown': [
      true,
      {
        ignoreAtRules: [
          'each',
          'else',
          'function',
          'if',
          'include',
          'mixin',
          'return',
          'use',
        ],
      },
    ],
    'number-leading-zero': 'always',
    'number-no-trailing-zeros': true,
    'prettier/prettier': true,
    'selector-class-pattern': null,
  },
};
