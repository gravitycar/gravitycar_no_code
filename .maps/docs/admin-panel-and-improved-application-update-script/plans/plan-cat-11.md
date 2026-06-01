# Implementation Plan: CAT-11 — Migrate Build/Deploy Scripts + Deprecate setup.php

## Spec Context

This plan covers the caller-migration phase of the admin panel epic: updating two shell scripts
to call `scripts/application-update.php` instead of `setup.php`, and adding a deprecation
comment to `setup.php`. No logic changes are made to `setup.php`. The new
`scripts/application-update.php` (CAT-10) must already exist before these changes take effect.

Catalog item: CAT-11  
Specification section: "Migration of setup.php Callers" (AC-43 through AC-46)  
Acceptance criteria addressed:
- AC-43: `build-backend.sh` lines 319, 320, 326 call `scripts/application-update.php`.
- AC-44: `transfer.sh` line 232 `chmod` target changes from `scripts/setup.php` (wrong) to `scripts/application-update.php` (correct).
- AC-45: `transfer.sh` line 246 calls `scripts/application-update.php` instead of `setup.php`.
- AC-46: `setup.php` receives a deprecation comment at the top; file is NOT deleted.

---

## Dependencies

- **Blocked by**: CAT-10 (`scripts/application-update.php` — must exist before build scripts call it)
- **Blocks**: nothing (this is the final backend catalog item)
- **Uses**: existing shell scripts and `setup.php` in the repository root

---

## File Changes

### New Files
- none

### Modified Files
- `scripts/build/build-backend.sh` — update `rebuild_cache()` function (lines 319, 320, 326)
- `scripts/deploy/transfer.sh` — fix chmod path bug and update script call (lines 232, 246)
- `setup.php` — add deprecation comment block at top (after opening `<?php` tag)

---

## Implementation Details

### 1. `scripts/build/build-backend.sh` — `rebuild_cache()` function

**Current code (lines 315–328):**

```bash
# Run cache rebuild if needed
rebuild_cache() {
    log "INFO" "Rebuilding framework cache..."
    
    if [[ -f "setup.php" ]]; then
        if php setup.php; then
            log "SUCCESS" "Framework cache rebuilt successfully"
        else
            log "WARN" "Cache rebuild failed, but continuing build"
        fi
    else
        log "DEBUG" "setup.php not found, skipping cache rebuild"
    fi
}
```

**Exact lines to change:**

| Line | Old content | New content |
|------|-------------|-------------|
| 319  | `    if [[ -f "setup.php" ]]; then` | `    if [[ -f "scripts/application-update.php" ]]; then` |
| 320  | `        if php setup.php; then` | `        if php scripts/application-update.php --all --schema --permissions; then` |
| 326  | `        log "DEBUG" "setup.php not found, skipping cache rebuild"` | `        log "DEBUG" "scripts/application-update.php not found, skipping cache rebuild"` |

**New code (lines 315–328 after change):**

```bash
# Run cache rebuild if needed
rebuild_cache() {
    log "INFO" "Rebuilding framework cache..."
    
    if [[ -f "scripts/application-update.php" ]]; then
        if php scripts/application-update.php --all --schema --permissions; then
            log "SUCCESS" "Framework cache rebuilt successfully"
        else
            log "WARN" "Cache rebuild failed, but continuing build"
        fi
    else
        log "DEBUG" "scripts/application-update.php not found, skipping cache rebuild"
    fi
}
```

**Rationale for `--all --schema --permissions` flags:**

These flags are equivalent to the default behavior of `scripts/application-update.php` when
invoked with no arguments (per AC-37). Making the flags explicit in the build script makes
the intent unambiguous for future readers and is consistent with the spec's description of
what the build process should do (rebuild everything, update schema, update permissions).

---

### 2. `scripts/deploy/transfer.sh` — deployment section

**Current code (lines 228–248):**

