# 🌱 GREENER-GO  
*A privacy-first, low-energy Google search launcher*

**GREENER-GO** is a tiny self-hostable search launcher that sends queries to Google’s **Web Results** view (`udm=14`) — skipping AI overviews, disabling search‑history personalization, and avoiding cookies or trackers.  
It includes an optional minimal PHP/MariaDB script that estimates energy and water saved by avoiding AI compute.

👉 Live demo: https://greener-go.sustainablehosting.com (hosted by [Sustainable Hosting](https://sustainablehosting.com))

---

## ✨ Features

- ✅ **No cookies / trackers / third-party scripts**  
- ✅ **Dark mode by default**, with `?theme=light` override  
- ✅ **Bypasses Google AI Overviews** via `udm=14`  
- ✅ **Disables search-history personalization** (`pws=0`)  
- ✅ **Optional “eco counter”** with one tiny PHP file + one MariaDB table  
- ✅ Works even if the backend is offline — fails gracefully

---

## 🚀 Quick Start

### Requirements

- A PHP host (PHP ≥ 7.0)  
- MariaDB/MySQL (for the optional eco-counter)  
- APCu recommended, but not required

### 1️⃣ Clone or download this repo

```bash
git clone https://github.com/SustainableHosting/greener-go.git
cd greener-go
```

Or download the ZIP from GitHub and extract it into your site’s web root.

---

### 2️⃣ Database setup (eco-counter)

Create the database, user, and table. Example:

```sql
CREATE DATABASE eco CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE USER 'eco_app'@'127.0.0.1' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE ON eco.* TO 'eco_app'@'127.0.0.1';
FLUSH PRIVILEGES;

USE eco;

CREATE TABLE eco_counter (
  id TINYINT PRIMARY KEY CHECK (id=1),
  total_searches BIGINT UNSIGNED NOT NULL DEFAULT 0,
  energy_saved_wh DOUBLE NOT NULL DEFAULT 0,
  water_saved_ml DOUBLE NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO eco_counter (id) VALUES (1);
```

> 📝 **Important:** Edit `eco.php` to set your database password and, if needed, host/user details.

---

### 3️⃣ Create the salt file

Generate a random salt **outside the web root**, e.g.:

```bash
mkdir ../private
openssl rand -hex 32 > ../private/eco_salt
chown webXXX:clientX ../private/eco_salt
chmod 0640 ../private/eco_salt
```

If your PHP-FPM pool enforces `open_basedir`, include the `../private` path for the site.

---

### 4️⃣ Test

Visit:

```
https://yourdomain/eco.php?mode=read
```

You should see JSON with `"ok": true`.

---

### 5️⃣ Optional icons

This repository doesn’t include favicons, web app icons, or manifests.  
If you want this to behave like a “homepage” or default search engine, provide your own icons and link them in the `<head>` of `index.html`.

---

## 📚 Why

Modern search interfaces are increasingly AI‑augmented, which:

- Consumes extra energy and water per query  
- Encourages deeper personalization and profiling  
- Ships more client‑side JavaScript bloat

**GREENER-GO** demonstrates how minimal HTML and a few smart URL parameters can offer a cleaner, privacy‑respecting, lower‑energy search experience.

---

## 🧠 FAQ

**Q: What happens if the database goes down?**  
A: The page still works — searches keep working. The eco‑counter simply shows “unavailable” until the backend is back.

**Q: Can I use this as my default browser search engine?**  
A: Not yet — but support via an OpenSearch XML descriptor is planned. Contributions welcome.

**Q: Can I customize the look?**  
A: Absolutely — just edit `index.html`. It’s a single file with inline CSS and minimal JS.

---

## 📝 SQL Schema (for convenience)

```sql
CREATE DATABASE eco CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE USER 'eco_app'@'127.0.0.1' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE ON eco.* TO 'eco_app'@'127.0.0.1';
FLUSH PRIVILEGES;

USE eco;

CREATE TABLE eco_counter (
  id TINYINT PRIMARY KEY CHECK (id=1),
  total_searches BIGINT UNSIGNED NOT NULL DEFAULT 0,
  energy_saved_wh DOUBLE NOT NULL DEFAULT 0,
  water_saved_ml DOUBLE NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO eco_counter (id) VALUES (1);
```

---

## 📄 `.gitignore` (optional)

```gitignore
# OS / Editor junk
.DS_Store
Thumbs.db
*.swp
*.swo

# Environment files / secrets
.env
eco_salt
/private/

# Logs
*.log

# Node / vendor (if someone extends this project)
node_modules/
vendor/
```

---

## 📄 License (MIT)

```text
MIT License

Copyright (c) 2025 Sustainable Hosting

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 💬 Discussion

HN thread (once posted): *link will go here*

---

## 🌍 Acknowledgements

Built by [Sustainable Hosting](https://sustainablehosting.com), the world’s oldest carbon‑neutral hosting company.  
Inspired by the idea that “ten blue links” should be fast, private, and sustainable.
