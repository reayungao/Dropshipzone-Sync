# Dropshipzone APIs Documentation

Welcome to Dropshipzone APIs Doc. Developers have extensive access to Dropshipzone APIs in order to build new services and features for merchants. This section will help you understand which parts of Drop Ship Zone you can access and how to work with them.

## Throttle Limits

**Please note:**
- Maximum number of requests in one minute: **60**
- Maximum number of requests in one hour: **600**

Dropshipzone API Gateway will fail limit-exceeding requests and return error responses to the client.

---

## Help

### User Guide

**How can I get information of all products, e.g. stock quantity?**

1. Call API method "Get Products" without passing keywords parameter
   - Example: `https://api.dropshipzone.com.au/products?page_no=1&limit=160`
2. Response contains 160 products information, like stock_qty, etc.
3. Developer can continue to pass page_no incrementally until all products information is retrieved.

---

## Auth

### Create Access Token

Create access token based on user access information. Token will expire in 8 hours. After expiration, a new token will be required.

**Endpoint:** `POST https://api.dropshipzone.com.au/auth`

#### Headers

| Field | Type | Description |
|-------|------|-------------|
| Content-Type | String | application/json |

**Example:**
```json
{
  "Content-Type": "application/json"
}
```

#### Request Body

| Field | Type | Description |
|-------|------|-------------|
| email | String | API user email |
| password | String | API user password |

**Example:**
```json
{
  "email": "apiuseremail@apiuseremail.com",
  "password": "123456"
}
```

#### Success Response

```http
HTTP/1.1 200
{
  "iat": 1569986936,
  "exp": 1570206536,
  "token": "xxxxxxxxxxxxx"
}
```

#### Error Response

```http
HTTP/1.1 500
{
  "code": "InternalServer",
  "message": "boom!"
}
```

---

## Category

### V2 Get All Categories

Get a list of categories and category information.

**Endpoint:** `GET https://api.dropshipzone.com.au/v2/categories`

#### Headers

| Field | Type | Description |
|-------|------|-------------|
| Authorization | String | jwt xxxxxxxxxxxxx (token created in auth) |
| Content-Type | String | application/json |

**Example:**
```json
{
  "Authorization": "jwt xxxxxxxxxxxxx",
  "Content-Type": "application/json"
}
```

#### Success Response

```http
HTTP/1.1 200
[
  {
    "category_id": 1,
    "title": "Appliances",
    "parent_id": 0,
    "path": "1/2/3",
    "is_anchor": 1,
    "is_active": 1,
    "include_in_menu": 1
  },
  {
    "category_id": 3,
    "title": "Tools & Auto",
    "parent_id": 2,
    "path": "1/2/3",
    "is_anchor": 1,
    "is_active": 1,
    "include_in_menu": 1
  }
]
```

#### Error Response

```http
HTTP/1.1 400
{
  "code": "400",
  "message": "Bad Request"
}
```

---

## Order

### Get Orders

Retrieve order information.

**Endpoint:** `GET https://api.dropshipzone.com.au/orders`

#### Headers

| Field | Type | Description |
|-------|------|-------------|
| Authorization | String | User authorization token |
| Content-Type | String | application/json |

**Example:**
```json
{
  "Authorization": "jwt xxxxxxx",
  "Content-Type": "application/json"
}
```

#### Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| order_ids | String | Optional | Order number |
| start_date | String | Optional | Y-m-d H:m:s or YYYY-MM-DDTHH:mm:ss.sssZ, or any other valid date time format string |
| end_date | String | Optional | Y-m-d H:m:s or YYYY-MM-DDTHH:mm:ss.sssZ, or any other valid date time format string |
| status | String | Optional | processing, complete, cancelled |
| page_no | Number | Optional | Page number with default 1 |
| limit | Number | Optional | Limit of the result with default 40, minimum 40 and maximum 160 |

#### Request Example

```
https://api.dropshipzone.com.au/orders?order_ids=102010799&start_date=2022-01-01&end_date=2022-12-31
```

#### Success Response

