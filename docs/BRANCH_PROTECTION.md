# Recommended branch protection for `main`

Use these settings in GitHub: **Repository → Settings → Branches → Add branch protection rule** (branch name pattern: `main`).

## Rule: Protect `main`

| Setting | Recommended value | Why |
|--------|---------------------|-----|
| **Require a pull request before merging** | ✅ Enabled | No direct pushes to `main` |
| **Required approvals** | `1` (or `2` for teams) | Human review before merge |
| **Dismiss stale pull request approvals when new commits are pushed** | ✅ Enabled | Re-review after changes |
| **Require review from Code Owners** | Optional | If you add `CODEOWNERS` |
| **Require status checks to pass before merging** | ✅ Enabled | CI must be green |
| **Require branches to be up to date before merging** | ✅ Enabled | Avoid merging outdated PRs |
| **Status checks that are required** | `tests / phpunit (8.2, 11.*)` (and other matrix jobs, or use “Require all jobs”) | Matches `.github/workflows/tests.yml` |
| **Require conversation resolution before merging** | ✅ Enabled (teams) | All review threads resolved |
| **Require signed commits** | Optional | Stricter supply-chain hygiene |
| **Require linear history** | Optional | Squash merge only, clean history |
| **Include administrators** | ❌ Disabled (recommended) | Admins follow same rules |
| **Allow force pushes** | ❌ Disabled | Prevent history rewrite on `main` |
| **Allow deletions** | ❌ Disabled | Prevent accidental branch delete |

## Merge settings (Repository → Settings → General)

| Setting | Recommended |
|--------|-------------|
| **Allow squash merging** | ✅ Primary method for PRs |
| **Allow merge commits** | Optional |
| **Allow rebase merging** | Optional |
| **Automatically delete head branches** | ✅ Enabled |

## Tags and releases

| Setting | Recommended |
|--------|-------------|
| **Protect tags** | Add rule for `v*` (optional) — restrict who can create/delete version tags |
| **Releases** | Create GitHub Release from tag `v1.0.0` after CI passes on `main` |

## Optional: rulesets (GitHub Enterprise / newer repos)

If your repo uses **Rulesets** instead of classic protection:

1. Target branch: `main`
2. Require pull request + 1 approval
3. Require status check: workflow `tests`
4. Block force push and deletion

## Quick setup checklist

- [ ] Branch protection rule on `main`
- [ ] Required status check: `tests` workflow
- [ ] PR required with at least 1 approval
- [ ] Force push disabled on `main`
- [ ] Dependabot enabled (Security → Dependabot)
- [ ] Tag `v1.0.0` created from `main` after merge
- [ ] GitHub Release published with notes from `docs/RELEASE-v1.0.0.md`
