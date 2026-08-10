# WP Embed — a two-click embed plugin for WordPress

Hold third-party embeds until the visitor asks for them, so nothing is
contacted and nothing is stored before a click. No cookie banner, no
subscription, no consent platform.

**Status: not started.** This repository currently contains one thing — the
implementation plan.

## Start here

**[`PLAN.md`](PLAN.md)** is the founding document. It specifies the
architecture, the provider model, the customisation surface, the accessibility
contract, seventeen edge cases, the testing strategy, and the `CLAUDE.md` the
repository should carry once work begins.

It is written to be executed from, not just read. A few sections are load-bearing
and worth reading before writing any code:

| Section | Why it matters |
|---|---|
| §1 Invariants | Ten rules whose failure modes are all silent — the plugin looks like it works while not working |
| §3.2 Minified HTML | The trap that makes most competing implementations fail in the field |
| §5.3 Aspect ratio | Why a naive placeholder renders under a full-width empty gap |
| §8 Accessibility | Requirements table; the panel is named with `role="group"`, never a heading |
| §12 `CLAUDE.md` spec | What that file must contain, to be written as part of M1 |

## Where this comes from

The plan generalises a working implementation on a live WordPress site, where
the pattern currently gates 40 embeds across 22 pages with zero third-party
requests before interaction. Every measurement quoted in `PLAN.md` is real,
taken from that site.

Highlights, since they are the reason the project exists:

- `www.youtube.com/embed/…` sets **5 cookies** on a plain GET with no playback
  and no scripts run — two of them ~18-month identifiers.
- `www.youtube-nocookie.com/embed/…` sets **none**.
- A plain WordPress-to-WordPress oEmbed preview set a cookie with a **one-year**
  lifetime.

## Scope

Ship **M1** first: the core gate, the fixture corpus, and the end-to-end test
that asserts zero third-party requests before the click. That alone is a useful
plugin. Milestones are in §13.

## Licence

GPLv2 or later, to match WordPress and to keep the WordPress.org route open.
