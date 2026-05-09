# Desktop bridge artifacts

Files served to the BlastR Desktop bridge at runtime.

| File | Purpose |
|------|---------|
| `addon.zip` | The WoW addon bundle the bridge installs (BlastR + BlastR_RCLootCouncil). Streamed by `GET /api/desktop/addon` to authenticated bridges. |
| `addon-version.txt` | Single-line version string, source of truth for the manifest's `addon.latest_version`. |

## Publishing a new addon release

```bash
./tools/build-addon.sh                  # zips the source + bumps the version file from the .toc
git add resources/desktop/
git commit -m "Addon vX.Y.Z"
# deploy → bridges pick it up on next heartbeat
```

The zip is computed from `external/desktop/internal/wow/addonfiles/`. Its
sha256 is calculated on the fly by `AddonReleaseService` (cached by file
mtime), so committing a new zip is enough — no env edits, no DB rows.
