# ConsentFlow — GDPR Cookie Consent for PrestaShop

**ConsentFlow** is a modern, privacy-first cookie consent module for PrestaShop. It shows a fully customizable consent banner, blocks tracking scripts and third-party embeds until the visitor decides, and speaks **Google Consent Mode v2** natively — so your analytics and ads stay compliant with **GDPR / ePrivacy** without losing signal.

No external services, no monthly fees, no data leaving your shop. Everything runs on your own PrestaShop installation.

---

## Key Features

- **Consent banner** — bottom, top or center (modal) position, blocks interaction with the page until the visitor makes a choice
- **Preference center** — granular per-category toggles (Necessary, Analytics, Marketing, Functional) with an expandable cookie table
- **Google Consent Mode v2** — emits the `consent` `default` state server-side in `<head>` *before* GTM loads, then fires `update` on every choice
- **Script blocking** — tracking scripts stay inert (`type="text/plain"`) until their category is granted
- **Iframe blocking** — YouTube, Google Maps and social embeds are replaced by a placeholder until consent is given
- **Consent log** — every decision stored with timestamp, IP, policy version and A/B variant; one-click **CSV export** for audits
- **Full theming** — your colors, border radius and every text string, editable per language
- **Multi-language** — all banner, modal and table texts are translatable
- **A/B testing** — compare two banner positions to find the highest acceptance rate
- **Re-open button** — a floating button lets visitors change their choice at any time
- **Bot-friendly** — crawlers are not shown the banner, keeping your pages clean for indexing
- **Developer API** — `window.ConsentFlow` with events and callbacks for custom integrations

---

## Requirements

- PrestaShop **8.0.0 – 9.x**
- PHP **8.1+**

---

## Installation

1. Download or clone this repository.
2. Zip the `consentflow` folder (the zip must contain `consentflow.php` at its root).
3. In the PrestaShop Back Office go to **Modules → Module Manager → Upload a module** and upload the zip.
4. Click **Install**.
5. Open the module from the sidebar: **Improve → Secret Sauce → ConsentFlow**.

> The module registers its own admin tab and creates its database tables automatically on install.

---

## Quick Start — getting it running

Once installed, five steps take you from zero to compliant:

1. **Enable the module** — *Settings* tab → toggle **Enable module** on. This is the master switch; when off, no banner and no assets are loaded.
2. **Choose the banner position and look** — set position (bottom / top / center), colors, border radius, and pick the floating re-open button position. *(Appearance card)*
3. **Write your texts** — *Texts* tab → set the banner title, body, button labels and category descriptions for each language. A **Privacy policy page** can be linked from the *Settings* tab.
4. **List your cookies** — *Cookie Definitions* tab → add the cookies your shop sets (name, provider, duration, description, category). These populate the cookie table shown in the preference center.
5. **Turn on Google Consent Mode v2** — *Settings* tab → toggle it on if you use Google Tag Manager / gtag (see below).

That is enough for a compliant banner. To actually **gate your tracking**, tag your scripts and iframes as shown in the next sections.

---

## Consent Categories

| Category | Purpose | Google Consent Mode v2 signals |
|----------|---------|--------------------------------|
| **Necessary** | Required for the shop to work. Always on, cannot be disabled. | `security_storage`, `functionality_storage` (granted) |
| **Analytics** | Traffic and behavior measurement. | `analytics_storage` |
| **Marketing** | Advertising, remarketing, conversion tracking. | `ad_storage`, `ad_user_data`, `ad_personalization` |
| **Functional** | Preference storage (language, currency, etc.). Optional — can be bundled with Necessary. | `personalization_storage` |

---

## Google Consent Mode v2

Enable **Google Consent Mode v2** in the *Settings* tab. ConsentFlow then:

1. Emits the **default** consent state (everything except security/functionality denied) **server-side in `<head>`**, before Google Tag Manager or gtag.js runs — this is what keeps you compliant *and* preserves Google's modeling.
2. Fires a **`gtag('consent', 'update', …)`** call with the visitor's real choices the moment they accept, reject or save preferences.

