# Project Documentation: Dropshipzone to WooCommerce Inventory Sync

## 1. Executive Summary

**Goal:** Automate the synchronization of product stock levels from Dropshipzone (Supplier) to our WooCommerce store (Retailer) to prevent overselling and ensure data accuracy.

**Core Philosophy:** "Safety First."

The integration is architected as an external, file-based system running independently of the WordPress application layer. It prioritizes server stability, data integrity, and strict adherence to API rate limits.

**Architecture Type:** Unidirectional Sync (Supplier → Middleware File → WooCommerce)

---

## 2. System Architecture

The system is divided into two distinct phases to ensure modularity and safety.

### Phase 1: The Fetcher (Current Focus)

- **Responsibility:** Authenticate with Dropshipzone API, download the full product catalog, and save it to a local JSON file.
- **Mechanism:** Atomic file writes with streaming to minimize RAM usage.
- **Output:** `dropshipzone_inventory.json` (The "Golden Source" of truth).

### Phase 2: The Matcher (Future Implementation)

- **Responsibility:** Read the local JSON file, compare it against the WooCommerce database, and batch update stock levels.
- **Mechanism:** Memory-based hash map comparison with a "Circuit Breaker" safety stop.

---

## 3. Directory Structure & Security

To ensure maximum security, all integration scripts must reside **outside the public web directory** (`public_html`).

**Path:** `/home/username/dropship_sync/`

| File Name | Permission | Description |
|-----------|------------|-------------|
| `config.php` | 600 | Stores sensitive API credentials. |
| `auth_manager.php` | 600 | Handles JWT token retrieval, caching, and rotation. |
| `fetch_inventory.php` | 600 | The main script for Phase 1 (Downloads data). |
| `token_store.json` | 600 | Auto-generated cache for the active API token. |
| `sync.lock` | 600 | Auto-generated lock file to prevent overlapping cron jobs. |
| `dropshipzone_inventory.json` | 644 | The output file containing the raw product data. |

### Folder Permissions

The parent folder `/dropshipzone_sync` must be set to **700** (User Read/Write/Execute ONLY). No Group or World access is permitted.

---

## 4. Technical Implementation Details

### A. Authentication (`auth_manager.php`)

**Endpoint:** `POST https://api.dropshipzone.com.au/auth`

**Token Logic:**
- Tokens expire in 8 hours (per documentation).
- **Buffer:** The script forces a refresh at 7 hours to ensure long-running jobs do not fail mid-sync.
- **Caching:** Tokens are stored in `token_store.json` to avoid redundant login requests.

### B. The Fetcher (`fetch_inventory.php`)

This script is the backbone of the integration. It implements several "Enterprise-Grade" stability features:

#### Streaming Architecture
- Instead of loading 20,000 products into a PHP Array (RAM heavy), it opens a file stream and writes products line-by-line.
- **Impact:** Constant low memory usage (~10-20MB) regardless of catalog size.

#### Rate Limiting (Crucial)
- **API Limit:** 60 requests per minute.
- **Implementation:** `usleep(1000000)` (1.0 second) pause between every page fetch.
- **Result:** Max ~60 requests/minute. Safe from IP bans.

#### Atomic Writes
- Data is written to `temp_inventory.json` first.
- Only upon successful completion is it renamed to `dropshipzone_inventory.json`.
- **Benefit:** Prevents corrupt/half-written files if the server crashes mid-sync.

#### Network Stability
- Implements a **Retry Loop** (3 Attempts) with exponential backoff for every API call.
- Handles 502 Bad Gateway or temporary timeouts gracefully.

---

## 5. Configuration

### `config.php`

```php
<?php
return [
    'email'    => 'YOUR_DROPSHIPZONE_EMAIL',
    'password' => 'YOUR_DROPSHIPZONE_PASSWORD',
    'base_url' => 'https://api.dropshipzone.com.au', // V2 API Endpoint
];
?>
```

---

## 6. Testing Procedures

Since this is an external script, it can be tested via CLI (Command Line Interface) without affecting the live WordPress site.

### Step 1: Authentication Check

Run the auth manager directly to verify credentials.

```bash
php auth_manager.php
```

**Expected Output:**
```
[Auth] Success! New token acquired.
```

### Step 2: Full Fetch Test

Run the fetch script to download the catalog.

```bash
php fetch_inventory.php
```

**Expected Output:**
```
[Sync] Starting Streaming Download...
   > Page 1 streamed (200 items).
   > Page 2 streamed (200 items).
[Sync] Download complete. Validating...
[Success] Inventory saved to .../dropshipzone_inventory.json
```

### Step 3: Verify Data Integrity

Check the head of the generated file to ensure valid JSON and correct fields (sku, stock).

```bash
head -n 15 dropshipzone_inventory.json
```

---

## 7. Deployment Plan

1. **Upload:** Place all scripts in `/home/username/dropship_sync/`.
2. **Secure:** Apply `chmod 700` to folder and `chmod 600` to PHP/JSON files.
3. **Test:** Run manually via SSH to confirm stability.
4. **Automate:** Add a Cron Job in cPanel.

### Cron Job Schedule

- **Frequency:** Once Per Hour (`0 * * * *`)
- **Command:** `/usr/local/bin/php /home/username/dropship_sync/fetch_inventory.php`

---

## 8. Future Roadmap (Phase 2: Matcher)

Once Phase 1 (Fetch) is stable, we will implement `matcher.php` with the following logic:

1. Load `dropshipzone_inventory.json` into memory (Hash Map).
2. Fetch all WooCommerce Product IDs & SKUs (SQL Query).
3. Compare Stock levels.
4. **Circuit Breaker:** Abort if >30% of products are missing from the feed (prevents catalog wipes).
5. **Batch Update:** Update `_stock` and `_stock_status` in WooCommerce.

---

## Notes

- All scripts are designed to be modular and maintainable.
- The system prioritizes data integrity and server stability over speed.
- Error logging should be implemented for production monitoring.
- Consider adding email notifications for critical failures once deployed.