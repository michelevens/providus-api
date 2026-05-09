# Webhooks

Credentik delivers events to your URL via signed HTTP POST when claims, payments, applications, or providers change state.

## Subscribing

```http
POST /api/v1/webhooks
Authorization: Bearer <your-token>
Content-Type: application/json

{
  "url": "https://your-domain.com/credentik-webhook",
  "events": ["claim.paid", "claim.denied", "payment.posted"]
}
```

Response includes `secret` exactly once — store it. You'll need it to verify signatures on every delivery.

Subscribe to all events with `"events": ["*"]`.

## Available events

| Event | When it fires |
|---|---|
| `application.status_changed` | Any payer-enrollment application changes status |
| `application.approved` | Application reaches approved status |
| `application.denied` | Application reaches denied/rejected status |
| `claim.submitted` | Claim transitions to submitted |
| `claim.paid` | Claim balance reaches zero |
| `claim.denied` | Claim transitions to denied |
| `payment.posted` | Payment is recorded against one or more claims |

## Delivery format

```http
POST https://your-domain.com/credentik-webhook
Content-Type: application/json
X-Credentik-Event: claim.paid
X-Credentik-Delivery-Id: 9f8b2c4a-3d1e-4f56-9876-1234567890ab
X-Credentik-Signature: 7c8e9b4a3d2c1f0e9d8b7a6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0a9b8c7d6e

{
  "event": "claim.paid",
  "delivery_id": "9f8b2c4a-3d1e-4f56-9876-1234567890ab",
  "data": {
    "claim_id": 1234,
    "claim_number": "CLM-001234",
    "patient_name": "Jane Doe",
    "payer_name": "BCBS of Florida",
    "total_charges": 250.00,
    "total_paid": 250.00,
    "balance": 0,
    "status": "paid"
  },
  "timestamp": "2026-05-08T14:23:01+00:00"
}
```

Respond with any `2xx` status to acknowledge. Anything else triggers retry.

## Verifying the signature

The signature is `HMAC-SHA256(raw_request_body, your_webhook_secret)` rendered as lowercase hex. Always verify on **the raw request body** before any JSON parsing — re-encoded JSON will not match.

### PHP

```php
$secret = getenv('CREDENTIK_WEBHOOK_SECRET');
$body = file_get_contents('php://input');
$expected = hash_hmac('sha256', $body, $secret);
$received = $_SERVER['HTTP_X_CREDENTIK_SIGNATURE'] ?? '';

if (!hash_equals($expected, $received)) {
    http_response_code(401);
    exit;
}
$event = json_decode($body, true);
```

### Node.js (Express + raw body)

```js
const crypto = require('crypto');
const express = require('express');
const app = express();

app.post('/credentik-webhook',
  express.raw({ type: 'application/json' }),
  (req, res) => {
    const expected = crypto
      .createHmac('sha256', process.env.CREDENTIK_WEBHOOK_SECRET)
      .update(req.body)                      // raw Buffer
      .digest('hex');
    const received = req.get('X-Credentik-Signature') || '';

    if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(received))) {
      return res.status(401).end();
    }
    const event = JSON.parse(req.body.toString('utf8'));
    res.status(200).end();
  }
);
```

### Python (Flask)

```python
import hmac, hashlib, os
from flask import Flask, request, abort

app = Flask(__name__)

@app.post('/credentik-webhook')
def hook():
    secret = os.environ['CREDENTIK_WEBHOOK_SECRET'].encode()
    body = request.get_data()              # raw bytes
    expected = hmac.new(secret, body, hashlib.sha256).hexdigest()
    received = request.headers.get('X-Credentik-Signature', '')
    if not hmac.compare_digest(expected, received):
        abort(401)
    event = request.get_json(force=True)
    return '', 200
```

## Retries and failure handling

| Attempt | Delay before retry |
|---|---|
| 1 → 2 | 10s |
| 2 → 3 | 1m |
| 3 → 4 | 5m |
| 4 → 5 | 30m |
| 5 → 6 | 2h |

After 5 failed attempts the delivery is marked `failed`. After **20 consecutive** terminal failures across deliveries, your webhook is auto-disabled — re-enable via `PUT /api/v1/webhooks/{id}` with `is_active: true` once your endpoint is healthy.

## Idempotency

Every delivery carries a unique `X-Credentik-Delivery-Id`. The same delivery may be retried up to 5 times — your handler must be idempotent. Recommended: persist the delivery ID and short-circuit if you've already processed it.

## Testing

```http
POST /api/v1/webhooks/{id}/test
Authorization: Bearer <your-token>
```

Sends a synthetic `event: "test"` payload to your URL.