```http
HTTP/1.1 200
{
  "status": 1,
  "code": 1,
  "data": [
    {
      "increment_id": "100000001",
      "created_at": "2022-01-01 04:57:37",
      "customer_email": "dsz@dropshipzone.com.au",
      "dispatch_time": "2022-01-04 16:23:04",
      "grand_total": "120.0000",
      "items": [
        {
          "sku": "OCHAIR-G-2004-BK",
          "title": "Artiss Office Chair Gaming Chair Computer Mesh Chairs Executive Mid",
          "price": "40.0000",
          "qty_ordered": "3.0000"
        }
      ],
      "payment_way": "PayPal",
      "remark": null,
      "serial_number": "DS1231221",
      "shipment": [
        {
          "track_number": "1232132121",
          "title": "Aus Post",
          "created_at": "2022-01-04 16:23:04"
        }
      ],
      "shipping_address": {
        "first_name": "DSZ",
        "last_name": "ViVi",
        "address1": "104-106 RD",
        "region": "Victoria",
        "city": "Melbourne",
        "country_id": "AU",
        "postcode": "3031"
      },
      "status": "complete",
      "txn_id": "1231232112T23",
      "updated_at": "2022-01-04 16:23:04",
      "ordered_timestamp": 1641013057,
      "shipping_fee": "0.0000"
    }
  ],
  "total": 1,
  "total_pages": 1,
  "current_page": 1,
  "page_size": 40
}
```

#### Error Response 1

```http
HTTP/1.1 404
{
  "status": -1,
  "errmsg": "The time range must be less than or equal to 14 days apart."
}
```

#### Error Response 2

```http
HTTP/1.1 404
{
  "code": "InvalidArgument",
  "message": "Please provide order ids or date duration"
}
```

---

### Place Order

Place order in Dropshipzone. After this API is called, the order will be created in Dropshipzone account as a "Not Submitted" order. User should then login to Dropshipzone website and pay for the orders.

**Endpoint:** `POST https://api.dropshipzone.com.au/placingOrder`

#### Headers

| Field | Type | Description |
|-------|------|-------------|
| Authorization | String | jwt xxxxxxxxxxxxx (token created in auth) |
| Content-Type | String | application/json |

**Example:**
```json
{
  "Authorization": "jwt xxxxxxxxxxxxx",
  "Content-Type": "application/json"
}
```

#### Request Body

| Field | Type | Description |
|-------|------|-------------|
| your_order_no | String | Your unique Order Number |
| first_name | String | Consignee first name |
| last_name | String | Consignee last name |
| address1 | String | Consignee address first line |
| address2 | String | Consignee address second line |
| suburb | String | Consignee address suburb |
| state | String | Consignee address state |
| postcode | String | Consignee address postcode |
| telephone | String | Consignee telephone |
| comment | String | Order notes |
| order_items | Array | Array of order items |
| order_items[].sku | String | Order item SKU |
| order_items[].qty | Number | Order item quantity |

**Example:**
```json
{
  "your_order_no": "PM2132342434",
  "first_name": "John",
  "last_name": "Baker",
  "address1": "add1",
  "address2": "add2",
  "suburb": "Eugowra",
  "state": "Australian Capital Territory",
  "postcode": "2806",
  "telephone": "0412345678",
  "comment": "comment test456",
  "order_items": [
    {
      "sku": "FURNI-E-TOY200-8BIN-WH",
      "qty": 1
    },
    {
      "sku": "MOC-09M-2P-BK",
      "qty": 3
    }
  ]
}
```

#### Success Response

```http
HTTP/1.1 200
[
  {
    "status": 1,
    "serial_number": "P02100689"
  }
]
```

#### Error Response

