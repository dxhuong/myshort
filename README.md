# MyShort

> A lightweight, self-hosted URL shortener — minimal, fast, and distraction-free.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-003B57?style=flat-square&logo=sqlite&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)
![Files](https://img.shields.io/badge/files-3%20only-blue?style=flat-square)

---

## What is MyShort?

MyShort is a self-hosted URL shortener built with pure PHP and SQLite. No framework. No Composer. No database server. Just 3 files — drop them on any shared hosting and you're live.

Most URL shorteners require a full stack setup or come with a monthly fee. MyShort runs on any server with PHP 8.0+ and stays out of your way.

---

## Features

- 🔗 **Custom slug** — set your own vanity URLs (e.g. `yourdomain.com/sale2024`)
- 🔒 **Password protection** — lock any link behind a password
- 📊 **Click analytics** — track total clicks, unique visitors, referrers, and daily trends
- 🖼️ **OG thumbnail** — custom title, description, and image when sharing on Facebook or Zalo
- 📱 **QR code** — auto-generated QR for every link, with one-click download
- ⚡ **Admin dashboard** — manage, search, edit, and delete links in one place
- 🕐 **Expiring links** — set an expiry date on any link
- 🧱 **3 files only** — `index.php`, `r.php`, `.htaccess`

---

## Requirements

- PHP 8.0+
- SQLite3 extension enabled
- Apache with `mod_rewrite` enabled (or Nginx equivalent)

---

## Installation

**1. Clone the repo**
```bash
git clone https://github.com/backrun/myshort.git
cd myshort
```

**2. Configure**

Open `index.php` and update the config at the top:
```php
define('BASE_URL', 'https://yourdomain.com');
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'your_password');
```

**3. Upload to your server**

Copy all 3 files to your hosting root (or a subdomain folder):
```
index.php
r.php
.htaccess
```

**4. Done**

Visit `yourdomain.com` — the SQLite database is created automatically on first run.

> **Tip:** For the cleanest short links, point a subdomain like `s.yourdomain.com` or `link.yourdomain.com` to this folder.

---

## Usage

1. Log in to the admin panel at `yourdomain.com`
2. Paste a long URL and click **Shorten**
3. Optionally set a custom slug, password, OG metadata, or expiry date
4. Copy your short link or download the QR code

---

## Screenshots

![MyShort Screenshot 1](https://backrun.co/uploads/upload/69f173a80f330_myshort_1.png)
![MyShort Screenshot 2](https://backrun.co/uploads/upload/69f173a80fe16_myshort_2.png)
![MyShort Screenshot 3](https://backrun.co/uploads/upload/69f173a8105fc_myshort_3.png)

---

## License

MIT License — free to use, modify, and distribute.

---

## About Backrun

**MyShort** is built and open-sourced by [Backrun](https://backrun.co).

Backrun builds tiny digital products that automate repetitive work — fast, calm, and reliable. The mission is simple: build small, reliable products that remove repetitive work and just run.

Every Backrun product is designed to be minimal, controlled, and trustworthy — built to run, not to distract. Tiny tools. Extensions. Flows. Background automation that just runs.

→ [backrun.co](https://backrun.co)
