# Pending workflows — one manual step to activate

The credentials used by the automation session that authored these files
cannot write to `.github/workflows/` (GitHub requires the `workflow` OAuth
scope for that path), so the two workflows are staged here. To activate
them, move them — from any normally-authenticated clone:

```sh
git mv .github/workflows-pending/ci.yml .github/workflows/ci.yml
git mv .github/workflows-pending/release.yml .github/workflows/release.yml
git commit -m "ci: activate test + release workflows"
git push
```

(or recreate the two files under `.github/workflows/` in the GitHub web
editor and delete this directory).

## What they do

- **ci.yml** — PHPCS + the PHPUnit unit/fixture suite on PHP 7.4 (the
  declared floor) and 8.4, for every pull request and every push to `main`.
- **release.yml** — on every push to `main`: re-runs the unit suite, builds
  the installable plugin zip with `bin/build-zip.sh`, and publishes a GitHub
  release. The tag is `v<Version>` from the `consent-gate.php` header; if
  that tag already exists (a merge without a version bump), the release is
  tagged `v<Version>-build.<run>` so every merge still gets its zip.

## Making tests block merges

After the CI workflow has run once, require its checks on `main` under
**Settings → Branches → Add branch ruleset** (or classic protection):

1. Target branch: `main`; enable **Require a pull request before merging**
   and **Require status checks to pass**.
2. Select the two checks: `PHP 7.4 lint + unit tests` and
   `PHP 8.4 lint + unit tests`.

Note: once enabled, direct pushes to `main` are blocked too — all changes
(including from automation sessions) then land via pull requests.

Delete this directory once the workflows are moved.
