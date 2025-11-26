# Dropshipzone to WooCommerce Inventory Sync

> **Enterprise-grade inventory synchronization system for WooCommerce stores using Dropshipzone as a supplier.**

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Status](https://img.shields.io/badge/status-Phase%201-yellow)](https://github.com)

## 🎯 Overview

This project automates the synchronization of product stock levels from Dropshipzone (Supplier) to WooCommerce (Retailer), preventing overselling and ensuring data accuracy through a safe, file-based integration architecture.

### Core Philosophy: "Safety First"

The system runs **independently** of the WordPress application layer, prioritizing:
- ✅ Server stability
- ✅ Data integrity
- ✅ Strict API rate limit adherence
- ✅ Zero-downtime operations

### Architecture

**Unidirectional Sync:** Supplier → Middleware File → WooCommerce

```
┌─────────────────┐         ┌──────────────┐         ┌──────────────┐
│  Dropshipzone   │────────>│  JSON File   │────────>│ WooCommerce  │
│   (Supplier)    │  Fetch  │  (Middleware)│  Match  │   (Store)    │
└─────────────────┘         └──────────────┘         └──────────────┘
```

---

## 🚀 Features

### Phase 1: The Fetcher (Current)
- ✅ JWT token authentication with 7-hour auto-refresh
- ✅ Streaming architecture (constant ~10-20MB RAM usage)
- ✅ Rate limiting (1 req/sec, respects 60 req/min API limit)
- ✅ Atomic file writes (prevents corrupt data)
- ✅ Retry logic with exponential backoff
- ✅ Cron-job ready with lock file protection

### Phase 2: The Matcher (Planned)
- 🔄 Hash map-based product comparison
- 🔄 Circuit breaker (aborts if >30% products missing)
- 🔄 Batch WooCommerce stock updates
- 🔄 Detailed sync logs and notifications

---

## 📋 Requirements

- **PHP:** 7.4 or higher
- **Server:** SSH access (for deployment)
- **API:** Valid Dropshipzone API credentials
- **WooCommerce:** Version 5.0+ (for Phase 2)
- **Server Extensions:** `curl`, `json`

---

## 🛠️ Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/dropshipzone-woocommerce-sync.git
cd dropshipzone-woocommerce-sync
```

### 2. Create Directory Structure

**Important:** Place scripts **outside** `public_html` for security.

```bash
mkdir -p /home/username/dropship_sync
cd /home/username/dropship_sync
```

### 3. Install Dependencies

```bash
composer install
```

### 4. Configure API Credentials

Edit `config.php`:

```php
<?php
return [
    'email'    => 'your_dropshipzone_email@example.com',
    'password' => 'your_dropshipzone_password',
    'base_url' => 'https://api.dropshipzone.com.au',
    'logging'  => [
        'path'        => __DIR__ . '/logs/sync.log',
        'max_size'    => 5 * 1024 * 1024,
        'max_backups' => 5,
    ],
    // ... other settings
];
?>
```

---

## 🧪 Testing

### Run the Sync

```bash
php run_sync.php
```

**Expected Output:**
```
[2023-10-27 10:00:00] [INFO] Starting Streaming Download...
[2023-10-27 10:00:01] [INFO] Page 1 streamed (200 items).
...
[2023-10-27 10:00:05] [INFO] Success! Inventory saved to .../dropshipzone_inventory.json
```

### Verify JSON Output

```bash
head -n 15 dropshipzone_inventory.json
```

---

## ⚙️ Automation (Cron Job)

Add to cPanel or `/etc/crontab`:

```cron
# Run inventory sync every 6 hours
0 */6 * * * /usr/local/bin/php /home/username/dropship_sync/run_sync.php
```

---

## 📁 File Structure

```
/home/username/dropship_sync/
│
├── config.php                      # API credentials (600)
├── run_sync.php                    # Main entry point (600)
├── src/                            # Class files (InventoryFetcher, AuthManager, Logger)
├── vendor/                         # Autoloader
├── token_store.json                # Auto-generated token cache (600)
├── sync.lock                       # Auto-generated lock file (600)
└── dropshipzone_inventory.json     # Product catalog output (644)
```

---

## 🔐 Security Best Practices

| Component | Permission | Why |
|-----------|------------|-----|
| Directory | `700` | User-only access |
| PHP Scripts | `600` | Read/write by owner only |
| JSON Output | `644` | Readable by web server for Phase 2 |
| Config File | `600` | Contains sensitive credentials |

**Never** place this directory inside `public_html` or any web-accessible location.

---

## 📊 API Reference

This project integrates with the Dropshipzone V2 API:

### Key Endpoints Used

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/auth` | POST | Obtain JWT token |
| `/v2/products` | GET | Fetch product catalog |

### Rate Limits

- **60 requests per minute**
- **600 requests per hour**

Our implementation enforces 1-second delays between requests (max 60/min).

**Full API Documentation:** See `dropshipzone-api-docs.md`

---

## 🗺️ Roadmap

### ✅ Phase 1: The Fetcher (Complete)
- [x] API authentication with token caching
- [x] Streaming product downloads
- [x] Rate limiting compliance
- [x] Atomic file writes
- [x] CLI testing suite

### 🔄 Phase 2: The Matcher (In Progress)
- [ ] Load JSON into memory hash map
- [ ] Query WooCommerce product SKUs
- [ ] Compare and identify stock changes
- [ ] Implement circuit breaker logic
- [ ] Batch update WooCommerce products

### 🔮 Phase 3: Monitoring (Planned)
- [ ] Error logging to file
- [ ] Email notifications on failures
- [ ] Sync success/failure metrics
- [ ] Admin dashboard widget

---

## 🤝 Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🐛 Troubleshooting

### Token Expires Mid-Sync
**Solution:** Token cache refreshes at 7 hours automatically. If issues persist, delete `token_store.json` and re-authenticate.

### Rate Limit Errors (429)
**Solution:** Ensure only one instance runs at a time. Check for stuck processes: `ps aux | grep fetch_inventory`

### Incomplete JSON File
**Solution:** The atomic write system creates `temp_inventory.json` first. If the main file is corrupt, check temp file and server logs.

### Memory Errors
**Solution:** Increase PHP memory limit in `php.ini` or script: `ini_set('memory_limit', '256M');`

---

## 📞 Support

- **Documentation:** Full API docs included in `docs/`
- **Issues:** [GitHub Issues](https://github.com/yourusername/dropshipzone-woocommerce-sync/issues)
- **Dropshipzone API:** [Official Documentation](https://resources.dropshipzone.com.au/apidoc/)

---

## 🙏 Acknowledgments

- Dropshipzone for providing comprehensive API documentation
- WooCommerce community for database optimization insights
- Everyone who contributed to making this integration safer and more efficient

---

## 🤖 AI Transparency & Disclaimer

### 🧠 The Stack
This project was architected and developed with the assistance of next-generation AI coding tools, including **Google Antigravity**, **Gemini 3 Pro**, and **Jules**.

### 🎯 Intent & Methodology
I have utilized this project as a hands-on mechanism to master enterprise solution architecture. While I do not have extensive manual coding experience, I leveraged these advanced AI tools to design a solution that strictly adheres to industry best practices for **security**, **stability**, and **scalability**.

This repository serves as a proof-of-concept for AI-assisted engineering. The code has been reviewed and tested for logic and flow, but it is ultimately an automated generation.

### ⚠️ Important Usage Warning
**Please Review Before Production:**
While the architecture prioritizes "Safety First" (using atomic writes and strict rate limiting), this software is provided **"as is"**, without warranty of any kind.
* **Users are responsible** for auditing the code before deploying it to a live server.
* **Always backup** your WooCommerce database before running any inventory sync tools.
* **No sensitive data** (API keys or passwords) was shared with the AI models during the generation of this codebase.

*Use this tool at your own risk. The author is not liable for any data loss or inventory discrepancies.*

---

**Built with ❤️ for WooCommerce store owners who value data integrity.**
