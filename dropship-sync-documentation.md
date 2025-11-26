
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
[INFO] Success! Inventory
```
1. **Load Feed:** Load `dropshipzone_inventory.json` into memory (Hash Map).
2. **Fetch & Filter:** Fetch all WooCommerce products but **FILTER** them using a **User-Configurable Strategy**:
    *   *Strategy 1: Tag Match* (e.g., Product has tag "Dropshipzone")
    *   *Strategy 2: Brand/Taxonomy* (e.g., Brand is "Dropshipzone")
    *   *Strategy 3: SKU Pattern* (e.g., SKU starts with "DSZ-")
    *   *Strategy 4: Meta Field* (e.g., `_supplier_id` = `123`)
3. **Match & Compare:** Loop through the **filtered subset** and look them up in the Hash Map.
    *   *Found?* Update stock/price.
    *   *Not Found?* Mark as out of stock.
    *   *Non-Matching Item?* **SKIP entirely.**
4. **Circuit Breaker:** Abort if a significant percentage (e.g., >20%) of the **filtered** products are missing.
5. **Batch Update:** Update `_stock` and `_stock_status` in WooCommerce.

### Step 2: Verify Data Integrity

Check the head of the generated file to ensure valid JSON and correct fields (sku, stock).

```bash
head -n 15 dropshipzone_inventory.json
```

---
- All scripts are designed to be modular and maintainable.
- The system prioritizes data integrity and server stability over speed.
- Error logging should be implemented for production monitoring.
- Consider adding email notifications for critical failures once deployed.