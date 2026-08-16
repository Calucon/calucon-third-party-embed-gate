# WordPress.org submission runbook

The submission itself is a manual, human act performed under your
WordPress.org account — it cannot be automated. Everything around it is set
up. This file is the checklist and the steps.

## What is ready

- **Plugin header** — name, description, `Version`, `Requires at least: 5.9`,
  `Requires PHP: 7.4`, GPLv2-or-later license, `Text Domain: third-party-embed-gate`,
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
- **Build**: `bash bin/build-zip.sh` → `build/third-party-embed-gate-<version>.zip`
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
   `plugin-check` plugin on a test site and run `wp plugin check third-party-embed-gate`,
   or use the web tool). Fix any ERROR-level findings; warnings are advisory.

## Submit (human steps)

1. Build the final zip: `bash bin/build-zip.sh`.
2. Go to https://wordpress.org/plugins/developers/add/ , sign in, and upload
   `build/third-party-embed-gate-<version>.zip`. Confirm the requested slug is
   **`third-party-embed-gate`** (it must match `Text Domain` and `Stable tag`).
3. Accept the guidelines and submit. You will get an automated Plugin Check
   email, then a manual review (typically days to a couple of weeks).
4. Reply to the review email if changes are requested. On approval you get
   SVN access and the slug is reserved.

## After approval — enable auto-deploy

The SVN repository only exists once approved. Then:

1. Add two repository **secrets** (Settings → Secrets and variables →
   Actions): `SVN_USERNAME` and `SVN_PASSWORD` — your WordPress.org login.
2. Add a repository **variable**: `WPORG_DEPLOY` = `true`.
3. From then on, the `deploy` job in `.github/workflows/release.yml` runs on
   every merge to `main` (right after the release is built), pushing trunk +
   a version tag to SVN and syncing `.wordpress-org/` to the SVN `/assets`
   directory (icons + screenshots). The shipped set is the inverse of
   `.distignore`. Until the `WPORG_DEPLOY` variable is `true`, that job is
   skipped, so nothing deploys before you are ready.

The deploy lives in the release workflow on purpose: GitHub does not fire
`release`/tag events for releases created with the default `GITHUB_TOKEN`, so
a separate release-triggered workflow would silently never run.

To cut a release: bump the `Version` header (and `Stable tag`), merge to
`main`. The release job tags and publishes the GitHub release and, when
`WPORG_DEPLOY` is on, the deploy job pushes it to SVN.

Manual alternative (no CI): `svn co https://plugins.svn.wordpress.org/third-party-embed-gate`,
copy the built files into `trunk/`, copy `.wordpress-org/*` into `assets/`,
`svn cp trunk tags/<version>`, `svn ci`.

## Regenerating assets

- Screenshots: boot a backend (`bash tests/wp/serve-playground.sh`) and run
  `node bin/capture-screenshots.cjs` → `.wordpress-org/screenshot-1..4.png`.
  Screenshot 5 (block-editor control) is best captured by hand from a real
  editing session.
- Translations: `php tests/bin/generate-pot.php`.