```bash
        # Set proper permissions
        find /home/$PRODUCTION_USER/public_html/api.gravitycar.com -type f -exec chmod 644 {} \;
        find /home/$PRODUCTION_USER/public_html/api.gravitycar.com -type d -exec chmod 755 {} \;
        
        # Make specific files executable
        chmod +x /home/$PRODUCTION_USER/public_html/api.gravitycar.com/scripts/setup.php 2>/dev/null || true
        
        # Create/update production config
        if [ -f '$REMOTE_TEMP_DIR/config/production.conf' ]; then
            cp '$REMOTE_TEMP_DIR/config/production.conf' /home/$PRODUCTION_USER/public_html/api.gravitycar.com/config.php
        fi
        
        # Update database password in config if provided
        if [ -n '${DB_PASSWORD:-}' ]; then
            sed -i \"s/DB_PASSWORD_PLACEHOLDER/${DB_PASSWORD}/g\" /home/$PRODUCTION_USER/public_html/api.gravitycar.com/config.php 2>/dev/null || true
        fi
        
        # Run framework setup
        cd /home/$PRODUCTION_USER/public_html/api.gravitycar.com
        php setup.php 2>/dev/null || echo 'Setup script had issues but continuing'
```

**Exact lines to change:**

| Line | Old content | New content |
|------|-------------|-------------|
| 232  | `        chmod +x /home/$PRODUCTION_USER/public_html/api.gravitycar.com/scripts/setup.php 2>/dev/null \|\| true` | `        chmod +x /home/$PRODUCTION_USER/public_html/api.gravitycar.com/scripts/application-update.php 2>/dev/null \|\| true` |
| 246  | `        php setup.php 2>/dev/null \|\| echo 'Setup script had issues but continuing'` | `        php scripts/application-update.php --all --schema --permissions 2>/dev/null \|\| echo 'Application update script had issues but continuing'` |

**Bug fix note for line 232:**

The original line attempted to `chmod +x` a file at `scripts/setup.php` relative to the
deployed web root. `setup.php` lives in the project root, not in `scripts/`. This is an
existing bug documented in the spec (AC-44 and Technical Context → Known bugs). The fix
changes the chmod target to `scripts/application-update.php`, which is the correct location
for the new CLI script.

**New code (lines 228–248 after change):**

```bash
        # Set proper permissions
        find /home/$PRODUCTION_USER/public_html/api.gravitycar.com -type f -exec chmod 644 {} \;
        find /home/$PRODUCTION_USER/public_html/api.gravitycar.com -type d -exec chmod 755 {} \;
        
        # Make specific files executable
        chmod +x /home/$PRODUCTION_USER/public_html/api.gravitycar.com/scripts/application-update.php 2>/dev/null || true
        
        # Create/update production config
        if [ -f '$REMOTE_TEMP_DIR/config/production.conf' ]; then
            cp '$REMOTE_TEMP_DIR/config/production.conf' /home/$PRODUCTION_USER/public_html/api.gravitycar.com/config.php
        fi
        
        # Update database password in config if provided
        if [ -n '${DB_PASSWORD:-}' ]; then
            sed -i \"s/DB_PASSWORD_PLACEHOLDER/${DB_PASSWORD}/g\" /home/$PRODUCTION_USER/public_html/api.gravitycar.com/config.php 2>/dev/null || true
        fi
        
        # Run framework setup
        cd /home/$PRODUCTION_USER/public_html/api.gravitycar.com
        php scripts/application-update.php --all --schema --permissions 2>/dev/null || echo 'Application update script had issues but continuing'
```

**Error handling pattern preserved:** The `2>/dev/null || echo '...'` pattern on line 246 is
preserved unchanged (only the command and echo message change). This ensures non-fatal
failures in the update script do not abort the overall deployment.

---

### 3. `setup.php` — deprecation comment block

**Location:** After the opening `<?php` tag (currently line 1), before the existing doc block.

**Current code (lines 1–9):**

```php
<?php
/**
 * Gravitycar Framework Setup Script
 * 
 * This script bootstraps the Gravitycar application, generates the database schema,
 * and creates initial user records including sample data.
 * 
 * Usage: php setup.php
 */
```

**New code (lines 1–21 after change):**

```php
<?php
/**
 * @deprecated This script is deprecated in favour of scripts/application-update.php.
 *
 * DO NOT USE in new code or automation pipelines. Use scripts/application-update.php
 * instead, which provides the same cache rebuild operations with improved error handling,
 * CLI flags, and proper DI container usage.
 *
 * This file will be removed in a future version of the Gravitycar Framework.
 *
 * Replacement usage:
 *   php scripts/application-update.php --all --schema --permissions
 *
 * @see scripts/application-update.php
 */

/**
 * Gravitycar Framework Setup Script
 * 
 * This script bootstraps the Gravitycar application, generates the database schema,
 * and creates initial user records including sample data.
 * 
 * Usage: php setup.php
 */
```

