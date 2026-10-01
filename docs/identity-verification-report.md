# Identity verification report (admin API)

Monthly (or weekly/daily) numbers about account (identity) verification: how every attempt ended,
per method, and how many users fall through after a failed Mercado Pago validation.

- **Endpoint:** `GET /api/admin/identity-verification-report`
- **Auth:** admin JWT (`user.admin` middleware) + permission `admin.identity.stats` (same as `GET /api/admin/identity-verification-stats`, which keeps working unchanged).
- **Source:** the append-only `identity_verification_events` table. All counting happens in SQL (MySQL 8+: window functions).
- **Code:** `IdentityVerificationReportController`, `IdentityVerificationReportService`, and `IdentityVerificationAttemptClassifier` (the single place where outcome classes are defined).

> **No data before 2026-09-17.** Events started being recorded with migration `2026_09_17_120000`. There is no backfill:
> ranges that start earlier simply show zeros for the missing days, and requests paid before that date only show up if a later event
> (e.g. an admin decision) proves they were paid.

## Query parameters

| Param         | Required | Values                               | Default | Notes |
|---------------|----------|--------------------------------------|---------|-------|
| `from`        | yes      | date (`YYYY-MM-DD`)                  |         | Inclusive, from 00:00:00 (app timezone). |
| `to`          | yes      | date, `>= from`                      |         | Inclusive, until 23:59:59. |
| `group_by`    | no       | `month` \| `week` \| `day`           | `month` | Bucket for `series`. |
| `method`      | no       | `all` \| `manual` \| `mercado_pago`  | `all`   | The excluded method's section is returned with zeros (shape stays the same). `manual` also zeroes the funnel. |
| `surface`     | no       | string (max 64)                      |         | Matches the event that starts the attempt. |
| `platform`    | no       | string (max 32)                      |         | Idem. |
| `app_version` | no       | string (max 64)                      |         | Idem. |

Invalid params return `422` with Laravel validation errors.

**Client-context filters and manual attempts:** manual events are recorded server-side and currently carry no
`surface`/`platform`/`app_version`, so when any of these filters is set, manual attempts are `0`.

## Response

The body is **not** wrapped in `data`.

```text
{
  filters: { from, to, group_by, method, surface, platform, app_version },
  totals: {
    attempts,                                   // manual.attempts + automatic.attempts
    manual:    { attempts, approved:{count,pct}, rejected:{count,pct}, inconclusive:{count,pct}, pending_review:{count,pct} },
    automatic: { attempts, approved:{count,pct}, rejected:{count,pct}, error:{count,pct}, cancelled:{count,pct}, abandoned:{count,pct} }
  },
  series: [ { period, ...same shape as totals } ],   // one entry per period in [from, to], empty periods included with zeros
  funnel: {
    failed_users,
    resolved:   { count, pct, by_method: { mercado_pago, manual, mp_rejection_approved, admin_edit } },
    unresolved: { count, pct },
    unlinked_failures
  }
}
```

- `pct` is a percentage (0–100) rounded to 2 decimals. Manual classes are a % of `manual.attempts`, automatic classes a % of
  `automatic.attempts`, funnel groups a % of `failed_users`. Division by zero yields `0`. Whole numbers serialize without decimals (`50`, not `50.0`).
- `period` format: `month` → `YYYY-MM`; `week` → date of that ISO week's Monday (`YYYY-MM-DD`, may be before `from`); `day` → `YYYY-MM-DD`.
- Each attempt is counted in the period in which it **started**. Its outcome is its state **as of now** (a request paid in September and approved in
  October counts as a September approved attempt), so recent periods keep changing while decisions are pending.

### Example

`GET /api/admin/identity-verification-report?from=2026-09-01&to=2026-10-31`

