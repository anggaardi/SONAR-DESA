# features/

One folder per module (`audit/`, `project/`, `report/`, `review/`, `demo/`): components and composables used by that module's pages.

- A feature folder must not import from another feature folder. Shared code goes to `components/ui` or `lib/` (PRD Arsitektur 9.4).
- `pages/` only composes. Logic lives here.
