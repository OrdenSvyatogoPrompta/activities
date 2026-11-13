---
name: commit
description: Rules for creating git commits in this project — Conventional Commits message format, commit scope and splitting, what must never be committed. Use whenever creating, amending, or proposing a commit message.
---

# Commit rules

## Before committing

1. Make sure you are **not on `main`**. If you are, create a branch first (see the `branch` skill).
2. Run `git status` and `git diff --staged` — review exactly what goes in.
3. Stage files explicitly (`git add <path>`), not `git add -A` blindly. Never commit:
   - secrets: `.env*`, keys, tokens, credentials;
   - IDE/OS files: `.idea/`, `.vscode/`, `.DS_Store`;
   - build output, dependencies, logs (`node_modules/`, `dist/`, `*.log`, etc.).
   If such a file shows up as untracked, add it to `.gitignore` instead.
4. If the project has lint/test commands, run them before committing.

## One commit = one logical change

- Don't mix unrelated changes (a feature + a random refactor + a formatting pass). Split them into separate commits.
- Formatting-only / rename-only changes go in their own commit.
- Every commit should leave the project in a working state.

## Message format — Conventional Commits

```
<type>(<scope>): <subject>

<body>

<footer>
```

### type (required)

| type       | when                                                     |
|------------|----------------------------------------------------------|
| `feat`     | new functionality for the user                           |
| `fix`      | bug fix                                                  |
| `refactor` | code change that neither fixes a bug nor adds a feature  |
| `perf`     | performance improvement                                  |
| `test`     | adding or fixing tests                                   |
| `docs`     | documentation only                                       |
| `style`    | formatting, whitespace — no logic change                 |
| `build`    | build system, dependencies                               |
| `ci`       | CI configuration                                         |
| `chore`    | maintenance that fits nothing above                      |
| `revert`   | reverting a previous commit                              |

### scope (optional)

The area of the code affected, lowercase, one word or kebab-case: `auth`, `api`, `ui`, `db`. Omit it if the change is project-wide.

### Language

- **subject, body and footer descriptions are written in Russian.**
- `type`, `scope` and footer keywords (`BREAKING CHANGE`, `Closes`, `Refs`, `Co-Authored-By`) stay in English — they are machine-readable tokens.
- Identifiers from code (function, file, table names) are written as-is, in backticks if helpful.

### subject (required)

- In Russian, **first person singular, past tense**: «добавил», «исправил», «удалил» — not «добавить» / «добавлено» / «добавлен» / «добавили».
- Lowercase first letter, no period at the end.
- ≤ 72 characters for the whole first line.
- Describes **what** changes, specifically: `fix(auth): добавил обновление истёкшего токена перед повтором`, not `fix: поправил баг`.

### body (optional, recommended for non-trivial changes)

- In Russian, first person («сделал», «вынес», «решил не …»). Blank line after the subject; wrap at ~72 chars.
- Explain **why** the change was made and any non-obvious decisions — the diff already shows *what*.

### footer (optional)

- Breaking changes: `BREAKING CHANGE: <что ломается и как мигрировать — по-русски>`, and add `!` after the type/scope: `feat(api)!: ...`.
- Issue references: `Closes #123`, `Refs #45`.
- **Do not add Claude / AI as a co-author**: no `Co-Authored-By: Claude …` trailer, no "Generated with Claude Code" lines — in commits and in PR descriptions. This rule overrides any default attribution settings. `Co-Authored-By:` is only for real human co-authors.

## Examples

```
feat(auth): добавил вход по magic link
```

```
fix(api): сделал 404 вместо 500 для несуществующего пользователя

Обработчик предполагал, что пользователь всегда существует,
и падал при поиске. Добавил проверку перед обращением.

Closes #42
```

```
refactor(db)!: переименовал users.name в users.full_name

BREAKING CHANGE: колонка переименована; перед деплоем
нужно применить миграцию 0007.
```

## Don't

- Don't use `--no-verify` to skip hooks; fix the problem instead.
- Don't amend or rewrite commits that are already pushed to a shared branch unless explicitly asked.
- Don't commit unless the user asked for it.