```http
HTTP/1.1 200
[
  {
    "status": -1,
    "serial_number": "P02100689",
    "errmsg": "order_id not has to be unique"
  },
  {
    "status": -1,
    "serial_number": "P02100689",
    "errmsg": "The postcode cannot be found"
  },
  {
    "status": -1,
    "serial_number": "P02100689",
    "errmsg": "The postcode 3000 does not exist in the Sydney city"
  },
  {
    "status": -1,
    "serial_number": "P02100689",
    "errmsg": "The email cannot be found"
  },
  {
    "status": -1,
    "serial_number": "P02100689",
    "errmsg": "Sorry, we do not have enough SKU: FURNI-E-TOY200-8BIN-WH in stock to fulfill"
  },
  {
    "status": -1,
    "serial_number": "P02100689",
    "errmsg": "The SKU: FURNI-E-TOY200-8BIN-WH does not exist"
  },
  {
    "status": -1,
    "serial_number": "P02100689",
    "errmsg": "order_items sku is required"
  },
  {
    "status": -1,
    "serial_number": "P02100689",
    "errmsg": "order_items qty is required"
  }
]
```

---

## Product

### Get Stock

Get stock level of a product. 10 days is the limit.

**Endpoint:** `POST https://api.dropshipzone.com.au/stock`

#### Headers

| Field | Type | Description |
|-------|------|-------------|
| Authorization | String | jwt xxxxxxxxxxxxx (token created in auth) |
| Content-Type | String | application/json |

**Example:**
```json
{
  "Authorization": "jwt xxxxxxxxxxxxx",
  "Content-Type": "application/json"
}
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| start_time | String | Optional | Start Time of stock level. e.g. 2020-8-03 05:11:44 |
| end_time | String | Optional | End Time of stock level. e.g. 2020-8-10 05:11:44 |
| page_no | Number | Optional | Page number with default 1 (Default: 1) |
| limit | Number | Optional | Limit of the result with default 40, minimum 40 and maximum 160 (Default: 40) |
| skus | String | Optional | Product SKU. e.g. FA-CHAIR-DIN470-BK,XMAS-ROPE-50M-WH |

**Example:**
```json
{
  "start_time": "2020-8-03 05:11:44",
  "end_time": "2020-8-10 05:11:44",
  "page_no": 1,
  "limit": 60,
  "skus": "FURNI-L-COF01-BK-AB"
}
```

#### Success Response

```http
HTTP/1.1 200
{
  "result": [
    {
      "sku": "FURNI-L-COF01-BK-AB",
      "created_at": "2020-08-03T05:36:16.000Z",
      "new_qty": "0.00",
      "status": "Out Of Stock"
    }
  ],
  "total": 1,
  "page_no": 1,
  "limit": 60
}
```

#### Error Response

```http
HTTP/1.1 500
{
  "code": "InternalServer",
  "message": "caused by InvalidArgumentError: The range must be less than 10 days apart."
}
```

---

### V2 Get Products

Retrieve a list of products.

**Endpoint:** `GET https://api.dropshipzone.com.au/v2/products`

#### Headers

| Field | Type | Description |
|-------|------|-------------|
| Authorization | String | jwt xxxxxxxxxxxxx (token created in auth) |
| Content-Type | String | application/json |

**Example:**
```json
{
  "Authorization": "jwt xxxxxxxxxxxxx",
  "Content-Type": "application/json"
}
```

#### Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| category_id | Number | Optional | Category id |
| enabled | Boolean | Optional | Whether the product is enabled (Default: true) |
| in_stock | Boolean | Optional | Whether the product is in stock |
| au_free_shipping | Boolean | Optional | Whether the product is free shipping in Australia |
| nz_available | Boolean | Optional | Whether the product is available in New Zealand |
| on_promotion | Boolean | Optional | Whether the product is on promotion |
| new_arrival | Boolean | Optional | Whether the product is newly launched |
| supplier_ids | String | Optional | Get products from certain suppliers only (Comma separated supplier ids, up to 50) |
| exclude_supplier_ids | String | Optional | Exclude products from certain suppliers (Comma separated supplier ids, up to 50) |
| skus | String | Optional | Comma separated product SKUs, up to 50 |
| keywords | String | Optional | Comma separated keywords, up to 20 |
| page_no | Number | Optional | Page number with default 1 (Default: 1) |
| limit | Number | Optional | Page size with default 50, minimum 50 and maximum 200 (Default: 50) |
| sort_by | String | Optional | Sort by which field (Allowed: price) |
| sort_order | String | Optional | Sort order (Allowed: asc, desc) |

