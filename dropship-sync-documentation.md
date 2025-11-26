# Developer Architecture & Implementation Guide

> **Target Audience:** Developers, System Architects, and Maintainers.
> **Purpose:** To explain the internal logic, design decisions, and future implementation specifications of the Dropshipzone Sync project.

---

## 1. Design Philosophy & Architectural Decisions

This project was built to solve a specific set of challenges inherent in syncing large datasets (20,000+ products) on shared hosting environments (cPanel/PHP).

### Challenge 1: Memory Exhaustion
**The Problem:** Loading a 20MB+ JSON file or an array of 20,000 objects into PHP memory often triggers `Fatal Error: Allowed memory size exhausted`.
**The Solution:** **Streaming Architecture.**
We use PHP's `php://temp` streams and process data line-by-line (or chunk-by-chunk). We never hold the entire catalog in memory.
*   **Input:** Streamed from API via cURL.
*   **Output:** Streamed to disk via `fwrite`.

### Challenge 2: API Rate Limits
**The Problem:** Dropshipzone enforces a strict limit of **60 requests per minute**. Exceeding this risks an IP ban.
**The Solution:** **Client-Side Throttling.**
We do not rely on the server to tell us to stop. We enforce a hard sleep of **1.0 seconds** (`usleep(1000000)`) after *every* API call. This guarantees a maximum theoretical throughput of 60 req/min, keeping us safely in the "Green Zone".

### Challenge 3: Data Integrity (The "Half-Sync" Risk)
**The Problem:** If the script crashes mid-sync (timeout, power loss), a partially written JSON file could corrupt the store.
**The Solution:** **Atomic Writes.**
1.  Write to `temp_inventory.json`.
2.  Only when the download is 100% complete and valid, `rename()` it to `dropshipzone_inventory.json`.
3.  This operation is atomic on Linux filesystems, ensuring the "Golden Source" file is always valid.

---

## 2. Core Component Deep Dive

### A. `src/AuthManager.php` (The Gatekeeper)
Handles the JWT (JSON Web Token) lifecycle.

*   **Token Caching:** Stores the token in `token_store.json` to persist across script runs.
*   **The "7-Hour Rule":** Dropshipzone tokens last 8 hours. We force a refresh if the token is >7 hours old.
    *   *Why?* If a sync job takes 2 hours and starts with a 7.5-hour-old token, it will fail mid-sync. Refreshing early prevents this.

### B. `src/InventoryFetcher.php` (The Engine)
Manages the download loop.

*   **Pagination:** Automatically handles the `next_page_url` provided by the API.
*   **Retry Logic:** Implements an exponential backoff strategy for network failures (502/504 errors).
    *   Attempt 1: Wait 1s
    *   Attempt 2: Wait 2s
    *   Attempt 3: Wait 4s -> Fail.
*   **Locking:** Uses `flock()` on `sync.lock` to ensure only one instance runs at a time.

### C. `src/Logger.php` (The Black Box)
Provides visibility into the headless process.

*   **Rotation:** Keeps the last 5 logs (max 5MB each) to prevent disk overflow.
*   **Context:** Logs timestamps, log levels (INFO/ERROR), and memory usage for debugging.

---

## 3. Phase 2: The Matcher (Implementation Specification)

> **Status:** Planned / In Development
> **Goal:** Sync the local JSON file to the WooCommerce Database safely.

### The "Store-First" Strategy (Critical)
To support stores with mixed inventory (Dropshipzone + Other Suppliers), we **cannot** simply loop through the JSON file and update everything. We must iterate through the **Store's Products** first.

#### Step 1: Identification (The Filter)
We must identify which WooCommerce products belong to Dropshipzone.
*   **Developer Note:** This must be configurable via a Strategy Pattern or Interface.
*   **Strategies:**
    1.  `SkuPrefixStrategy`: Checks if SKU starts with `DSZ-`.
    2.  `TagStrategy`: Checks for `Supplier: Dropshipzone` tag.
    3.  `MetaFieldStrategy`: Checks `_supplier` meta key.

#### Step 2: The Loop
```php
// Pseudo-code for Matcher Logic
$jsonIndex = loadJsonToHashMap('dropshipzone_inventory.json'); // SKU -> Data
$storeProducts = wc_get_products(['limit' => -1]);

foreach ($storeProducts as $product) {
    // 1. Identification Check
    if (!isDropshipzoneProduct($product)) {
        continue; // SAFETY: Skip non-DSZ products
    }

    $sku = $product->get_sku();

    // 2. Lookup
    if (isset($jsonIndex[$sku])) {
        // MATCH FOUND
        $newData = $jsonIndex[$sku];
        updateProductStock($product, $newData['qty']);
        updateProductPrice($product, $newData['price']);
    } else {
        // MATCH NOT FOUND (Discontinued?)
        // 3. Safety Check: Is the file empty?
        if (circuitBreakerTripped()) {
            abortSync();
        }
        setProductStock($product, 0); // Mark out of stock
    }
}
```

#### Step 3: The Circuit Breaker
**Risk:** If `dropshipzone_inventory.json` is empty (0 items) due to a bug, the loop above would mark **100%** of Dropshipzone items as "Out of Stock".
**Logic:**
1.  Count total identified DSZ products (e.g., 300).
2.  Count "Missing" lookups (e.g., 290).
3.  Calculate Failure Rate: `290 / 300 = 96%`.
4.  **Threshold:** If Failure Rate > 20%, **ABORT**. Do not save changes. Send Alert.

---

## 4. Extending the Project

### Adding New API Endpoints
If Dropshipzone adds a new endpoint (e.g., Orders), create a new class in `src/` (e.g., `OrderManager.php`) extending a base `ApiClient` class to reuse the `AuthManager`.

### Customizing Logging
The `Logger` class is PSR-3 inspired but simplified. To send logs to Slack or Email, modify the `log()` method in `src/Logger.php` to dispatch events or call external webhooks.

---

## 5. Security Checklist for Developers

Before deploying any changes:
1.  **Check `.gitignore`:** Ensure `config.php` and `logs/` are never committed.
2.  **Permissions:** Scripts should run as the user, not root. Files `600`, Dirs `700`.
3.  **Input Validation:** Never trust data from the JSON file blindly. Sanitize before passing to `wc_update_product`.