```json
{
  "filters": {
    "from": "2026-09-01",
    "to": "2026-10-31",
    "group_by": "month",
    "method": "all",
    "surface": null,
    "platform": null,
    "app_version": null
  },
  "totals": {
    "attempts": 150,
    "manual": {
      "attempts": 30,
      "approved": { "count": 13, "pct": 43.33 },
      "rejected": { "count": 4, "pct": 13.33 },
      "inconclusive": { "count": 8, "pct": 26.67 },
      "pending_review": { "count": 5, "pct": 16.67 }
    },
    "automatic": {
      "attempts": 120,
      "approved": { "count": 78, "pct": 65 },
      "rejected": { "count": 18, "pct": 15 },
      "error": { "count": 6, "pct": 5 },
      "cancelled": { "count": 8, "pct": 6.67 },
      "abandoned": { "count": 10, "pct": 8.33 }
    }
  },
  "series": [
    {
      "period": "2026-09",
      "attempts": 100,
      "manual": {
        "attempts": 20,
        "approved": { "count": 9, "pct": 45 },
        "rejected": { "count": 3, "pct": 15 },
        "inconclusive": { "count": 6, "pct": 30 },
        "pending_review": { "count": 2, "pct": 10 }
      },
      "automatic": {
        "attempts": 80,
        "approved": { "count": 52, "pct": 65 },
        "rejected": { "count": 12, "pct": 15 },
        "error": { "count": 4, "pct": 5 },
        "cancelled": { "count": 6, "pct": 7.5 },
        "abandoned": { "count": 6, "pct": 7.5 }
      }
    },
    {
      "period": "2026-10",
      "attempts": 50,
      "manual": {
        "attempts": 10,
        "approved": { "count": 4, "pct": 40 },
        "rejected": { "count": 1, "pct": 10 },
        "inconclusive": { "count": 2, "pct": 20 },
        "pending_review": { "count": 3, "pct": 30 }
      },
      "automatic": {
        "attempts": 40,
        "approved": { "count": 26, "pct": 65 },
        "rejected": { "count": 6, "pct": 15 },
        "error": { "count": 2, "pct": 5 },
        "cancelled": { "count": 2, "pct": 5 },
        "abandoned": { "count": 4, "pct": 10 }
      }
    }
  ],
  "funnel": {
    "failed_users": 21,
    "resolved": {
      "count": 12,
      "pct": 57.14,
      "by_method": {
        "mercado_pago": 7,
        "manual": 3,
        "mp_rejection_approved": 1,
        "admin_edit": 1
      }
    },
    "unresolved": { "count": 9, "pct": 42.86 },
    "unlinked_failures": 2
  }
}
```

## Classification rules

All rules live in `STS\Services\IdentityVerificationAttemptClassifier` (PHP methods + the SQL `CASE` fragments the report
uses, both generated from the same constant maps). `IdentityVerificationAttemptClassifierTest` checks every rule in PHP **and**
evaluates the generated SQL on the database to prove both agree.

### Automatic (Mercado Pago OAuth)

**Unit:** one `attempt_started` event (emitted by `GET /api/users/mercadopago-oauth-url`, carries a fresh `attempt_id`).
Its outcome is the latest `succeeded`/`failed` event with the same `attempt_id` (method `mercado_pago`).

| Class       | Rule |
|-------------|------|
| `approved`  | outcome `succeeded` |
| `rejected`  | `failed` with `dni_mismatch`, `name_mismatch`, `both_mismatch` or `missing_identification` |
| `cancelled` | `failed` with `oauth_cancelled` or `oauth_denied` (user cancelled/denied the OAuth screen) |
| `error`     | any other `failed` reason: `token_exchange_failed`, `users_me_failed`, `missing_access_token`, `callback_exception`, `invalid_or_expired_state`, `missing_code_or_state`, `user_not_found`, and any unknown/empty reason |
| `abandoned` | no outcome event for the `attempt_id` (user never came back, or the attempt has no `attempt_id`) |

Failures that cannot be linked to an attempt (no `attempt_id`, e.g. `missing_code_or_state` without a state) are not attempts by
themselves; the attempt they belong to shows up as `abandoned`, and they are counted in `funnel.unlinked_failures` when they have no user.

### Manual (paid DNI + selfie review)

**Unit:** one **paid manual request** — a `manual_identity_validations` row (`related_id` of events with
`related_type = manual_identity_validations`) that has at least one event proving it was paid:
`payment_succeeded`, `docs_submitted`, `succeeded`, `failed`, `info_requested`, or `admin_state_changed` with a status other than `closed`.
The attempt starts at the earliest of those events. Unpaid requests (only `payment_started` / `payment_failed`) are not attempts.

A resubmission after a rejection overwrites the same row (`submission_count + 1`), so **the whole request counts once** with its latest outcome.
The class is given by the request's **latest state event**:

