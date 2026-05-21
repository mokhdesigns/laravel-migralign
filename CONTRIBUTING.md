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

1. **Cursor Settings → Agents → Attribution** → turn **off** Commit attribution  
2. Or search **co-author** / **attribution** and disable it  
3. Optional CLI: in `~/.cursor/cli-config.json` set `"commitAttribution": false`

Then commit from your own terminal or from Cursor without the co-author trailer.

### If `cursoragent` still appears on GitHub Contributors

GitHub caches the contributor graph from **old commit SHAs** even after history rewrites. The repository `main` branch was reset to a **single clean commit** with no Cursor co-author metadata. After the force push:

- Wait up to **24–48 hours** for the graph to refresh  
- Hard-refresh the [Contributors](https://github.com/mokhdesigns/laravel-migralign/graphs/contributors) page  
- If it still shows, open a [GitHub Support](https://support.github.com/) ticket to refresh repository contributor data
