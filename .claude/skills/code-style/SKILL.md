---
name: code-style
description: Rules for writing and cleaning up code in this project — concise comments, no one-off or duplicated code. Use whenever writing, generating, reviewing or refactoring code (backend or frontend), including work delegated to subagents.
---

# Code style rules

## Comments

- Comment **why**, not **what**. If the code reads clearly, write no comment.
- One line where possible; never more than 2–3. No essays, no restating the contract or requirements doc.
- No comments that narrate the task or history: «группа B», «по требованию раздела 3»,
  «добавил для фронта», «TODO для группы C», «как в моках» — that belongs in commits and docs.
- No section banners, no docblocks that repeat the signature or types already declared.
- PHPDoc only for what types can't express (`list<Club>`, array shapes, generics).
- Language: Russian, matching the rest of the code.

## No one-off code

- No code that exists only for a single past situation: migration helpers, debug output,
  probes, temporary flags, fallbacks for versions we don't use.
- No duplicates: before adding a DTO, formatter, helper, test trait or style, search for an existing one
  and reuse it. One response format per resource, one place for a rule.
- No speculative API: don't add methods, options, parameters or endpoints nobody calls yet.
- Remove dead code instead of commenting it out: unused methods, imports, variables, styles, constants.
- Tests share helpers through one common base class/trait, not a copy per test folder.

## Before finishing

- Search the diff for new comments and cut each to the minimum (or delete it).
- Check that every new public method/class has a caller.
