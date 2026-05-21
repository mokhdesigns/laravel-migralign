# Contributing

Thanks for contributing to MigrAlign.

## Development setup

1. Fork and clone the repository.
2. Install dependencies:

```bash
composer install
```

3. Run tests:

```bash
composer test
```

## Pull request guidelines

- Keep PRs focused and small.
- Add or update tests for behavior changes.
- Update `README.md` when user-facing behavior changes.
- Ensure CI passes before requesting review.

## Local quality checks

```bash
composer validate --strict
composer test
```

## Git commits (no Cursor co-author)

This repository does **not** list Cursor as a contributor. Do not add:

```text
Co-authored-by: Cursor <cursoragent@cursor.com>
```

### Enable the project hook (recommended)

After cloning, run once:

```bash
git config core.hooksPath .githooks
```

The `prepare-commit-msg` hook removes the Cursor co-author trailer automatically.

### Disable in Cursor IDE

In Cursor: **Settings** → search **co-author** or **attribution** → turn off automatic Git co-author / commit attribution for the agent.

Then commit from your own terminal or from Cursor without the co-author trailer.
