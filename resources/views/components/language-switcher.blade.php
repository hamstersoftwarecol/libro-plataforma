@props(['variant' => 'default'])

{{-- Translates the page with Google's website translator (HU: lectura en otros idiomas). --}}
<div {{ $attributes->class([
         'notranslate flex items-center rounded-full p-0.5 text-xs font-semibold ring-1',
         'bg-zinc-900/60 ring-zinc-700/70' => $variant === 'default',
         'bg-black/20 ring-paper/20' => $variant === 'cinematic',
     ]) }} translate="no" data-language-switcher>
    @foreach (['es' => 'ES', 'en' => 'EN'] as $code => $label)
        <button type="button" data-lang="{{ $code }}" title="{{ $code === 'es' ? 'Español (original)' : 'English (Google Translate)' }}"
                class="rounded-full px-2.5 py-1 text-zinc-400 transition hover:text-white data-[active=true]:bg-brand-600 data-[active=true]:text-white">{{ $label }}</button>
    @endforeach
</div>

@once
    <div id="google_translate_element" class="hidden" aria-hidden="true"></div>
    <script>
        // Silenciar telemetría de Google Translate para evitar net::ERR_BLOCKED_BY_CLIENT por bloqueadores de anuncios
        (() => {
            const isLogUrl = (url) => typeof url === 'string' && (url.includes('translate.googleapis.com/element/log') || url.includes('/element/log?'));

            if (window.fetch) {
                const origFetch = window.fetch;
                window.fetch = function(input, init) {
                    const url = typeof input === 'string' ? input : (input?.url || '');
                    if (isLogUrl(url)) {
                        return Promise.resolve(new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } }));
                    }
                    return origFetch.apply(this, arguments);
                };
            }

            if (window.XMLHttpRequest) {
                const origOpen = XMLHttpRequest.prototype.open;
                const origSend = XMLHttpRequest.prototype.send;
                XMLHttpRequest.prototype.open = function(method, url) {
                    this._isLogUrl = isLogUrl(url);
                    return origOpen.apply(this, arguments);
                };
                XMLHttpRequest.prototype.send = function() {
                    if (this._isLogUrl) {
                        try {
                            Object.defineProperty(this, 'status', { value: 200, writable: false });
                            Object.defineProperty(this, 'readyState', { value: 4, writable: false });
                            Object.defineProperty(this, 'responseText', { value: '{}', writable: false });
                            if (typeof this.onload === 'function') this.onload();
                        } catch (_) {}
                        return;
                    }
                    return origSend.apply(this, arguments);
                };
            }

            if (navigator.sendBeacon) {
                const origBeacon = navigator.sendBeacon.bind(navigator);
                navigator.sendBeacon = function(url, data) {
                    if (isLogUrl(url)) return true;
                    return origBeacon(url, data);
                };
            }
        })();

        window.libroTranslation = (() => {
            const current = () => {
                const local = localStorage.getItem('libro_lang');
                if (local && ['es', 'en'].includes(local)) return local;
                const values = [...document.cookie.matchAll(/(?:^|;\s*)googtrans=\/[a-z-]+\/([a-z-]+)/gi)].map((match) => match[1]);
                return values.find((lang) => lang !== 'es') || 'es';
            };

            const domains = () => {
                const parts = location.hostname.split('.');
                if (parts.length < 2 || /^\d+$/.test(parts.at(-1))) return [];
                return parts.slice(0, -1).map((_, index) => parts.slice(index).join('.')).filter((domain) => domain.includes('.'));
            };

            const writeCookie = (value, expired = false) => {
                const suffix = `; path=/${expired ? '; expires=Thu, 01 Jan 1970 00:00:00 GMT' : ''}`;
                document.cookie = `googtrans=${value}${suffix}`;
                domains().forEach((domain) => {
                    document.cookie = `googtrans=${value}${suffix}; domain=${domain}`;
                    document.cookie = `googtrans=${value}${suffix}; domain=.${domain}`;
                });
            };

            const restoreOriginal = () => {
                const combo = document.querySelector('.goog-te-combo');
                if (combo) {
                    combo.value = 'es';
                    combo.dispatchEvent(new Event('change'));
                }
            };

            const select = (lang) => {
                try { localStorage.setItem('libro_lang', lang); } catch (_) {}

                if (lang === 'es') {
                    writeCookie('', true);
                    writeCookie('/es/es');
                    restoreOriginal();
                } else {
                    writeCookie(`/es/${lang}`);
                }

                window.dispatchEvent(new CustomEvent('libro:language-change', { detail: { lang } }));
                location.reload();
            };

            const mark = () => document.querySelectorAll('[data-language-switcher] [data-lang]').forEach((button) => {
                button.dataset.active = String(button.dataset.lang === current());
            });

            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-language-switcher] [data-lang]');
                if (button) select(button.dataset.lang);
            });
            document.addEventListener('DOMContentLoaded', mark);
            mark();

            return { current, select };
        })();

        function googleTranslateElementInit() {
            try {
                new google.translate.TranslateElement({ pageLanguage: 'es', includedLanguages: 'en,es', autoDisplay: false }, 'google_translate_element');
            } catch (_) {}
        }
    </script>
    <script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async onerror="console.warn('Google Translate widget omitido por bloqueador de cliente.')"></script>
@endonce
