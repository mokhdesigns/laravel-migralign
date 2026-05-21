# Publish MigrAlign on Packagist

Step-by-step guide to list **migralign/laravel-migralign** on [Packagist](https://packagist.org/).

## Package details

| Field | Value |
|-------|--------|
| Composer name | `migralign/laravel-migralign` |
| GitHub repo | https://github.com/mokhdesigns/laravel-migralign |
| Packagist URL (after submit) | https://packagist.org/packages/migralign/laravel-migralign |
| Install command | `composer require migralign/laravel-migralign` |

## Before you submit

1. Repository must be **public**
2. Root `composer.json` must include:

   ```json
   {
     "name": "migralign/laravel-migralign",
     "type": "library",
     "license": "MIT"
   }
   ```

3. Push a version tag (recommended):

   ```bash
   git tag -a v1.0.0 -m "MigrAlign v1.0.0"
   git push origin v1.0.0
   ```

4. Optional: create a [GitHub Release](https://github.com/mokhdesigns/laravel-migralign/releases) for the same tag

## Step 1 — Register on Packagist

1. Go to https://packagist.org/register/
2. Complete registration and verify email
3. Log in

## Step 2 — Submit the repository

1. Open https://packagist.org/packages/submit
2. Enter:

   ```
   https://github.com/mokhdesigns/laravel-migralign
   ```

3. Click **Check**
4. Verify Packagist shows:
   - Name: `migralign/laravel-migralign`
   - Description from `composer.json`
5. Click **Submit**

## Step 3 — Connect GitHub for auto-updates

1. Profile menu → **GitHub** (or package **Settings**)
2. Authorize Packagist on GitHub
3. Enable webhook / auto-update for `mokhdesigns/laravel-migralign`

When you push a new tag (e.g. `v1.0.1`), Packagist updates automatically.

Manual fallback: open the package page → **Update**.

## Step 4 — Test installation

```bash
composer create-project laravel/laravel migralign-test
cd migralign-test

composer require migralign/laravel-migralign

php artisan vendor:publish --tag=migralign-config
php artisan migralign:sync --dry-run
```

Pin a version:

```bash
composer require migralign/laravel-migralign:^1.0
```

## Step 5 — Release updates

```bash
git checkout main
git pull
# make changes, commit, push
git tag -a v1.0.1 -m "MigrAlign v1.0.1"
git push origin v1.0.1
```

Packagist picks up the new tag via webhook (or click **Update**).

## Maintainer checklist

- [ ] Public GitHub repo
- [ ] Valid `composer.json` at repository root
- [ ] Release tag on GitHub
- [ ] Submitted on Packagist
- [ ] GitHub hook enabled
- [ ] Test `composer require` in a clean Laravel app
- [ ] README install instructions match Packagist name

## Common issues

**Composer cannot find package**

- Confirm submit succeeded on Packagist
- Run `composer clear-cache`
- Wait a few minutes for Packagist mirror sync

**Package shows dev-master only**

- Push a semver tag: `v1.0.0`, `v1.0.1`, etc.
- Click **Update** on Packagist

**Auto-update not working**

- Re-connect GitHub in Packagist profile
- Confirm webhook exists in GitHub repo → Settings → Webhooks

## Links

- [Packagist submit](https://packagist.org/packages/submit)
- [Packagist docs — managing packages](https://packagist.org/about)
- [MigrAlign GitHub](https://github.com/mokhdesigns/laravel-migralign)
