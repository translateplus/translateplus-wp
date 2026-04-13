# TranslatePlus – AI Translation for WordPress

Translate your WordPress site instantly with **TranslatePlus**, a fast, modern, and cost-efficient AI-powered translation plugin.

> Built for developers, businesses, and creators who need scalable multilingual support.

---

## Features

### Multilingual Translation

* Translate posts, pages, and custom post types
* Supports multiple languages
* One-click translation

---

### Automatic & Manual Translation

* Auto-translate on publish
* Manual translation per page
* Regenerate translations anytime

---

### SEO-Friendly URLs

Each language has its own URL:

```
/en/post-name  
/es/post-name  
/fr/post-name  
```

* Clean structure
* Search engine friendly
* Supports hreflang

---

### Cost-Efficient API

TranslatePlus uses a **request-based pricing model**:

* Lower cost vs traditional APIs
* Transparent usage tracking
* Built-in cost estimation

---

### Language Switcher

* Premium dropdown switcher
* Menu integration
* Browser language auto-detection

---

### Fast & Lightweight

* Minimal overhead
* Optimized queries
* Built for performance

---

## Compatibility

Works seamlessly with:

* WordPress posts, pages, and custom post types
* SEO plugins (Yoast SEO, Rank Math)
* Modern themes

---

## Getting Started

### 1. Install Plugin

```bash
git clone https://github.com/translateplus/translateplus-wp.git
```

Or upload to:

```
/wp-content/plugins/translateplus-wp
```

---

### 2. Activate Plugin

Go to:

```
WordPress Admin → Plugins → Activate TranslatePlus
```

---

### 3. Connect API

* Navigate to **Settings → TranslatePlus**
* Enter your API key
* Verify connection

---

### 4. Start Translating

* Open any post or page
* Add a new language
* Translate instantly

---

## 🔌 API Usage

TranslatePlus is powered by a simple API:

```http
POST /translate
```

```json
{
  "text": "Hello world",
  "target_lang": "es"
}
```

---

## 🎁 Free Credits

Get started with **free credits** to test translations instantly.

👉 https://translateplus.io

---

## How It Works

1. Create or edit content
2. Add translation (ES, FR, etc.)
3. Plugin creates linked posts
4. URLs are automatically generated
5. Language switcher connects everything

---

## Architecture

* Each language = separate post
* Linked via translation group
* Language stored in metadata
* Clean WordPress-native approach

---

## Development

### Requirements

* PHP 7.4+
* WordPress 5.8+

---

### Local Setup

```bash
git clone https://github.com/your-username/translateplus-wp.git
```

Activate plugin in your local WordPress environment.

---

## Contributing

Contributions are welcome!

1. Fork the repository
2. Create a feature branch
3. Submit a pull request

---

## Issues

Found a bug or have a feature request?

👉 Open an issue on GitHub

---

## License

GPLv2 or later
https://www.gnu.org/licenses/gpl-2.0.html

---

## About TranslatePlus

TranslatePlus is more than a plugin—it’s a translation infrastructure for modern applications.

* WordPress integration
* API-first design
* Built for scale

👉 https://translateplus.io
