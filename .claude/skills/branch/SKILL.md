---
name: branch
description: Rules for git branches and pull requests in this project — branch naming, creating branches from main, keeping them up to date, pushing and opening PRs. Use whenever creating, renaming, pushing a branch or opening a PR.
---

# Branch & PR rules

## Main branch

- `main` is the only long-lived branch and is always in a working state.
- **Never commit or push directly to `main`.** All work goes through a branch + PR.

## Branch naming

```
<type>/<short-description>
<type>/<issue-id>-<short-description>   # when there is a ticket/issue
```

- `type` — same set as commit types: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `build`, `ci`, `chore`. Use `hotfix` for urgent production fixes.
- `short-description` — English, lowercase, kebab-case, 2–5 words, no more than ~40 chars.
- Only `a-z`, `0-9`, `-` and the single `/`. No spaces, underscores, uppercase or Cyrillic.

Examples:

```
feat/magic-link-login
fix/42-user-404
refactor/split-api-handlers
chore/update-deps
```

Bad: `my-branch`, `fix`, `Feature/Login`, `new_stuff`, `eric/test`.

## Creating a branch

```bash
git checkout main
git pull --ff-only
git checkout -b <type>/<short-description>
```

One branch = one task. If unrelated work comes up, start a separate branch from `main`.

## Keeping a branch up to date

- Prefer rebasing a personal branch onto fresh `main`:
  ```bash
  git fetch origin
  git rebase origin/main
  ```
- After a rebase of an already-pushed branch, push with `git push --force-with-lease` (never plain `--force`).
- Don't rebase branches other people are working on — merge `main` into them instead.

## Pushing and opening a PR

```bash
git push -u origin <branch>
gh pr create --base main --title "<title>" --body "<body>"
```

- Push and open PRs only when the user asked for it.
- **PR title** follows the commit format, in Russian: `<type>(<scope>): <subject>` — it becomes the squash-merge commit message (see the `commit` skill).
- **PR body** — in Russian:
  ```markdown
  ## Что
  Краткое описание изменения.

  ## Зачем
  Проблема или мотивация. Ссылки на задачи: Closes #123.

  ## Как проверить
  Шаги для проверки изменения.
  ```
- Keep PRs small and focused; split large changes into several PRs.

## After merge

- Merge strategy: squash merge into `main`.
- Delete the branch after merge (remote and local):
  ```bash
  git checkout main && git pull --ff-only
  git branch -d <branch>
  ```
