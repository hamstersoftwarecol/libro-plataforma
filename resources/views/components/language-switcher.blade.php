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
        window.libroTranslation = (() => {
            const current = () => {
                const values = [...document.cookie.matchAll(/(?:^|;\s*)googtrans=\/[a-z-]+\/([a-z-]+)/gi)].map((match) => match[1]);
                // Google can leave the cookie on several domains; any non-Spanish value means "translated".
                return values.find((lang) => lang !== 'es') || 'es';
            };

            // libro.somosenlaces.org -> ['libro.somosenlaces.org', 'somosenlaces.org']
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
                if (lang === current()) return;

                if (lang === 'es') {
                    writeCookie('', true);
                    writeCookie('/es/es');
                    restoreOriginal();
                } else {
                    writeCookie(`/es/${lang}`);
                }

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

            return { current };
        })();

        function googleTranslateElementInit() {
            new google.translate.TranslateElement({ pageLanguage: 'es', includedLanguages: 'en,es', autoDisplay: false }, 'google_translate_element');
        }
    </script>
    <script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async></script>
@endonce
