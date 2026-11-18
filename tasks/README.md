# tasks/

Satu file per modul: `m0-core.md`, `m1-standard.md`, ..., `m7-release.md` (PRD Arsitektur 8, 13.1).
ID task: `M2-03`. Ukuran: **S** <= 2 jam, **M** <= 4 jam, **L** <= 1 hari. Task lebih besar dari L wajib dipecah.

| Modul | File | Owner | Jadwal | Fitur (PRD Fitur Inti) |
| --- | --- | --- | --- | --- |
| M0 Core | `m0-core.md` | Dev 1 | M0a 20-22 Nov, M0b 23-24 Nov | Lintas fitur (8) |
| M1 Standard | `m1-standard.md` | Dev 2 | 23-24 Nov | 3.5 |
| M2 Audit | `m2-audit.md` | Dev 2 | 23 Nov - 1 Des | Fitur 1 (A1-A15) |
| M3 Project | `m3-project.md` | Dev 1 | 23-26 Nov | Fitur 2 (P1-P12) |
| M4 Report | `m4-report.md` | Dev 1 | 27 Nov - 3 Des | Fitur 3 (C1-C12) |
| M5 Review | `m5-review.md` | Dev 2 | 2-5 Des | Fitur 4 (B1-B10) |
| M6 Demo | `m6-demo.md` | Dev 1 | pintu di S1; data 4-5 Des | Fitur 5 (M1-M8) |
| M7 Release | `m7-release.md` | Berdua | 12-17 Des | S5 PRD induk |

## Template task

```markdown
### M2-02 · PriceLabeler + TotalChecker
- Status: todo | doing | review | done · Ukuran: M · Owner: Dev 2 · Depends on: M1-04
- Spec: PRD Fitur Inti 3.4, 9 · PRD Arsitektur 6.5, 6.6, 9.2
- Tujuan: label, persen, selisih, dan cek total dihitung kelas PHP murni.

Scope
- ...
Out of scope
- konversi satuan (OUT PRD induk)
Files
- app/Modules/Audit/Domain/PriceLabeler.php, tests/Unit/Audit/PriceLabelerTest.php
Acceptance criteria
- [ ] F2-1 ...
Verify
- make test ARGS="--filter=PriceLabeler"
```

## Aturan

- Setiap task menunjuk bagian spec yang dikerjakan.
- Penyimpangan dari spec dicatat sebagai entri D-xxx di `docs/DECISIONS.md`.
- Definition of Done dan Definition of Ready ada di `docs/ENGINEERING.md` bagian 8-9.
- Setelah 12 Des (freeze fitur): hanya task `fix/`, `perf/`, `style/`, `docs/`.