**Important:** No logic changes to `setup.php`. Only the deprecation comment block is added.
Do NOT modify any `require_once`, class definitions, function calls, or any other code in
the file.

---

## Other References to `setup.php` in the Codebase

A full codebase search was performed. The following files also reference `setup.php` but are
**out of scope for this catalog item** — they are documentation, notes, and other tooling that
the spec does not require updating:

| File | Nature | Action |
|------|--------|--------|
| `docker-entrypoint.sh` (line 55) | Docker first-run script calls `php setup.php` | **Out of scope** — the spec (AC-43 through AC-46) only covers `build-backend.sh` and `transfer.sh`. Docker entrypoint is a separate concern. |
| `.vscode/extensions/gravitycar-tools/package.json` (line 186) | VS Code tool description references `setup.php` | **Out of scope** — documentation/tooling, not a deployment script. |
| `.github/copilot-instructions.md`, `.github/chatmodes/*.md` | Developer documentation | **Out of scope** — docs only. |
| `docs/**/*.md` | Implementation plans, guides, notes | **Out of scope** — historical documentation. |
| `src/Services/OpenAPIPermissionFilter.php` (line 150) | Error message references `setup.php` in a `RuntimeException` | **Out of scope** — this is a test helper error message, not a caller. Future cleanup may update this message. |
| `src/Services/PermissionsBuilder.php` (line 17) | PHPDoc comment references `setup.php` | **Out of scope** — documentation comment only. |

**The only callers that need updating per the spec are:**
1. `scripts/build/build-backend.sh` — lines 319, 320, 326 ✅ (covered above)
2. `scripts/deploy/transfer.sh` — lines 232, 246 ✅ (covered above)

The `docker-entrypoint.sh` reference (line 55: `php setup.php`) is notable. It calls
`setup.php` during Docker first-run setup, which includes user seeding that `application-update.php`
explicitly does NOT perform. This is intentional — the spec states "Do NOT create users or
roles from AdminService or application-update.php; user/role seeding is a separate concern."
Updating `docker-entrypoint.sh` would require a separate user-seeding solution and is therefore
outside this epic's scope.

---

## Error Handling

No new error handling logic is introduced in this catalog item. The existing error handling
patterns in both shell scripts are preserved:
- `build-backend.sh`: `log "WARN"` on failure, continues the build (non-fatal)
- `transfer.sh`: `2>/dev/null || echo '...'` on failure, continues the deployment (non-fatal)

---

## Unit Test Specifications

This catalog item modifies only shell scripts and adds a comment to `setup.php`. There is no
business logic to unit test. No PHPUnit or Selenium tests are required.

**Manual validation steps** (for verification, not automated tests):

1. After applying changes to `build-backend.sh`, run:
   ```bash
   bash -n scripts/build/build-backend.sh
   ```
   Expected: no syntax errors.

2. After applying changes to `transfer.sh`, run:
   ```bash
   bash -n scripts/deploy/transfer.sh
   ```
   Expected: no syntax errors.

3. Confirm `setup.php` still parses as valid PHP:
   ```bash
   php -l setup.php
   ```
   Expected: `No syntax errors detected in setup.php`.

4. Confirm the deprecation comment appears at the top of `setup.php` (after `<?php`).

5. Confirm `setup.php` logic is unchanged by diffing against the previous version — only
   the comment block should appear in the diff.

---

## Notes

- The `--all --schema --permissions` flags in both shell scripts are equivalent to the
  no-argument default of `scripts/application-update.php` (per AC-37). Making them explicit
  is intentional — it makes the deployment intent clear to future maintainers without relying
  on knowledge of the script's default behavior.
- The `chmod +x` on line 232 of `transfer.sh` is technically not needed for a PHP script
  invoked as `php scripts/application-update.php` (PHP interpretes the file regardless of
  execute bit). However the line is preserved as-is (just with the corrected path) to maintain
  consistency with the existing deployment pattern and avoid unintended scope creep.
- The deprecation comment uses `@deprecated` PHPDoc syntax even though `setup.php` is a
  script file (not a class file). This is acceptable and consistent with how PHP tools and
  IDE support deprecation notices. The comment is prominent and unambiguous.
- The plan intentionally does NOT update `docker-entrypoint.sh`. That script calls `setup.php`
  for user seeding, which is functionality that `scripts/application-update.php` does not
  replace. Updating it would require a separate user-seeding solution.
