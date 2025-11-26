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
| `run_sync.php` | 600 | Main entry point script. |
| `src/` | 700 | Directory containing class files. |
| `vendor/` | 700 | Directory containing autoloader. |
| `token_store.json` | 600 | Auto-generated cache for the active API token. |
| `sync.lock` | 600 | Auto-generated lock file to prevent overlapping cron jobs. |
| `dropshipzone_inventory.json` | 644 | The output file containing the raw product data. |

### Folder Permissions

The parent folder `/dropshipzone_sync` must be set to **700** (User Read/Write/Execute ONLY). No Group or World access is permitted.

---

## 4. Technical Implementation Details

### A. Authentication (`src/AuthManager.php`)

**Endpoint:** `POST https://api.dropshipzone.com.au/auth`

**Token Logic:**
- Tokens expire in 8 hours (per documentation).
- **Buffer:** The script forces a refresh at 7 hours to ensure long-running jobs do not fail mid-sync.
- **Caching:** Tokens are stored in `token_store.json` to avoid redundant login requests.

### B. The Fetcher (`src/InventoryFetcher.php`)

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
    'base_url' => 'https://api.dropshipzone.com.au',
    'logging'  => [
        'path'        => __DIR__ . '/logs/sync.log',
        'max_size'    => 5 * 1024 * 1024,
        'max_backups' => 5,
    ],
    'sync' => [
        'batch_limit' => 200,
        'timeout'     => 60,
        'retries'     => 5,
        'rate_limit_sleep' => 6500000,
    ],
];
?>
```

---

## 6. Testing Procedures

Since this is an external script, it can be tested via CLI (Command Line Interface) without affecting the live WordPress site.

### Step 1: Run the Sync

```bash
php run_sync.php
```

**Expected Output:**
```
[INFO] Starting Streaming Download...
[INFO] Page 1 streamed (200 items).
[INFO] Page 2 streamed (200 items).
[INFO] Download complete. Validating...
[INFO] Success! Inventory saved to .../dropshipzone_inventory.json
```

### Step 2: Verify Data Integrity

Check the head of the generated file to ensure valid JSON and correct fields (sku, stock).

```bash
head -n 15 dropshipzone_inventory.json
```

---

## 7. Deployment Plan

1. **Upload:** Place all scripts in `/home/username/dropship_sync/`.
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