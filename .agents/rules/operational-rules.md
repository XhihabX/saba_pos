---
trigger: always_on
---

# Operational Rules — Read Before Every Task

These rules apply to every request in this project, without exception. Filter every instruction I give against these rules before acting.

## 1. Mandatory Implementation Plan First
Never jump straight into writing or modifying code. Always produce a detailed Implementation Plan first and wait for my approval before touching any file.

## 2. Interactive Clarifications
If anything about my request is ambiguous, underspecified, or could be interpreted more than one way, ask me directly inside the plan. Do not guess silently and proceed.

## 3. Impact & Risk Analysis (mandatory section in every plan)
Every plan must explicitly state:
- What existing features/files this change touches or could break
- What could go wrong (specific risks, not generic disclaimers)
- What improves as a result
- Overall effect on site stability

## 4. Plain-Language Explanations
Alongside any technical explanation, include a plain-language version — I am not a formally trained developer. Assume I need both: the technical accuracy AND the "what this means for my site" translation.

## 5. Documentation Is Mandatory, Not Optional
After every implemented change (feature added, function modified, bug fixed):
- Update the relevant section of `docs/ARCHITECTURE.md` to reflect the current state (edit in place — this file always describes the system as it is NOW, not a history)
- Append a new entry to `docs/CHANGELOG.md` using the required template (see that file) — never skip this step, even for small changes
- If `docs/ARCHITECTURE.md` or `docs/CHANGELOG.md` don't exist yet, create them using the templates in this project before doing anything else

## 6. Read Documentation Before Acting
At the start of any task, read `docs/ARCHITECTURE.md` and the most recent entries in `docs/CHANGELOG.md` first — this is how continuity survives a lost chat history or a switch to a different AI model. Never assume you remember the codebase from a previous session; verify against these files.

## 7. Coding Standards & Safety Guardrails
- Never hardcode secrets/API keys — always environment variables
- Don't modify or remove existing working functionality unless the plan explicitly says so and I've approved it
- Prefer small, isolated changes over large sweeping rewrites when adding a feature to an existing codebase
- Flag any code smell or fragile pattern you notice while working, even if unrelated to the current task, rather than silently working around it
- If a change requires touching more than a few files, break it into smaller sub-steps within the plan rather than one giant diff