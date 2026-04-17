# External Sync API (for Flutter / another server)

This API allows another machine/app to fetch HR data from this server.

## 1) Enable API key

Set this in `.env`:

```env
EXTERNAL_SYNC_API_KEY=your-strong-secret-key
```

Then clear cache:

```bash
php artisan optimize:clear
```

## 2) Base URL

If your app runs at `http://localhost/PHDHRM`, then:

- Base API URL: `http://localhost/PHDHRM/api/integration/v1`

For another machine, use server IP/domain instead of `localhost`.

## 3) Authentication header

Send header on every request:

```http
X-API-KEY: your-strong-secret-key
Accept: application/json
```

## 4) Endpoints

### Health check

`GET /api/integration/v1/health`

### Employees (paginated)

`GET /api/integration/v1/employees`

Query params:

- `per_page` (1-200, default 50)
- `page` (default 1)
- `updated_since` (ISO datetime or `Y-m-d H:i:s`)
- `department_id`
- `is_active` (`1` or `0`)
- `q` (search by employee_id / official_id_10 / name / phone)

### Employee detail

`GET /api/integration/v1/employees/{id}`

### Departments / Org units

`GET /api/integration/v1/departments`

Query params:

- `updated_since`
- `is_active` (`1` or `0`)

## 5) Sample cURL

```bash
curl -H "X-API-KEY: your-strong-secret-key" ^
     -H "Accept: application/json" ^
     "http://localhost/PHDHRM/api/integration/v1/employees?per_page=20&page=1"
```

## 6) Common errors

- `401 Unauthorized API key.` → wrong or missing `X-API-KEY`
- `503 External API key is not configured on server.` → `.env` key missing
- `422 Invalid updated_since format...` → invalid datetime format