You do not need to add any consent code to GTM yourself — just make sure your GTM/gtag container is present on the page. Tags configured to respect consent will fire only for granted categories.

---

## Blocking scripts until consent

Any `<script>` you want gated should be marked as `type="text/plain"` with a `data-consentflow` attribute naming its category. ConsentFlow activates it only once that category is granted:

```html
<!-- Runs only after the visitor accepts "analytics" -->
<script type="text/plain" data-consentflow="analytics">
  console.log('Analytics initialized');
</script>

<!-- External script, gated behind "marketing" -->
<script type="text/plain" data-consentflow="marketing"
        src="https://example.com/pixel.js"></script>
```

---

## Blocking iframes until consent

Enable **Block iframes until consent** in the *Settings* tab, then add `data-consentflow` to any embed. Until consent is given, the iframe is replaced by a friendly placeholder with an "Accept and show" button:

```html
<iframe data-consentflow="marketing"
        src="https://www.youtube.com/embed/VIDEO_ID"
        width="560" height="315"></iframe>
```

Provider icons for YouTube, Google Maps, Facebook, Instagram, X/Twitter and Vimeo are detected automatically.

---

## JavaScript API

ConsentFlow exposes a small API on `window.ConsentFlow` for custom integrations:

```js
// Has the visitor granted a category?
ConsentFlow.hasConsent('analytics');        // => true | false

// Get the full consent object
ConsentFlow.getConsent();
// => { necessary:true, analytics:false, marketing:false, functional:true, version:1, timestamp:… }

// Run a callback once a category is (or becomes) granted — fires once
ConsentFlow.onConsent('marketing', function () {
  loadFacebookPixel();
});

// Load an external script as soon as a category is granted
ConsentFlow.loadScript('https://example.com/tag.js', 'analytics', { async: true });

// Open the preference center programmatically
ConsentFlow.showPreferences();

// Revoke consent and show the banner again
ConsentFlow.revokeConsent();

// Has the visitor interacted with the banner at all?
ConsentFlow.hasInteracted();                // => true | false
```

You can also listen for consent changes via a DOM event:

```js
document.addEventListener('consentflow:update', function (e) {
  console.log('New consent state:', e.detail);
});
```

---

## Consent Logging & Compliance

When **Log consent to database** is enabled, every accept / reject / custom / revoke action is stored with:

- timestamp and customer/guest reference
- per-category decision (analytics, marketing, functional)
- policy version and A/B variant
- IP address

Browse and filter entries in the *Consent Log* tab, or **export to CSV** for audits and DSAR requests. A configurable retention period auto-purges old entries, and logs are deleted on GDPR customer-deletion requests.

> **Re-consent on policy change:** bump the **Policy version** number in *Settings* to invalidate all stored consents and prompt every visitor to choose again.

---

## Database Structure

| Table | Description |
|-------|-------------|
| `consentflow_log` | Consent decisions log |
| `consentflow_cookie_definition` | Cookie definitions (name, provider, duration, category) |
| `consentflow_cookie_definition_lang` | Multilingual cookie descriptions |

---

## Translations

ConsentFlow is fully translatable. English is the source language and Polish translations are bundled. Every storefront-facing string (banner, modal, cookie table headers, badges) is editable per language directly in the *Texts* tab — no file editing required.

---

## ❤️ Support the author

ConsentFlow is **free and open-source**. If it saves you time or money, please consider supporting its development:

- ☕ **[Buy me a coffee](https://buymeacoffee.com/marcingajewski)**
- ⭐ **[Star the project on GitHub](https://github.com/GajewskiMarcin/ConsentFlow)**
- 🐛 **[Report a bug or request a feature](https://github.com/GajewskiMarcin/ConsentFlow/issues)**

Every coffee and every star helps keep the module maintained. Thank you!

---

## Author

Created by **Marcin Gajewski** — [marcingajewski.pl](https://marcingajewski.pl)

---

## License

Released under the **Academic Free License 3.0 (AFL-3.0)**.