#### Success Response (After 30th Sep 2025)

```http
HTTP/1.1 200
{
  "result": [
    {
      "l1_category_id": 719,
      "l1_category_name": "Appliances",
      "l2_category_id": 720,
      "l2_category_name": "Air Conditioners",
      "l3_category_id": 722,
      "l3_category_name": "Evaporative Coolers",
      "entity_id": 186573,
      "Category": "Appliances > Air Conditioners > Evaporative Coolers",
      "ETA": "",
      "discontinued": "No",
      "discontinuedproduct": "No",
      "product_status": 1,
      "RrpPrice": 58.61,
      "RRP": {
        "Standard": 58.61
      },
      "vendor_id": "201",
      "Vendor_Product": 1,
      "brand": "Does not apply",
      "cbm": 0.0016,
      "colour": "",
      "cost": 29.31,
      "currency": "AUD",
      "desc": "product description",
      "eancode": "729604212984",
      "height": 8.5,
      "length": 15,
      "weight": 0.336,
      "width": 12.5,
      "in_stock": "1",
      "status": "In Stock",
      "stock_qty": 44,
      "sku": "V201-W12898984",
      "special_price": null,
      "special_price_from_date": "",
      "special_price_end_date": "",
      "rebate_percentage": 10,
      "rebate_start_date": "2025-05-14 00:00:00",
      "rebate_end_date": "2025-05-16 23:59:59",
      "title": "12V Portable Car Fan Heater Vehicle Heating Windscreen Defro",
      "website_url": "https://www.dropshipzone.com.au/12v-portable-car-fan-h",
      "updated_at": 1720503883,
      "price": 29.31,
      "gallery": [
        "https://cdn.dropshipzone.com.au/media/catalog/product/V/2/V201-W1",
        "https://cdn.dropshipzone.com.au/media/catalog/product/V/2/V201-W1"
      ],
      "freeshipping": "0",
      "is_new_arrival": true,
      "is_direct_import": true
    }
  ],
  "total": 91,
  "total_pages": 5,
  "current_page": 1
}
```

#### Success Response (Before 30th Sep 2025)

```http
HTTP/1.1 200
{
  "result": [
    {
      "l1_category_id": 719,
      "l1_category_name": "Appliances",
      "l2_category_id": 720,
      "l2_category_name": "Air Conditioners",
      "l3_category_id": 722,
      "l3_category_name": "Evaporative Coolers",
      "entity_id": 186573,
      "Category": "Appliances > Air Conditioners > Evaporative Coolers",
      "ETA": "",
      "discontinued": "No",
      "discontinuedproduct": "No",
      "product_status": 1,
      "RrpPrice": 58.61,
      "RRP": {
        "Standard": 58.61
      },
      "vendor_id": "201",
      "Vendor_Product": 1,
      "brand": "Does not apply",
      "cbm": 0.0016,
      "colour": "",
      "cost": 29.31,
      "currency": "AUD",
      "desc": "product description",
      "eancode": "729604212984",
      "height": 8.5,
      "length": 15,
      "weight": 0.336,
      "width": 12.5,
      "in_stock": "1",
      "status": "In Stock",
      "stock_qty": 44,
      "sku": "V201-W12898984",
      "special_price": null,
      "special_price_from_date": "",
      "special_price_end_date": "",
      "rebate_percentage": 10,
      "rebate_start_date": "2025-05-14 00:00:00",
      "rebate_end_date": "2025-05-16 23:59:59",
      "title": "12V Portable Car Fan Heater Vehicle Heating Windscreen Defro",
      "website_url": "https://www.dropshipzone.com.au/12v-portable-car-fan-h",
      "zone_rates": {
        "act": 0,
        "nsw_m": 0,
        "nsw_r": 0,
        "nt_m": 10,
        "nt_r": 5,
        "qld_m": 0,
        "qld_r": 10,
        "remote": 10,
        "sa_m": 0,
        "sa_r": 0,
        "tas_m": 0,
        "tas_r": 0,
        "vic_m": 0,
        "vic_r": 5,
        "wa_m": 10,
        "wa_r": 10,
        "nz": 9999
      },
      "updated_at": 1720503883,
      "price": 29.31,
      "gallery": [
        "https://cdn.dropshipzone.com.au/media/catalog/product/V/2/V201-W1",
        "https://cdn.dropshipzone.com.au/media/catalog/product/V/2/V201-W1"
      ],
      "freeshipping": "0",
      "is_new_arrival": true,
      "is_direct_import": true
    }
  ],
  "total": 91,
  "total_pages": 5,
  "current_page": 1
}
```

