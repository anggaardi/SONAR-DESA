# components/ui

Base components written by the team (A13: no starter kit or component library):
- `Button`: Base action control (primary, secondary, ghost, danger) with mobile h-11 and link support.
- `Card`: Surface container with mobile/desktop padding and subtle surface option.
- `Badge`: Status chip supporting semantic colors (positive, negative, warning, info, demo, neutral).
- `Alert`: Status feedback box with semantic icons and actionable message.
- `PriceLabel`: Specialized badge for RAB checks (WithinStandard, AboveStandard, NeedsCheck, NoReference).
- `Table`: Accessible responsive data table with row highlighting and mobile card collapsing.
- `Dialog`: Accessible native `<dialog>` wrapper with keyboard trap and overlay.
- `SummaryList`: GOV.UK-style definition list for project summaries and audit proofs.
- `EmptyState`: Human-friendly empty state with IDDS line-art spot illustrations (plang desa, map arah, search, segel).

Rules:
- No domain words, no route calls. A component here must not know what an "audit" is.
- PriceLabel always shows text and color, never color alone.
- Pure semantic tokens from `resources/css/app.css` only.
