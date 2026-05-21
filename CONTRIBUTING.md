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
