# Test Plan — <name>

> Template (SI.5). Records what is verified, how, and the results.

## 1. Scope

What change/CR this plan verifies.

## 2. Test items

| Item | Type | Priority |
| --- | --- | --- |
| | unit / integration / e2e | |

## 3. Approach

| Level | Covered here | Tool |
| --- | --- | --- |
| Static | `php -l`, `composer validate` | CI |
| Unit | | PHPUnit |
| Integration | | PHPUnit + temp SQLite |
| End-to-end | | manual `demo.md` |

## 4. Test cases

| ID | Requirement | Preconditions | Steps | Expected | Result |
| --- | --- | --- | --- | --- | --- |
| TC-1 | FR-1 | | | | ☐ pass ☐ fail |
| TC-2 | NFR-1 (double vote refused) | | | | ☐ |

### Priority cases for this product

- TC-A: second ballot submit is refused (atomic UPDATE).
- TC-B: ballot opens only when voter constituency == booth constituency.
- TC-C: kiosk authorization expires (~2 min) and is consumed on submit.
- TC-D: kiosk session cannot reach `admin/*`.
- TC-E: `db.php` migrations are safe to re-run (idempotent).

## 5. Environment

PHP version: ___ · SQLite: ___ · Browser: ___ · HTTPS/localhost: ___

## 6. Entry / exit criteria

- **Entry:** code complete on a branch; CI green.
- **Exit:** all Must cases pass; failures logged as defects; evidence recorded.

## 7. Results summary

| Metric | Value |
| --- | --- |
| Cases run | |
| Passed | |
| Failed | |
| Defects raised | |

## 8. Notes / deviations

