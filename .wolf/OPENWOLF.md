# OpenWolf Operating Protocol

You are working in an OpenWolf-managed project. These rules apply every turn.

## File Navigation

1. Check `.wolf/anatomy.md` BEFORE reading any file. It has a 2-3 line description and token estimate for every file in the project.
2. If the description in anatomy.md is sufficient for your task, do NOT read the full file.
3. If a file is not in anatomy.md, search with Grep/Glob, then update anatomy.md with the new entry.

## Code Generation

1. Before generating code, read `.wolf/cerebrum.md` and respect every entry.
2. Check the `## Do-Not-Repeat` section — these are past mistakes that must not recur.
3. Follow all conventions in `## Key Learnings` and `## User Preferences`.

## After Actions

1. After every significant action, append a one-line entry to `.wolf/memory.md`:
   `| HH:MM | description | file(s) | outcome | ~tokens |`
2. After creating, deleting, or renaming files: update `.wolf/anatomy.md`.

## Cerebrum Learning (MANDATORY — every session)

OpenWolf's value comes from learning across sessions. You MUST update `.wolf/cerebrum.md` whenever you learn something useful. This is not optional.

**Update `## User Preferences` when the user:**
- Corrects your approach ("no, do it this way instead")
- Expresses a style preference (naming, structure, formatting)
- Shows a preferred workflow or tool choice
- Rejects a suggestion — record what they preferred instead
- Asks for more/less detail, verbosity, explanation

**Update `## Key Learnings` when you discover:**
- A project convention not obvious from the code (e.g., "tests go in __tests__/ not test/")
- A framework-specific pattern this project uses
- An API behavior that surprised you
- A dependency quirk or version constraint
- How modules connect or data flows through the system

**Update `## Do-Not-Repeat` (with date) when:**
- The user corrects a mistake you made
- You try something that fails and find the right approach
- You discover a gotcha that would trip up a fresh session

**Update `## Decision Log` when:**
- A significant architectural or technical choice is made
- The user explains why they chose approach A over B
- A trade-off is explicitly discussed

**The bar is LOW.** If in doubt, add it. A cerebrum entry that's slightly redundant costs nothing. A missing entry means the next session repeats the same discovery process.

## Bug Logging (MANDATORY)

**Log a bug to `.wolf/buglog.json` whenever ANY of these happen:**
- The user reports an error, bug, or problem
- A test fails or a command produces an error
- You fix something that was broken
- You edit a file more than twice to get it right
- An import, module, or dependency is missing or wrong
- A runtime error, type error, or syntax error occurs
- A build or lint command fails
- A feature doesn't work as expected
- You change error handling, try/catch blocks, or validation logic
- The user says something "doesn't work", "is broken", or "shows wrong X"

**Before fixing:** Read `.wolf/buglog.json` first — the fix may already be known.

**After fixing:** ALWAYS append to `.wolf/buglog.json` with this structure:
```json
{
  "id": "bug-NNN",
  "timestamp": "ISO date",
  "error_message": "exact error or user complaint",
  "file": "file that was fixed",
  "root_cause": "why it broke",
  "fix": "what you changed to fix it",
  "tags": ["relevant", "keywords"],
  "related_bugs": [],
  "occurrences": 1,
  "last_seen": "ISO date"
}
```

**The threshold is LOW.** When in doubt, log it. A false positive in the bug log costs nothing. A missed bug means repeating the same mistake later.

## Token Discipline

- Never re-read a file already read this session unless it was modified since.
- Prefer anatomy.md descriptions over full file reads when possible.
- Prefer targeted Grep over full file reads when searching for specific code.
- If appending to a file, do not read the entire file first.

## Design QC

When the user asks you to check, evaluate, or improve the design/UI of their app:

1. Run `openwolf designqc` via Bash to capture screenshots.
   - The command auto-detects a running dev server, or starts one from package.json if needed
   - Use `--url <url>` only if auto-detection fails
   - The command saves compressed JPEG screenshots to `.wolf/designqc-captures/`
   - Full pages are captured as sectioned viewport-height images (top, section2, ..., bottom)
2. Read the captured screenshot images from `.wolf/designqc-captures/` using the Read tool.
3. Evaluate the design against modern standards (Shadcn UI, Tailwind, clean React patterns):
   - Spacing and whitespace consistency
   - Typography hierarchy and readability
   - Color contrast and accessibility (WCAG)
   - Visual hierarchy and focal points
   - Component consistency
   - Whether the design looks "dull" or "white-coded" (generic, no personality)
4. Provide specific, actionable feedback with fix suggestions.
5. If the user approves, implement the fixes directly in their code.
6. After fixes, re-run `openwolf designqc` to capture new screenshots and verify improvement.

**Token awareness:** Each screenshot costs ~2500 tokens. The command compresses images (JPEG quality 70, max width 1200px) to minimize cost. For large apps, use `--routes / /specific-page` to limit captures.

## Reframe — UI Framework Selection

When the user asks to change, pick, migrate, or "reframe" their project's UI framework:

1. Read `.wolf/reframe-frameworks.md` for the full framework knowledge base.
2. Ask the user the decision questions from the file (current stack, priority, Tailwind usage, theme preference, app type). Stop early once the choice narrows to 1-2 options.
3. Present a recommendation with reasoning based on the comparison matrix.
4. Once the user confirms, use the selected framework's prompt from the file — **adapted to the actual project** using `.wolf/anatomy.md` for real file paths, routes, and components.
5. Execute the migration: install dependencies, update config, refactor components.
6. After migration, run `openwolf designqc` to verify the new look.

**Do NOT read the entire reframe-frameworks.md into context upfront.** Read the decision questions and comparison matrix first (~50 lines). Only read the specific framework's prompt section after the user chooses.

## Session End

Before ending or when asked to wrap up:

1. Write a session summary to `.wolf/memory.md`.
2. Review the session: did you learn anything? Did the user correct you? Did you fix a bug? If yes, update `.wolf/cerebrum.md` and/or `.wolf/buglog.json`.
