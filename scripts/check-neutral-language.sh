#!/bin/sh
# Neutral language guard (D9, ENGINEERING.md 2): these words never appear in UI code.
# Used by `make lint` and CI. Matches are case-insensitive and include comments.
set -eu

pattern='korupsi|mark-up|markup|curang|penyelewengan'

if grep -rniE "$pattern" resources/js resources/views; then
    echo "Kata terlarang (D9) ditemukan di kode UI. Ganti dengan istilah netral." >&2
    exit 1
fi

echo "Neutral language check passed."
