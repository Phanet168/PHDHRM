@once
    {{-- Critical styles and a dependency-free script keep the initial loader reliable. --}}
    <style>
        .page-loader { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 24px; background: #f7f9fc; color: #05305c; opacity: 1; transition: opacity 240ms ease-out; font-family: 'Noto Sans Khmer', sans-serif; }
        .page-loader[hidden] { display: none; }
        .page-loader.is-leaving { opacity: 0; pointer-events: none; }
        .page-loader-content { box-sizing: border-box; min-width: 0; width: 100%; max-width: 680px; max-height: calc(100vh - 48px); max-height: calc(100dvh - 48px); overflow-y: auto; padding: 12px; text-align: center; }
        .page-loader .site-logo { --site-logo-size: 104px; }
        .page-loader-name-km { margin: 24px 0 10px; font-size: clamp(1.25rem, 3vw, 1.875rem); font-weight: 700; line-height: 1.85; overflow-wrap: anywhere; }
        .page-loader-name-en { margin: 0; color: #526579; font-size: clamp(.9375rem, 2vw, 1.125rem); line-height: 1.65; overflow-wrap: anywhere; }
        .page-loader-spinner { width: 32px; height: 32px; margin: 26px auto 16px; border: 3px solid #dce9f4; border-top-color: #005cad; border-right-color: #0ea962; border-radius: 50%; animation: page-loader-spin .85s linear infinite; }
        .page-loader-text { margin: 0; color: #526579; font-size: 1rem; line-height: 1.9; overflow-wrap: anywhere; }
        @keyframes page-loader-spin { to { transform: rotate(360deg); } }
        @media (max-width: 480px) {
            .page-loader { box-sizing: border-box; padding: max(16px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(16px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left)); }
            .page-loader .site-logo { --site-logo-size: 80px; }
            .page-loader-content { padding: 8px 0; max-height: calc(100vh - 32px); max-height: calc(100dvh - 32px - env(safe-area-inset-top) - env(safe-area-inset-bottom)); }
            .page-loader-name-km { margin-top: 18px; font-size: 1.25rem; line-height: 1.85; }
            .page-loader-name-en { font-size: .9375rem; }
        }
        @media (max-height: 500px) and (max-width: 64rem) {
            .page-loader .site-logo { --site-logo-size: 56px; }
            .page-loader-name-km { margin: 12px 0 6px; font-size: 1.125rem; }
            .page-loader-name-en { font-size: .875rem; }
            .page-loader-spinner { width: 28px; height: 28px; margin: 14px auto 8px; }
        }
        @media (prefers-reduced-motion: reduce) { .page-loader { transition: none; } .page-loader-spinner { animation: none; } }
    </style>
    <div id="page-loader" class="page-loader" role="status" aria-live="polite" aria-label="កំពុងផ្ទុក" hidden>
        <div class="page-loader-content">
            <x-organization-logo />
            <p class="page-loader-name-km">{{ config('site.name_km') }}</p>
            @if (config('site.name_en'))
                <p class="page-loader-name-en" lang="en">{{ config('site.name_en') }}</p>
            @endif
            <div class="page-loader-spinner" aria-hidden="true"></div>
            @if (config('site.loading_text_km'))
                <p class="page-loader-text">{{ config('site.loading_text_km') }}</p>
            @endif
        </div>
    </div>
    <script>
        (() => {
            const loader = document.getElementById('page-loader');
            if (!loader) return;
            let navigationPending = false;
            const show = () => {
                loader.classList.remove('is-leaving');
                loader.setAttribute('aria-hidden', 'false');
                document.body.setAttribute('aria-busy', 'true');
                loader.hidden = false;
            };
            const dismiss = (immediate = false) => {
                navigationPending = false;
                document.body.removeAttribute('aria-busy');
                loader.setAttribute('aria-hidden', 'true');
                if (immediate || loader.hidden || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    loader.hidden = true;
                    loader.classList.remove('is-leaving');
                    return;
                }
                loader.classList.add('is-leaving');
            };
            // Keep the component mounted so it can cover the outgoing page while
            // Laravel is still preparing the next response (before its HTML arrives).
            loader.addEventListener('transitionend', (event) => {
                if (event.target === loader && event.propertyName === 'opacity'
                    && loader.classList.contains('is-leaving')) {
                    loader.hidden = true;
                }
            });

            const pageDestination = (href, target) => {
                if (!href || href.startsWith('#') || (target && target.toLowerCase() !== '_self')) return false;
                let url;
                try { url = new URL(href, document.baseURI); } catch { return false; }
                if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin) return false;
                if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return false;
                // Downloads and asset links leave the current document in place.
                return !/\/download(?:\/|$)|\.(?:pdf|docx?|xlsx?|csv|zip|rar|7z|png|jpe?g|webp|gif|svg|mp4|mp3)$/i.test(url.pathname);
            };
            const beginNavigation = () => {
                navigationPending = true;
                show();
            };

            window.addEventListener('click', (event) => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                const link = event.target.closest?.('a[href]');
                if (!link || link.hasAttribute('download') || link.closest('[data-no-page-loader]')) return;
                if (pageDestination(link.getAttribute('href'), link.getAttribute('target'))) beginNavigation();
            });
            window.addEventListener('submit', (event) => {
                if (event.defaultPrevented) return;
                const form = event.target;
                if (form.tagName !== 'FORM' || form.closest('[data-no-page-loader]')) return;
                const submitter = event.submitter;
                const method = submitter?.getAttribute('formmethod') ?? form.getAttribute('method') ?? 'get';
                if (!['get', 'post'].includes(method.toLowerCase())) return;
                const action = submitter?.getAttribute('formaction') ?? form.getAttribute('action') ?? window.location.href;
                const target = submitter?.getAttribute('formtarget') ?? form.getAttribute('target');
                if (pageDestination(action || window.location.href, target)) beginNavigation();
            });
            // Escape also lets a user recover if they cancel a pending browser request.
            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && navigationPending) dismiss(true);
            });
            window.addEventListener('load', () => {
                if (!navigationPending) dismiss();
            }, { once: true });
            window.addEventListener('pageshow', (event) => {
                if (event.persisted) dismiss(true);
            });
            // Native navigation starts immediately; there is no timer or minimum hold.
            if (document.readyState === 'complete') dismiss(true);
            else show();
        })();
    </script>
@endonce
