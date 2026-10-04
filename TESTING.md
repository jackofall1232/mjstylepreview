# Verification record

Executed October 4, 2026. The installable ZIP contains only `mj-style-preview/`, not these fixtures.

## TESTED

- Syntax: all six plugin PHP files passed `php -l` on PHP 8.4.24. Plugin execution tested on PHP 8.3.35.
- Actual isolated WordPress 7.1.2 + MariaDB 11: activation without plugin fatal errors; shortcode and scoped assets; scheduled cleanup; administrative credential masking; bounded settings and permission checks.
- 27 WordPress integration assertions: positive hair extraction, excluded raw injected instructions, unsupported/empty/overlong/negated descriptions, signed/forged browser tokens, browser-bound nonces, same/foreign origin checks, atomic lock acquisition, concurrency denial, cooldown, browser/IP/daily caps, stale-photo cleanup and deactivation. These check enforcement sequentially, not a concurrent stress benchmark.
- 30 HTTP assertions with real PHP multipart uploads and a mocked OpenAI transport: consent/nonce/origin rejection; spoofed image/SVG/oversized/undersized rejection; JPEG/PNG/WebP acceptance; upstream timeout/429/401/invalid base64 handling; success; no raw upstream details in responses; no-store headers; cooldown; temporary-file removal on success and failures; EXIF orientation 6 corrected; longest edge reduced to 1,536; source EXIF removed.
- JavaScript syntax via `node --check`.
- 36 Chromium/Playwright checks at 375, 390, 768 and 1280px: unchecked consent, upload and mocked result display, no horizontal overflow, result and retry focus, primary touch target size, reduced-motion configuration and no JavaScript runtime exceptions. Rendered shortcode HTML and plugin CSS/JS were used without theme CSS. Visually inspected the narrow layout screenshot. Synthetic colored images were used, not real AI output.
- Uninstall in the actual WordPress fixture removed plugin options, DB transients, cron and private storage while preserving an unrelated option.
- ZIP integrity and PHP syntax checked before delivery; test fixture files and test API credentials are excluded.

The test server uses a local HTTP connection with a simulated server HTTPS flag and Origin to exercise server logic. This is not an end-to-end production TLS/cookie deployment test. The browser fixture mocks AJAX responses. Neither fixture calls OpenAI.

## REQUIRES LIVE VERIFICATION

1. Real `gpt-image-2.5-flare` account access, billing, generation latency, model fidelity, and realistic hair/identity preservation. The official API specification was verified, but no paid model request was made.
2. Bluehost GD/EXIF availability, PHP/Apache time and memory limits, upload/post limits, outbound HTTPS, private temporary directory, actual cron execution, and any proxy/CDN behavior.
3. Real HTTPS cookies and session lifecycle, CDN cache exclusions, error recovery after interrupted mobile connections, and trusted proxy IP configuration.
4. Kadence/custom child theme and installed plugins, actual iPhone Safari and Samsung Chrome photo selection, HEIC-to-JPEG workflows, screen-reader testing and a full accessibility audit.
5. WordPress 6.5/PHP 8.1 minimum-version matrix, alternate models, multisite per-site installations, and persistent external object caches. Network activation is intentionally unsupported.
6. Real load/race testing under concurrent traffic. Local functional lock tests verify serial exclusion, not production database/worker stress.
7. OpenAI's current retention controls and the site's privacy notice. Local deletion does not establish service-side deletion.

## Reproduce in the cloud workspace

Use the existing checkout at `/workspace/mjstylepreview`. Do not create a worktree. Docker and the workspace's Node/Playwright/Chromium are prerequisites. These fixtures target only disposable containers named `mjsp-wp` and `mjsp-db`; never aim them at production. The test database and admin passwords are intentionally non-secret local fixtures.

```bash
bash tests/install.sh
bash tests/start.sh
/workspace/tools/test-venv/bin/python tests/http-integration.py
node --check mj-style-preview/assets/js/preview.js
```

For browser tests, run `python3 -m http.server 8091 --bind 127.0.0.1` from the repository in a separate terminal, then `node tests/browser.cjs`. Only internal local requests are used. Start instructions generate the fixture JPEG and shortcode HTML.

To check syntax using the retained WordPress container:

```bash
docker exec mjsp-wp sh -c 'find /var/www/html/wp-content/plugins/mj-style-preview -name "*.php" -exec php -l {} \;'
```

Uninstall test (run last; it deletes the fixture's plugin configuration):

```bash
docker exec -u www-data mjsp-wp php /tmp/mjsp-uninstall.php
```

Rerun `tests/start.sh` to restore the test configuration. Stop your fixture services with `docker stop mjsp-wp mjsp-db`; containers retain their disposable database/files. The scripts never modify WordPress core, Kadence or the target site's database. Tests and mock API hooks are outside the ZIP and must never be installed on a public site.
