# MJ Style Preview 1.0.0

A standalone WordPress plugin for MJ Hair Artist. No theme edits, frontend framework, accounts, media-library uploads or gallery. Disabled by default until configured.

## Install and configure

1. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**, select `mj-style-preview.zip`, install and activate. For updates, upload the replacement ZIP; do not uninstall first unless you want settings deleted.
2. Use WordPress 6.5+, PHP 8.1+, HTTPS, GD with JPEG/PNG/WebP support, and EXIF. PHP `upload_max_filesize` and `post_max_size` must accommodate the configured upload (default 10 MB); set both to at least 16 MB. Recommended PHP memory limit: 256 MB. Larger photos may be rejected on low-memory hosts.
3. Under **Settings → MJ Style Preview**, add your OpenAI API key, retain `gpt-image-2.5-flare`, choose limits, and enable previews. The account must have access to this model and sufficient project budget. The key is never redisplayed.
4. Prefer setting `define('MJ_STYLE_PREVIEW_OPENAI_API_KEY', 'YOUR_KEY');` in `wp-config.php` before WordPress loads. Supply the real value only on your server, never in source control. This overrides the database key. A database key is stored unencrypted in the site's options and may appear in database backups; clear it before switching to the constant if desired.
5. Place `[mj_style_preview]` in a Shortcode block on any normal page. No attributes or page ID are required. Keep page and AJAX origin identical; HTTPS is required. Cookies must be permitted. Exclude `mjsp_session` and `mjsp_generate` AJAX responses from CDN/page caches. The shortcode page itself may be cached: session nonces are fetched at runtime.
6. Test on staging before public launch. Allow outbound HTTPS to `api.openai.com`. Confirm Bluehost allows a PHP/HTTP request lasting up to 150 seconds and an overall request window of at least 175 seconds. The plugin does not override host resource limits. If your hosting terminates long requests, v1's synchronous flow needs a different host allowance or a future job-based implementation.
7. Configure a real server cron to execute due WordPress cron events every five minutes (for example your host's WP-CLI `wp cron event run --due-now` with the correct site path). Traffic-triggered WP-Cron alone cannot guarantee a deletion deadline on a quiet site.

## Booking hook

No URL is invented. A site-specific integration plugin can return your existing HTTPS booking URL through `mj_style_preview_booking_url`. Example: `add_filter('mj_style_preview_booking_url', function () { return get_option('your_existing_booking_url', ''); });`. Replace the option name with the actual site's setting. The button remains absent when no valid HTTPS URL is supplied.

## Verified API contract

Verified October 4, 2026 against OpenAI's official published OpenAPI specification:
https://raw.githubusercontent.com/openai/openai-openapi/master/openapi.yaml

The exact identifier **gpt-image-2.5-flare** is listed. No model substitution was made. `POST https://api.openai.com/v1/images/edits` accepts multipart `image`, `prompt`, and `model`. GPT Image accepts JPG, PNG or WebP inputs below 50 MB each. This plugin imposes tighter limits and sends one normalized JPEG, `n=1`, `size=1024x1536`, `quality=medium`, `output_format=jpeg`, `output_compression=85`. It validates `data[0].b64_json` as a JPEG. It does not send deprecated `response_format`, and omits `input_fidelity` because the specification does not document Flare support for that parameter. TLS verification stays enabled; no redirects or arbitrary upstream URLs are allowed.

The prose documentation hosts were blocked in the build environment; the official specification was accessible. Check current model availability and pricing in your account before enabling. Model configuration is centralized and editable, but alternative models still require compatibility testing.

## Hairstyle scope and limitations

Visitors enter a description, but **raw text never reaches OpenAI**. A positive English vocabulary extracts common cuts, lengths, colors, textures, highlights and roots; the server constructs a hair-only instruction from those approved descriptors. It recognizes the requested example, including long/shoulder-length layers, big loose curls, blonde highlights and darker roots. The response displays the details actually applied.

This is a deliberate v1 constraint: unsupported wording is ignored if another supported descriptor is present; wholly unsupported requests and negations are rejected. It is not full unrestricted natural-language understanding. Composite descriptions can be approximate. Review the applied details and do not promise perfect request fidelity. A future structured semantic parser could broaden wording support without passing raw instructions onward.

The protected prompt asks the image model to preserve identity, age, face, body, expression, clothing, lighting, pose and background. Prompting cannot enforce pixel-level invariance. Image content itself can also contain adversarial instructions; no generative model guarantee is claimed. Results may alter unintended details. Consent and the visible salon-achievability disclaimer are part of the interface.

## Privacy: exact data locations and lifetimes

| Data | Location | Lifetime |
| --- | --- | --- |
| Selected original | Browser File object and local blob URL | Until replacement, leaving the page, or the successful preview's expiry; failed attempts retain selection for retry |
| Incoming upload | PHP's host-managed `upload_tmp_dir` | PHP removes it when the request ends; host policy governs cleanup after crashes |
| Normalized JPEG | OS temporary directory in `mjsp-<site-specific hash>/photo-<random>.jpg` | Deleted in `finally` on success or error. Abandoned files become eligible after the configured lifetime, default 10 minutes |
| Generated preview | PHP response memory, then browser blob URL | No plugin disk file or database entry; browser clears after configured lifetime, retry, photo replacement or navigation |
| Prompt | Request memory only | Request lifetime; not saved or logged by this plugin |
| Settings and aggregate usage | WordPress options | Until uninstall; counts retain only current UTC day/month totals when next updated |
| Abuse state | WordPress transients and plugin options | Browser/IP counters expire after an idle hour; cooldown defaults to 60 seconds; locks expire in 5–240 seconds; one current daily count persists |
| Browser token | Secure, HttpOnly, SameSite=Strict cookie | 24 hours |

Only the normalized version of the visitor's original photo is sent to OpenAI—not an unrelated generated person. Normalization corrects JPEG EXIF orientation, removes metadata and limits the longest edge to 1,536 pixels. Incoming images are limited to 256–6,000 pixels per edge and 16 megapixels. HEIC is not supported: export as JPG.

The private directory uses mode 0700 and normalized files 0600. It is outside the WordPress root and known document root. To use a host-approved private directory, define `MJ_STYLE_PREVIEW_TEMP_DIR` as an existing writable directory outside the web root. Never point it at public uploads. If your host aliases directories into its public web server, confirm the private path is not exposed. Changing the configured path, site URL or WordPress salts requires manually cleaning the old plugin-specific directory.

Cleanup is scheduled every five minutes via WP-Cron and runs before image processing; uninstall/deactivation also cleans normalized files. **Without a real cron, abandoned-file retention is not a hard deadline.** Browser timers may be delayed by backgrounded mobile tabs; images can also be retained by users through screenshots/downloads. Plugin deletion cannot erase host snapshots, PHP/web-server infrastructure logs, browser storage internals or OpenAI's systems.

OpenAI receives the image and constructed hair prompt. OpenAI's service retention, abuse monitoring and account-specific controls are separate from this plugin's local deletion. No zero-retention promise is made. Review the current OpenAI data controls and your site's privacy notice before launch.

## Abuse protection and security

Defaults: 3 attempts per browser, 10 per IP-derived hash, 60-second browser cooldown, 30 attempts per UTC day site-wide and 1 simultaneous request. Limits are admin-configurable within bounded ranges. Hourly counters use a conservative sliding idle expiry: ongoing attempts can extend a counter's lifetime. Invalid uploads after reservation and upstream failures consume quota; consent/nonce/prompt validation failures do not.

The global daily count is a persistent option, protected by atomic database option locks. It survives transient eviction. Per-browser/IP counters use transients; cache flushes can reset them. The daily cap still applies. Concurrency locks expire after 240 seconds to recover from crashes. Distributed attacks can consume the site's allowance and deny service, but cannot create an unlimited paid generator through session resets. Set an OpenAI project budget as an independent cost safeguard.

No IP address is stored: only a keyed hash in short-lived rate-limit keys. Hashes and cookies remain pseudonymous abuse data, not anonymous in a strict legal sense. Uses `REMOTE_ADDR` only; it never trusts arbitrary forwarded-IP headers. Behind a proxy, visitors may share the proxy's quota. Configure trusted client-IP restoration at the server if appropriate.

Each generation requires a signed browser cookie, a browser-bound WordPress nonce, a same-origin POST, HTTPS, consent and server validation. Logged-out WordPress nonces alone are not treated as visitor identity. Uploaded files must be real PHP uploads with validated raster MIME, extension, dimensions and bytes. All API requests are server-side. Settings require `manage_options` and WordPress Settings API CSRF protection. Output is escaped; frontend text uses `textContent`.

Optional `MJ_STYLE_PREVIEW_LOG=true` logs UTC time, generic result category and duration to PHP's configured error log. No API key, photo, prompt, raw upstream body or visitor identifier is logged by this plugin. Hosting logs and other plugins are outside its control. Keep WordPress debug display off in production.

## Deactivation and uninstall

Deactivation clears cleanup scheduling and private normalized photos; settings remain for reactivation. Delete/uninstall removes plugin options, DB transients, locks, usage totals, cron and current private storage, preserving unrelated content. External object-cache entries without database keys can remain only until their short TTL expires. The HttpOnly browser cookie expires naturally; server uninstall cannot contact prior visitors' browsers. Per-site activation is supported; network activation is rejected in v1.

## File tree

```text
mj-style-preview/
├── mj-style-preview.php
├── uninstall.php
├── README.md
├── includes/
│   ├── core.php
│   ├── http.php
│   └── admin.php
├── templates/preview.php
└── assets/
    ├── css/preview.css
    └── js/preview.js
```

## Recommended v2

Broader hair-only semantic interpretation with a preview of extracted instructions; asynchronous jobs if real Bluehost limits require them; privacy-preserving bot challenges if traffic warrants; real booking integration; optional hair masks to improve edit locality; tested HEIC conversion; and multilingual copy. Keep permanent galleries and accounts out of the core workflow.
