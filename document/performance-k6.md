# k6 load test — ManageClinic

## Script

`scripts/k6/load-health.js` — ramps to **1000 VU** over ~10 minutes against `/health`.

## Run

```bash
# Install k6: https://k6.io/docs/get-started/installation/
k6 run scripts/k6/load-health.js
# With custom target:
BASE_URL=https://app.manageclinic.com k6 run scripts/k6/load-health.js
```

## Baseline results (document after run)

| Metric | Target | Measured |
|--------|--------|----------|
| p99 latency | &lt; 300 ms | _TBD_ |
| Error rate | &lt; 1% | _TBD_ |
| RPS at 1000 VU | — | _TBD_ |

## If p99 &gt; 300 ms

1. Run migration `011_sprint12_perf_indexes.sql`.
2. Enable Redis for tenant/module caches.
3. Review slow query log on `patients`, `appointments`, `visits`, `notifications`.
4. Re-run k6 and update this table.

## Extended scenarios (optional)

- Authenticated dashboard: export session cookie, hit `/api/v1/dashboard/queue`.
- Public booking: `GET /book/demo/slots`.
