# Public page loader

The public layout lives in `resources/views/layouts/app.blade.php` and includes `<x-page-loader />` once. Existing `<x-layout>` pages delegate to that layout. The component also supports pages using `@extends('layouts.app')` and a `content` section.

Branding uses the existing `Setting` model/cache and globally populated `site` config. `AppServiceProvider` reads one cached settings snapshot; neither the loader nor the shared logo component queries settings.

Settings are edited under the existing admin settings page:

| Existing setting key | Global config | Purpose |
| --- | --- | --- |
| `org_name_km` | `site.name_km` | Khmer organization name |
| `org_name_en` | `site.name_en` | English organization name |
| `logo` | `site.logo` / `site.logo_url` | Uploaded organization logo |
| `loading_text_km` | `site.loading_text_km` | Optional Khmer loading message |

Existing organization keys are retained instead of introducing duplicate `organization_name_*` settings. `Setting::set()` invalidates the existing settings cache; saved changes appear on the next request.

The loader and public header share `<x-organization-logo />`. Public-disk paths, `storage/` and `public/storage/` paths, public asset paths, and absolute HTTP(S) URLs are supported. Public-disk URLs use the configured disk URL, including the application's subdirectory. The normal public storage link must remain available.

If the settings logo is absent or its local file is missing, the configured `site.default_logo` (optional `SITE_DEFAULT_LOGO` environment value) is used. If no image is configured, the existing header healthcare cross is shown. Images remain invisible until successfully loaded. Remote failures try the configured default once, then retain the healthcare cross without a broken-image icon.

No loader or previous loading animation existed in this checkout. The component provides a small rotating indicator and a 240ms opacity fade beginning immediately on `window.load`, with no minimum display time or timer. It stays mounted but hidden after loading. Same-origin page links and native GET/POST form submissions immediately show it on the outgoing page, while the server prepares the next response. Browser navigation and form submission proceed normally; requests are not delayed or replaced with AJAX.

Hash links, downloads, external links, new tabs, modified clicks, prevented events, and elements marked `data-no-page-loader` do not start the overlay. Submitter action/method/target overrides are respected. Background visitor-statistics requests do not trigger it. Escape hides the overlay when a user cancels navigation, and back/forward-cache restoration resets it. Reduced motion, disabled JavaScript, and failed application scripts are supported. Critical styles and dismissal run independently of the application bundle.

On a first visit from outside the website, the browser must receive the first HTML response before any site loader can render. For navigation within the site, the already-loaded component covers that server-response wait.

The loader adapts its logo and typography for small screens and permits vertical scrolling on short viewports. Automated checks cover settings integration, cache invalidation, URL formats, missing/failed logos, duplicate inclusion, and dismissal behavior. Visual mobile/desktop QA still requires a connected browser.

```sh
php artisan test --filter=PageLoaderTest
node --test tests/Frontend/page-loader.test.mjs
```
