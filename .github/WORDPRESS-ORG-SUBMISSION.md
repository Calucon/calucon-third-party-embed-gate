# WordPress.org submission runbook

The submission itself is a manual, human act performed under your
WordPress.org account — it cannot be automated. Everything around it is set
up. This file is the checklist and the steps.

## What is ready

- **Plugin header** — name, description, `Version`, `Requires at least: 5.9`,
  `Requires PHP: 7.4`, GPLv2-or-later license, `Text Domain: calucon-third-party-embed-gate`,
  `Domain Path`, `Plugin URI`, `Author URI`.
- **readme.txt** — `Stable tag` matches the header; `Contributors: calucon`;
  `Donate link`; `Tested up to: 7.0`; Description, Installation, FAQ,
  Screenshots and Changelog sections; no compliance claims.
- **Assets** in `.wordpress-org/` — `icon-128x128.png`, `icon-256x256.png`,
  `icon.svg`, and `screenshot-1..4.png` (matching the readme captions).
- **Direct-access guards** on every shipped PHP file; `uninstall.php` guarded
  by `WP_UNINSTALL_PLUGIN`.
- **No outbound requests** anywhere in the shipped code — the core product
  claim, enforced by the E2E suite.
- **CI green** — PHP 7.4/8.4 lint + unit, E2E + axe, WordPress integration,
  and the Plugin Check workflow.
- **Build**: `bash bin/build-zip.sh` → `build/calucon-third-party-embed-gate-<version>.zip`
  (production files only; verified 66 files, no tests/tooling/vendor).

## Before you submit — confirm

1. **`calucon` is your WordPress.org username.** The directory validates the
   `Contributors` field against real accounts. Create/confirm it at
   https://login.wordpress.org/register .
2. **`Tested up to`** names a WordPress version that exists at submit time.
   `7.0` is current today (7.0.3). If 7.1 has shipped and you have not
   retested, either leave 7.0 (a soft "may be outdated" note, never a
   rejection) or bump after testing.
3. **Run Plugin Check** once more on the final build and read the report
   (the CI job does this on every push; to run locally, install the
   `plugin-check` plugin on a test site and run `wp plugin check calucon-third-party-embed-gate`,
   or use the web tool). Fix any ERROR-level findings; warnings are advisory.

## Submit (human steps)

1. Build the final zip: `bash bin/build-zip.sh`.
2. Go to https://wordpress.org/plugins/developers/add/ , sign in, and upload
   `build/calucon-third-party-embed-gate-<version>.zip`. Confirm the requested slug is
   **`calucon-third-party-embed-gate`** (it must match `Text Domain` and `Stable tag`).
3. Accept the guidelines and submit. You will get an automated Plugin Check
   email, then a manual review (typically days to a couple of weeks).
4. Reply to the review email if changes are requested. On approval you get
   SVN access and the slug is reserved.

## After approval — enable auto-deploy

The SVN repository only exists once approved. Then:

1. Add two repository **secrets** (Settings → Secrets and variables →
   Actions): `SVN_USERNAME` and `SVN_PASSWORD` — your WordPress.org login.
2. Add a repository **variable**: `WPORG_DEPLOY` = `true`.
3. From then on, the `deploy` job in `.github/workflows/release.yml` runs
   whenever `trunk` is merged into `main` (right after the release is built),
   pushing trunk + a version tag to SVN and syncing `.wordpress-org/` to the
   SVN `/assets` directory (icons + screenshots). The shipped set is the
   zip's — `bin/build-zip.sh`'s staging directory, handed to the deploy as
   `BUILD_DIR` — so SVN, the GitHub release and the trunk sync carry
   identical files. Until the `WPORG_DEPLOY` variable is `true`, that job is
   skipped, so nothing deploys before you are ready.

The deploy lives in the release workflow on purpose: GitHub does not fire
`release`/tag events for releases created with the default `GITHUB_TOKEN`, so
a separate release-triggered workflow would silently never run.

