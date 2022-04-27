# YaleSites Linting and Formatting Standards

YaleSites projects are expected to follow a consistent linting and formatting configuration so that developers can focus on solving problems, rather than making sure their code is formatted according to the preferences of one or more developer. This is accomplished through a variety of tools:

- [Prettier](https://prettier.io) for general code formatting across many file types.
- [Commitlint](https://github.com/conventional-changelog/commitlint) to ensure semantic versioning and changelogs can be fully and accurately updated automatically.
- [Stylelint](https://stylelint.io/) to ensure error-free and consistently formatted style sheets (scss).
- [ESLint](https://eslint.org/docs/user-guide/getting-started) to ensure error-free and consistently formatted JavaScript.

## Installation

<details><summary>Prerequisites</summary>

Each environment that needs to pull @yalesites-org packages from GitHub needs to be authenticated using a "Personal Access Token". This only needs to be done once per-environment.

- Go to `https://github.com/settings/tokens/new`
  - In the "Note" field add something like "YaleSites GitHub Packages"
  - Choose an expiration value
  - Check the box for "write:packages" (this will automatically check all of the "repo" boxes as well)
  - Click "Generate token"
- On your local machine, create an environment variable. *See the link below for details
  - The `key` for YaleSites projects needs to be `YALESITES_BUILD_TOKEN`
  - The `value` is the token you created above
- Done!
  
*[Here's a stack overflow post showing how to set persistent environment variables for various shells](https://unix.stackexchange.com/questions/117467/how-to-permanently-set-environmental-variables)

</details>

### Installing the package

There must be a `.npmrc` file in the project root that tells npm to get `@yalesites-org` packages from GitHub rather than npm.

- Create a `.npmrc` file in your project root (or modify an existing one) and add the following:

```bash
@yalesites-org:registry=https://npm.pkg.github.com
//npm.pkg.github.com/:_authToken=${YALESITES_BUILD_TOKEN}
```

Then you can install the package like any other npm dependency.

```bash
npm install --save-dev @yalesites-org/eslint-config-and-other-formatting
```

## Prettier

**All projects _must_ utilize Prettier.**

<details><summary>Prettier Setup</summary>

1. To implement Prettier, create the file `.prettierrc.js` in the project root and add the following:

```js
module.exports = {
  ...require('@yalesites-org/eslint-config-and-other-formatting/prettier.config'),
};
```

2. Then, add this script to the `package.json`:

```json
{
  "scripts": {
    "prettier": "prettier components --ignore-unknown --list-different"
  }
}
```

(Replace `components` with the path to the top-level directory that contains the project's source code.)

</details>

## Commitlint

**All projects _must_ utilize Commitlint.**

<details><summary>Commitlint Setup</summary>

1. To use Commitlint, create the file `commitlint.config.js` in the project root and add the following:

```js
module.exports = {
  extends: [
    '@yalesites-org/eslint-config-and-other-formatting/commitlint.config',
  ],
};
```

2. Install husky in the repository `npx husky install`
3. Create the husky script by running this in the project root: `npx husky add .husky/commit-msg 'npm run husky:commit-msg'`
4. Then define the script in the `package.json`

```json
{
  "scripts": {
    "husky:commit-msg": "commitlint --edit $1"
  }
}
```

</details>

## Stylelint

Stylelint must be implemented on projects that define custom stylesheets.

<details><summary>Stylelint Setup</summary>

1. To use it, create the file `stylelint.config.js` in the project root and add the following:

```js
module.exports = {
  extends: [
    '@yalesites-org/eslint-config-and-other-formatting/stylelint.config',
  ],
};
```

2. Then, add this script to the `package.json`:

```json
{
  "scripts": {
    "lint:styles": "stylelint 'components/**/*.scss'"
  }
}
```

(Replace `components` with the path to the top-level directory that contains the project's source code.)

</details>

## Eslint

ESlint must be implemented on projects that define custom javascript.

<details><summary>Eslint Setup</summary>

1. To use it, create the file `.eslintrc.js` in the project root and add the following:

```js
module.exports = {
  extends: ['@yalesites-org/eslint-config-and-other-formatting'],
};
```

2. Then, add this script to the `package.json`:

```json
{
  "scripts": {
    "lint:js": "eslint components"
  }
}
```

(Replace `components` with the path to the top-level directory that contains the project's source code.)

</details>

## lint-staged

Lint-staged is _highly_ recommended since it will only lint modified files, making the development workflow significantly faster than linting an entire codebase whether or not files have changed.

<details><summary>Lint-staged Setup</summary>

1. Create the husky script by running this in the project root: `npx husky add .husky/pre-commit 'npm run husky:pre-commit'`
2. Then define the script in the `package.json`

```json
{
  "scripts": {
    "husky:pre-commit": "lint-staged"
  }
}
```

3. Finally, define which file types to lint in your `package.json`. Below is an example that runs stylelint on scss files, eslint on js files and prettier on js, scss, and php files. Each project's requirements will vary, and may or may not need all of these (or more) so adjust according to the project needs.

```json
{
  "lint-staged": {
    "components/**/*.scss": ["npm run lint:styles -- --fix"],
    "components/**/*.js": ["npm run lint:js -- --fix"],
    "components/**/*.{js,scss,php}": ["npm run prettier -- --write"]
  }
}
```

</details>

## Releases

Any time something is pushed to the `main` branch on GitHub, a [GitHub Action](.github/workflows/release.yml) is run to determine whether a new release is needed (via semantic-release.)

This is an entirely automated process, so whether changes are pushed directly to `main` or if they go through the preferred PR workflow the release process will be run.