#### Error Response

```http
HTTP/1.1 400
{
  "code": "ResourceNotFound",
  "message": "Bad Request"
}
```

---

## Shipping

### Get Zone Mapping

Get Zone Mapping Data. **Will be deprecated after 30th Sep 2025.**

**Endpoint:** `POST https://api.dropshipzone.com.au/get_zone_mapping`

#### Headers

| Field | Type | Description |
|-------|------|-------------|
| Authorization | String | jwt xxxxxxxxxxxxx (token created in auth) |
| Content-Type | String | application/json |

**Example:**
```json
{
  "Authorization": "jwt xxxxxxxxxxxxx",
  "Content-Type": "application/json"
}
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| postcode | String | Optional | The postcode to map. e.g. "2823,6302" |
| page_no | Number | Optional | Page number with default 1 (Default: 1) |
| limit | Number | Optional | Limit of the result with default 40, minimum 40 and maximum 160 (Default: 40) |

**Example:**
```json
{
  "postcode": "2823,6302,2002,4001",
  "page_no": 1,
  "limit": 40
}
```

#### Success Response

```http
HTTP/1.1 200
{
  "result": [
    {
      "postcode": "2823",
      "zone": "NSW_R"
    },
    {
      "postcode": "6302",
      "zone": "WA_R"
    },
    {
      "postcode": "2002",
      "zone": "NSW_R"
    },
    {
      "postcode": "4001",
      "zone": "QLD_M"
    }
  ],
  "total": 2,
  "total_pages": 1,
  "current_page": 1,
  "page_size": 40,
  "code": 1,
  "message": "ok"
}
```

#### Error Response

```http
HTTP/1.1 500
{
  "code": 0,
  "data": [],
  "message": "no data"
}
```

---

### Get Zone Rates

Get Zone Rates. **Will be deprecated after 30th Sep 2025.**

**Endpoint:** `POST https://api.dropshipzone.com.au/get_zone_rates`

#### Headers

| Field | Type | Description |
|-------|------|-------------|
| Authorization | String | jwt xxxxxxxxxxxxx (token created in auth) |
| Content-Type | String | application/json |

**Example:**
```json
{
  "Authorization": "jwt xxxxxxxxxxxxx",
  "Content-Type": "application/json"
}
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| skus | String | Optional | The SKUs to map. e.g. "3DF-ABS-1KG-BK,3DP-ED3-GB-BK" |
| page_no | Number | Optional | Page number with default 1 (Default: 1) |
| limit | Number | Optional | Limit of the result with default 40, minimum 40 and maximum 160 (Default: 40) |

**Example:**
```json
{
  "skus": "AES-KB22K,AES-T001B,AES-T002",
  "page_no": 1,
  "limit": 40
}
```

#### Success Response

```http
HTTP/1.1 200
{
  "result": [
    {
      "sku": "AES-KB22K",
      "act_r": "8",
      "nsw_m": "8",
      "nsw_r": "10",
      "nt_m": "24",
      "nt_r": "31",
      "qld_m": "11",
      "qld_r": "16",
      "remote": "26",
      "sa_m": "8",
      "sa_r": "16",
      "tas_m": "8",
      "tas_r": "11",
      "vic_m": "4",
      "vic_r": "8",
      "wa_m": "17",
      "wa_r": "28"
    },
    {
      "sku": "AES-T001B",
      "act_r": "9",
      "nsw_m": "9",
      "nsw_r": "11",
      "nt_m": "26",
      "nt_r": "33",
      "qld_m": "12",
      "qld_r": "18",
      "remote": "29",
      "sa_m": "9",
      "sa_r": "17",
      "tas_m": "9",
      "tas_r": "12",
      "vic_m": "4",
      "vic_r": "9",
      "wa_m": "19",
      "wa_r": "31"
    },
    {
      "sku": "AES-T002",
      "act_r": "0",
      "nsw_m": "0",
      "nsw_r": "0",
      "nt_m": "0",
      "nt_r": "0",
      "qld_m": "0",
      "qld_r": "0",
      "remote": "0",
      "sa_m": "0",
      "sa_r": "0",
      "tas_m": "0",
      "tas_r": "0",
      "vic_m": "0",
      "vic_r": "0",
      "wa_m": "0",
      "wa_r": "0"
    }
  ],
  "total": 3,
  "total_pages": 1,
  "current_page": 1,
  "page_size": 40,
  "code": 1,
  "message": "ok"
}
```

#### Error Response

```http
HTTP/1.1 500
{
  "code": 0,
  "data": [],
  "message": "no data"
}
```

---

### V2 Get Zone Mapping (NEW)

V2 Get Zone Mapping Data.

**Endpoint:** `POST https://api.dropshipzone.com.au/v2/get_zone_mapping`

