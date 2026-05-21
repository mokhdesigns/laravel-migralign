# MigrAlign v1.0.0 — Release notes (copy for GitHub Release)

**Title:** `v1.0.0` — Initial release

---

## MigrAlign v1.0.0

First stable release of **MigrAlign** — a Laravel package that aligns live **MySQL/MariaDB** schema with your **migration-defined** schema, with safe auto-apply and interactive guidance for risky changes.

### Install

```bash
composer require migralign/laravel-migralign
php artisan vendor:publish --tag=migralign-config
php artisan migralign:sync --dry-run
```

### Highlights

- **`migralign:sync`** — compare migrations vs live database and apply differences
- **Migration scanning** — `Schema::create()` and `Schema::table()` (Laravel 11/12)
- **MySQL introspection** — reads columns from `information_schema`
- **Smart diff** — add/modify/drop columns, create/drop tables
- **Risk levels** — `safe`, `risky`, `destructive` with pre-checks and remediation hints
- **Auto-apply safe changes** — e.g. nullable new columns, widening where safe
- **Interactive prompts** — apply / skip / abort for risky and destructive operations
- **Dry-run & filters** — `--dry-run`, `--table=`, `--migration=`, `--connection=`, `--force`
- **Sync report** — applied, skipped, pending manual, errors

### Requirements

- PHP 8.2+
- Laravel 11 or 12
- MySQL or MariaDB

### Documentation

Full guide: [README](https://github.com/mokhdesigns/laravel-migralign#readme)

### Quick example

```bash
# After adding a column in a migration
php artisan migralign:sync --dry-run
php artisan migralign:sync
```

### Known limitations

- MySQL/MariaDB only (other drivers are rejected with a clear message)
- Highly dynamic migration `up()` logic may need manual review
- Always run `--dry-run` before production sync

### Credits

**Author:** Mokhtar Ali ([@mokhdesigns](https://github.com/mokhdesigns))

**License:** MIT

---

**Full changelog**

### Added

- Laravel service provider and `migralign:sync` Artisan command
- Migration scanner with recording schema builder (Laravel 12 compatible)
- MySQL schema introspector
- Schema diff engine and risk analyzer
- Schema change applier (ALTER TABLE / CREATE TABLE)
- Interactive guide for risky/destructive changes
- Config file `config/migralign.php` (publish tag: `migralign-config`)
- GitHub Actions CI (PHP 8.2/8.3 × Laravel 11/12)
- Package documentation in README

### Security

Report vulnerabilities privately per [SECURITY.md](https://github.com/mokhdesigns/laravel-migralign/blob/main/SECURITY.md).
