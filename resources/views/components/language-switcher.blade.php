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
            const current = () => (document.cookie.match(/(?:^|;\s*)googtrans=\/[a-z-]+\/([a-z-]+)/i) || [])[1] || 'es';
            const setCookie = (value) => {
                const expires = value ? '' : '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
                const cookie = `googtrans=${value}${expires}; path=/`;
                document.cookie = cookie;
                document.cookie = `${cookie}; domain=${location.hostname}`;
                if (location.hostname.split('.').length > 1) document.cookie = `${cookie}; domain=.${location.hostname}`;
            };
            const select = (lang) => {
                if (lang === current()) return;
                setCookie(lang === 'es' ? '' : `/es/${lang}`);
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