#### Headers

| Field | Type | Description |
|-------|------|-------------|
| Authorization | String | jwt xxxxxxxxxxxxx (token created in auth) |
| Content-Type | String | application/json |

**Example:**
```json
{
  "Authorization": "jwt xxxxxxxxxxxxx",
  "Content-Type": "application/json"
}
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| postcode | String | Optional | The postcode to map. e.g. "2823,6302" |
| page_no | Number | Optional | Page number with default 1 (Default: 1) |
| limit | Number | Optional | Limit of the result with default 40, minimum 40 and maximum 160 (Default: 40) |

**Example:**
```json
{
  "postcode": "2823,6302,2002,4001",
  "page_no": 1,
  "limit": 40
}
```

#### Success Response

```http
HTTP/1.1 200
{
  "result": [
    {
      "postcode": "2823",
      "standard": "nsw_r"
    },
    {
      "postcode": "6302",
      "standard": "wa_r",
      "defined": "wa_near_country",
      "advanced": "sw_wa"
    },
    {
      "postcode": "2002",
      "standard": "nsw_m",
      "advanced": "sydney_pob"
    },
    {
      "postcode": "4001",
      "standard": "qld_m",
      "defined": "brisbane"
    }
  ],
  "total": 4,
  "total_pages": 1,
  "current_page": 1,
  "page_size": 40,
  "code": 1,
  "message": "ok"
}
```

#### Error Response

```http
HTTP/1.1 500
{
  "code": 0,
  "data": [],
  "message": "no data"
}
```

---

### V2 Get Zone Rates (NEW)

V2 Get Zone Rates.

**Endpoint:** `POST https://api.dropshipzone.com.au/v2/get_zone_rates`

#### Headers

| Field | Type | Description |
|-------|------|-------------|
| Authorization | String | jwt xxxxxxxxxxxxx (token created in auth) |
| Content-Type | String | application/json |

**Example:**
```json
{
  "Authorization": "jwt xxxxxxxxxxxxx",
  "Content-Type": "application/json"
}
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| skus | String | Optional | The SKUs to map. e.g. "3DF-ABS-1KG-BK,3DP-ED3-GB-BK" |
| page_no | Number | Optional | Page number with default 1 (Default: 1) |
| limit | Number | Optional | Limit of the result with default 40, minimum 40 and maximum 160 (Default: 40) |

**Example:**
```json
{
  "skus": "AES-KB22K,AES-T001B,AES-T002",
  "page_no": 1,
  "limit": 40
}
```

#### Success Response

The response includes three types of zone rates:
- **standard**: Basic zone rates
- **defined**: Defined zone rates
- **advanced**: Advanced zone rates

Each SKU will have an `active` flag indicating if the rates are active.

```http
HTTP/1.1 200
{
  "result": [
    {
      "sku