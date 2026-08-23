# CLAUDE.md

Guidance for Claude Code (and other AI assistants) working in this repository.

## Status of this document

**This repository is currently an empty scaffold.** As of commit `5c83999` ("Initial
commit", 2026-08-22) the entire tracked contents are:

```
LICENSE      Apache License 2.0
README.md    single heading line: "# xxx-tms-idispecink.cz"
```

There is no source code, no build system, no dependency manifest, no test suite, no
CI configuration, and no application structure. Everything below is therefore split
into two parts:

1. **Verified facts** — things that are true of the repository right now.
2. **Conventions and placeholders** — the working agreement for how code should land,
   and the sections that must be filled in as it does.

Do not treat any placeholder section as a description of existing code. If a section
says "not yet established", that is the literal current state — say so to the user
rather than guessing at an architecture that does not exist.

## Verified facts

| | |
|---|---|
| Repository | `doprava-id/xxx-tms-idispecink.cz` (GitHub, private) |
| Remote | `https://github.com/doprava-id/xxx-tms-idispecink.cz` |
| Default branch | `main` |
| License | Apache License 2.0 (`LICENSE`, unmodified boilerplate) |
| Sole commit author | `doprava-id <doprava@idispecink.cz>` |
| Language/runtime | none committed |

### Domain context (inferred, unconfirmed)

The repository name reads as **TMS** (Transport Management System) for
**iDispečink.cz**, a Czech road-freight dispatching operation. The `xxx-` prefix
reads as a placeholder in the name, not a released product name. Nothing in the
committed files confirms any of this — treat it as a working hypothesis and confirm
with the user before building on it.

Practical consequences if that reading holds:

- The working language is **Czech**. Domain vocabulary (`přeprava` = shipment,
  `zásilka` = consignment, `řidič` = driver, `nakládka`/`vykládka` = loading/unloading,
  `dispečink` = dispatching, `objednávka` = order, `dopravce` = carrier) will show up
  in requirements, and probably in UI strings and data.
- User-facing text, documents and reports should be written in Czech unless the user
  says otherwise. Code identifiers, comments and commit messages default to English —
  but if the first real code that lands uses Czech identifiers, follow that instead
  and update this file.
- Likely integration surfaces, based on how the surrounding iDispečink.cz tooling
  works: Airtable (shipment records), Trello (daily dispatch boards), Blue Yonder TMS
  (ESA / Wellpack-Chep imports), Excel exports, and PDF/Word transport orders. None of
  these are wired into this repository yet.

## Conventions

### Branching and pushing

- Develop on a feature branch; never commit directly to `main`.
- AI-assistant sessions use the branch handed to them in the task setup
  (for this session: `claude/claude-md-documentation-coq8vc`). Do not push to a
  different branch without explicit permission.
- Push with `git push -u origin <branch-name>`. On network failure, retry with
  exponential backoff (2s, 4s, 8s, 16s) — do not switch branches to work around a
  push error.
- Open a pull request only when the user explicitly asks for one.
- If a branch's PR has already been merged, restart the branch from the latest `main`
  (`git fetch origin main && git checkout -B <branch> origin/main`) rather than
  stacking new commits on merged history.

### Commits

- One logical change per commit; imperative mood subject line.
- Write the subject in English, ≤72 characters, no trailing period.
- Never put a model identifier (`claude-opus-5`, marketing names, etc.) in a commit
  message, PR title/body, code comment, or any other artifact pushed to the repo.

### Code style

Not yet established — no code exists. The first substantive change to land should:

1. commit a formatter/linter config alongside the code (e.g. `.editorconfig` plus the
   ecosystem standard: `ruff`/`black` for Python, `prettier`/`eslint` for TS/JS),
2. commit a dependency manifest and lockfile,
3. add the corresponding **Development workflow** commands to this file.

Until then, match whatever the surrounding file does and do not invent a house style.

### Secrets and credentials

This is an operational dispatching system touching carrier, driver and customer data.

- Never commit credentials, API keys, Airtable/Trello tokens, Blue Yonder logins, or
  customer/driver personal data. Use environment variables and commit a
  `.env.example` with keys and empty values.
- Add a `.gitignore` covering `.env`, local data exports, and generated
  `.xlsx`/`.pdf` artifacts before any pipeline code lands.
- Real shipment, driver or pricing data belongs in fixtures that are anonymized, not
  in the repository as-is.

## Development workflow

Not yet established. There is nothing to install, build, run, or test.

When tooling lands, replace this section with the actual commands, verified by running
them — not copied from a template:

```
Install     <command>
Run (dev)   <command>
Test        <command>
Lint        <command>
Typecheck   <command>
Build       <command>
```

Until this section is filled in, do not tell the user that tests or builds pass —
there is no test or build step to run.

## Repository structure

```
.
├── CLAUDE.md    this file
├── LICENSE      Apache License 2.0
└── README.md    placeholder heading only
```

That is the complete tree. When directories appear, document what each one is *for*,
not just that it exists.

## Working agreements for AI assistants

- **Verify before asserting.** Because the repository is empty, almost any question
  about "how this codebase does X" currently has the answer "it doesn't yet". Check
  with `git ls-files` before describing structure.
- **Do not scaffold uninvited.** Do not add frameworks, CI workflows, Docker files, or
  directory trees because a repository "should" have them. Build what the user asks
  for.
- **Keep this file honest.** Whenever a change makes a statement here false — the
  first real code, a test runner, a chosen language — update the affected section in
  the same commit. A stale CLAUDE.md is worse than a short one.
- **Scope of GitHub access.** This session is scoped to `doprava-id/xxx-tms-idispecink.cz`.
  Do not read from or write to other repositories unless they are explicitly added.
