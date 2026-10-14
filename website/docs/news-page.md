# Public news page

The `/news` page uses the existing site's navy, blue, green, and Noto Sans Khmer design tokens. The shared header/footer and homepage news cards are unchanged.

- Five published articles appear in the slider, prioritizing `is_featured`, then newest publication date. If fewer articles exist, only those available appear; a single article has no slider controls. Drafts, future publications, and deleted articles are excluded.
- The listing shows six articles per page, with category filtering preserved in pagination. Categories and published counts come from `news_categories` and `news`. `NewsCategorySeeder` safely adds the seven default categories without overwriting existing names or order.
- Desktop uses approximately 70/30 columns. Below 1024px the sidebar follows the listing; below 768px article cards use one column. The sidebar is sticky only when its full height fits the viewport.
- Swiper advances every 5.5 seconds, supports touch swipes, navigation and pagination buttons, and pauses for hover, keyboard focus, hidden tabs, or the pause control. Reduced-motion preferences disable automatic playback by default.

## Visitor statistics

Counting begins when this feature is installed; historical traffic is not fabricated. Visitors mean anonymous browsers, not identified people. Clearing cookies, expiry, or using another browser creates another visitor. Blocking cookies can increase counts.

An encrypted, HttpOnly, SameSite=Lax first-party cookie contains a random 256-bit token and expires after one year (Secure on HTTPS). Only an HMAC of that random token, first/last visit times, and a page-view counter are stored in `anonymous_visitors`. The feature never collects IP addresses, user-agent strings, referrers, URLs, account identifiers, or fingerprint attributes. This describes the new analytics tables; web-server logs and the application's existing session configuration are separate.

Successful public GET HTML responses are counted; authenticated staff, admin routes, recognized bots, prefetches, AJAX, downloads, JSON endpoints, HEAD requests, errors, and redirects are excluded. Bot filtering is best effort. Each counted page load adds a page view; repeat visits from the same cookie do not add another total visitor. Daily unique records use Cambodia's timezone (`Asia/Phnom_Penh`), with monthly counts deduplicated across the current month.

“Online” means a public page was loaded in the last five minutes. The widget refreshes aggregate counts every minute while visible. Refreshing statistics itself does not count as a visit or keep a browser online. Server-rendered values reflect counts immediately before the current response is recorded; the initial JSON refresh includes it. The public JSON endpoint returns only six aggregate counts and is rate limited.

Daily visitor identifiers are pruned after 35 days by the Laravel scheduler. Anonymous lifetime hashes and page counters remain for total deduplication. Enable the standard `php artisan schedule:run` once per minute in the deployment scheduler so retention runs.

## Setup and verification

```sh
php artisan migrate --force
php artisan db:seed --class=NewsCategorySeeder --force
npm run build
php artisan test --filter="NewsPageTest|VisitorStatisticsTest"
```

Tests cover publication visibility, featured priority, dynamic categories, pagination, empty/single-article states, idempotent seeding, cookie deduplication, schema privacy, page-view counting, Cambodia midnight/month boundaries, online expiry, excluded requests, and aggregate-only responses.