| Latest state event                               | Class            |
|--------------------------------------------------|------------------|
| `succeeded` (admin approve)                      | `approved`       |
| `admin_state_changed` → `approved`               | `approved`       |
| `failed` (admin reject)                          | `rejected`       |
| `admin_state_changed` → `rejected`               | `rejected`       |
| `docs_submitted`                                 | `pending_review` |
| `admin_state_changed` → `pending`                | `pending_review` |
| `payment_succeeded` (paid, no documents yet)     | `inconclusive`   |
| `info_requested` (admin asked for more info)     | `inconclusive`   |
| `closed_after_mp_success`                        | `inconclusive`   |
| `admin_state_changed` → `awaiting_photos`        | `inconclusive`   |
| `admin_state_changed` → `closed`                 | `inconclusive`   |

`upload_rejected`, `payment_started` and `payment_failed` do not change the state.

**Inconclusive** = the paid request did not end in approval or rejection and is not waiting for an admin: the user paid but never sent the
documents, we asked for more information and nothing came back, or the request was closed without a decision (e.g. automatically after
a Mercado Pago success, or by an admin). It includes requests that are still in progress on the user's side.

**pending_review** = documents are in and an admin has to review them.

### Funnel ("who falls through")

1. **failed_users:** distinct users of MP attempts started in range whose class is `rejected` or `error` (cancelled/abandoned are excluded).
2. For each of them, the **earliest approval at or after their first such failure** (no upper bound: a resolution after `to` still counts):

   | `by_method` key          | Approval event |
   |--------------------------|----------------|
   | `mercado_pago`           | a later MP `succeeded` |
   | `manual`                 | a manual request approved (`succeeded`, or `admin_state_changed` → `approved`) |
   | `mp_rejection_approved`  | an admin approved the MP rejection (`succeeded` + `approved_from_mp_rejection`) |
   | `admin_edit`             | an admin set `identity_validated = true` on the profile (`admin_identity_edited` + `validated`) |

   `resolved.count` = users with such an approval (each user attributed to exactly one method); `unresolved` = the rest.
   Later revocations (`verification_reset`, `admin_identity_edited` → `unvalidated`) are not subtracted.
3. **unlinked_failures:** MP `failed` events in range classified `rejected`/`error` with a null `user_id` (no/expired OAuth state, deleted user).
   They cannot be followed, so they are reported apart.

Client-context filters apply to the failed attempts (and to the unlinked failures), not to the approvals.

## Events emitted by admin paths

These close gaps where admin actions changed verification state without leaving an event:

| Path | Event (`method` / `name` / `reason`) | `related_type` | Metadata |
|------|--------------------------------------|----------------|----------|
| `POST /api/admin/manual-identity-validations/{id}/state` (only if `review_status` or `paid` changed) | `manual` / `admin_state_changed` / new `review_status` (`approved`, `rejected`, `pending`, `awaiting_photos`, `closed`) | `manual_identity_validations` | `previous_status`, `previous_paid`, `paid`, `admin_id` |
| `POST /api/admin/mercado-pago-rejected-validations/{id}/review` action `reject` | `manual` / `failed` / `rejected_from_mp_rejection` | `mercado_pago_rejected_validations` | |
| same, action `pending` | `manual` / `info_requested` / `pending_from_mp_rejection` | `mercado_pago_rejected_validations` | |
| `PUT /api/users/modify` (admin update) when `identity_validated` flips | `admin` / `admin_identity_edited` / `validated` \| `unvalidated` | `users` | `admin_id` |
| `UserIdentityVerificationResetService::clearForUser` (`POST /api/admin/users/{user}/clear-identity-validation`) | `admin` / `verification_reset` / – | `users` | `previous_validated`, `previous_validation_type`, `admin_id` |

`admin` is a new `method` value for admin actions on the user that belong to neither flow; it never produces attempts.
The existing MP-rejection approval (`manual` / `succeeded` / `approved_from_mp_rejection`) is unchanged.

## Known limitations

- The old `identity-verification-stats` endpoint counts every `manual` `failed` event as a failure, so MP-rejection rejects
  (`rejected_from_mp_rejection`) now also appear there, just as MP-rejection approvals already appeared as successes.
- An admin setting `paid=false` via the state endpoint emits `admin_state_changed` with the unchanged status; if that is the first
  event of a request it counts as a (paid) attempt. This is an unusual correction path.
- The SQL uses MySQL functions (`DATE_FORMAT`, `WEEKDAY`) and window functions (MySQL 8.0+); the test suite runs on MySQL.
