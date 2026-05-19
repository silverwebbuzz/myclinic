# Sprint 12 — Test cases

## Automated tests

```bash
composer test
# or: ./vendor/bin/phpunit
```

| Suite | File | Covers |
|-------|------|--------|
| Unit | `SeatServiceTest` | Seat limits, extra seats |
| Unit | `ModuleGateTest` | Module cache, 402 require |
| Unit | `EventBusTest` | Event row + visit.completed → invoice |
| Unit | `QueryBuilderTest` | forClinic scoping |
| Unit | `UploadValidatorTest` | UUID names, size limits |
| Integration | `ClinicalFlowTest` | Patient → visit → invoice → pay |
| Integration | `SpecialtySmokeTest` | GP, homeo, dental, derma visits |
| Isolation | `MultiTenantIsolationTest` | Cross-clinic 404/null |

## Health & ops

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Health | `curl /health` | `status`, `checks`, `version`, 200 | ☐ |
| 2 | Degraded | Stop Redis | `status: degraded`, still 200 if DB ok | ☐ |
| 3 | Backup | `bash workers/backup.sh` | `.sql.gz` in `storage/backups/` | ☐ |
| 4 | Sentry | Trigger test exception with `SENTRY_DSN` | Event in Sentry or `storage/logs/sentry.log` | ☐ |

## Security & GDPR

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 5 | Upload MIME | POST non-image to logo | Rejected | ☐ |
| 6 | GDPR export | Admin → patient → export | ZIP download | ☐ |
| 7 | Anonymize | Admin POST anonymize | Name `Anonymized #id`, inactive | ☐ |
| 8 | OWASP doc | Review `document/security-audit.md` | Signed off | ☐ |

## Performance

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 9 | k6 | `k6 run scripts/k6/load-health.js` | p99 &lt; 300ms documented | ☐ |
| 10 | Indexes | Apply `011_sprint12_perf_indexes.sql` if needed | No duplicate index errors | ☐ |

## Go-live

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 11 | Checklist | `document/go-live-checklist.md` | All critical items checked | ☐ |
| 12 | Production env | Copy `.env.production.example` | No debug, live keys only on prod | ☐ |