## The branches, and how a release happens

GitHub mirrors WordPress.org: **`trunk` is development, `main` is stable.**
Feature branches merge into `trunk` — never into `main`. A pull request into
`main` is refused by CI (`.github/workflows/main-gate.yml`) unless its head is
`trunk` and its version has no tag yet, and `main` only accepts *Create a
merge commit*. Hotfixes take the same path; `enforce_admins` is on, so there
is no bypass for anyone.

To cut a release:

1. On a feature branch, bump the version in all three places — the `Version`
   header and `CALUCON_EMBED_GATE_VERSION` in
   `calucon-third-party-embed-gate.php`, and `Stable tag` in `readme.txt` —
   write the changelog and upgrade notice, and merge the PR into `trunk`.
   (`bin/plugin-version.sh` checks the three agree.)
2. The merge runs `.github/workflows/trunk.yml`: SVN trunk is synced and a
   GitHub **pre-release** `vX.Y.Z-rc.N` is published with the zip. Review the
   German on the GlotPress dev projects (links below) and sync the readme
   translation from `.wordpress-org/readme-de_*.md`. Every further merge into
   `trunk` produces the next `rc`.
3. Open the release: `gh pr create --base main --head trunk`. Both gates
   must be green.
4. Merge it with **Create a merge commit**. Squash or rebase would give `main`
   its own commit SHAs, and every later `trunk → main` PR would re-present
   the old commits. `release.yml` then tags `vX.Y.Z`, publishes the GitHub
   release, deletes the `rc` pre-releases for that version and, when
   `WPORG_DEPLOY` is on, deploys the SVN tag.

`trunk` shows as "behind `main`" by one merge commit per release. That is
expected; never "update" it with a squash. A plain merge of `main` into
`trunk` is harmless if ever wanted.

## Trunk and translations

`.github/workflows/trunk.yml` copies the shipped file set into SVN `trunk`
and stops there — no SVN tag, no stable release. It runs automatically on
every merge into `trunk` (where it also publishes the release candidate), and
by hand — **Actions → Trunk → Run workflow** — from any ref, to preview a sync
or refresh trunk without a merge. The manual run defaults to a **dry run**:
the first click shows the diff and commits nothing. Untick *Dry run* to push.
A manual run never publishes a release candidate.

Its purpose is translation. translate.wordpress.org builds its *Development*
and *Development Readme* projects from trunk, so syncing trunk makes
wordpress.org re-parse the readme and the plugin strings. The German can then
be translated and inspected on GlotPress **before** that text becomes the
Stable version the public plugin page shows.

What keeps it safe is that it never writes trunk's `Stable tag:` line. That
line is what wordpress.org serves; the branch's readme names the version being
developed, which has no tag yet, and pointing the directory at a tag that does
not exist is the handbook's own "pushing bad code to users" scenario. The
workflow reads the live value out of trunk, restores it into the readme it
uploads, and refuses to commit unless that tag exists under `/tags`. Whatever
users were getting before the run, they get after it.

After it runs, the strings appear at:

- `translate.wordpress.org/projects/wp-plugins/calucon-third-party-embed-gate/dev-readme/de/default/`
- `translate.wordpress.org/projects/wp-plugins/calucon-third-party-embed-gate/dev/de/default/`

Manual alternative (no CI): `svn co https://plugins.svn.wordpress.org/calucon-third-party-embed-gate`,
copy the built files into `trunk/`, copy `.wordpress-org/*` into `assets/`,
`svn cp trunk tags/<version>`, `svn ci`.

## Regenerating assets

- Screenshots: boot a backend (`bash tests/wp/serve-playground.sh`) and run
  `node bin/capture-screenshots.cjs` → `.wordpress-org/screenshot-1..4.png`.
  Screenshot 5 (block-editor control) is best captured by hand from a real
  editing session.
- Translations: `php tests/bin/generate-pot.php`.
