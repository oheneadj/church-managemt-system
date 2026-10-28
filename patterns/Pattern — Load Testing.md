# Pattern — Load Testing

> Read this file before running a load test, or before launch when you need to know how the system behaves under traffic.

## 1. When to Read This

Read this file before:

- A major product launch where you expect a traffic spike
- Adding a feature that will be used by many concurrent users (checkout, payment, search)
- After a significant architecture change that may affect performance
- When you have SLA or performance targets to validate before going live
- When the system has never been tested under realistic load and you are about to expose it to real traffic

**The rule:** test under realistic load in staging before exposing the system to real production traffic. Discovering a bottleneck in production costs far more — in user trust, in revenue, in emergency work — than discovering it in a test.

## 2. Load Test Types

| Test type | What it answers | How it runs |
| --- | --- | --- |
| **Smoke test** | Does the system work at all? Is the test script correct? | 1–5 VUs for 1–5 minutes |
| **Load test** | Does the system meet targets under normal expected load? | Target VUs for 10–30 minutes |
| **Stress test** | Where is the breaking point? What fails first? | Ramp up beyond expected load until failure |
| **Spike test** | How does the system handle a sudden burst of traffic? | Ramp up instantly to 10× normal, then back down |
| **Soak test** | Does the system degrade over time? (memory leaks, connection pool exhaustion) | Normal load for several hours |

**Run them in this order:**

1. Smoke test first — verify the script works before wasting time on a full load test
2. Load test — validate against your targets
3. Stress test — find the breaking point
4. Spike test — if you expect sudden bursts (product launch, marketing campaign)
5. Soak test — if the system will run continuously for days without restarts

**Tools:**

- **k6** — recommended; JavaScript test scripts, excellent CLI, CI-friendly, open source
- **Artillery** — YAML or JS; good for complex scenarios, built-in reporting
- **Locust** — Python; good for complex user flows
- **Gatling** — Scala/Java; excellent for high-load scenarios, good reporting

## 3. What to Measure & Thresholds

**The four key metrics:**

**Response time (latency):**

- Measure at percentiles — not just average. The average hides the worst-case experience.
- `p50` (median) — half of users are faster than this
- `p95` — 95% of users are faster than this; the realistic worst case for most users
- `p99` — 99% of users are faster than this; the true tail experience
- Typical targets: p95 < 500ms for API endpoints, p95 < 2s for page loads

**Error rate:**

- Percentage of requests returning non-2xx responses
- Target: < 1% for most systems; < 0.1% for payment endpoints
- A rising error rate under load is the most critical signal

**Throughput (requests per second, RPS):**

- How many requests per second the system handles
- Define your target RPS based on expected peak traffic
- At what RPS does the error rate start to climb? That is your ceiling.

**Concurrent users / Virtual Users (VUs):**

- How many simultaneous users the system can serve
- Estimate based on expected peak sessions × average session duration

**Setting thresholds before the test:** Define your pass/fail criteria before running the test — not after seeing the results.

```js
// k6 thresholds
export const options = {
  thresholds: {
    http_req_duration: ['p95<500'],    // 95% of requests under 500ms
    http_req_failed:   ['rate<0.01'],  // error rate under 1%
  },
};
```

**What to monitor during the test (not just in k6 output):**

- Database query time and connection pool usage
- CPU and memory on application servers
- Redis memory and connection count
- Queue depth and worker utilization
- Error rates in Sentry / Flare
- Replication lag if using read replicas

## 4. k6 Practical Patterns

**Basic load test structure:**

```js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '2m', target: 50  },   // ramp up to 50 VUs
    { duration: '5m', target: 50  },   // stay at 50 VUs
    { duration: '2m', target: 0   },   // ramp down
  ],
  thresholds: {
    http_req_duration: ['p95<500'],
    http_req_failed:   ['rate<0.01'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'https://staging.yourapp.com';

export default function () {
  // Browse car catalogue
  const catalogueRes = http.get(`${BASE_URL}/api/v1/cars?status=available&page=1`);
  check(catalogueRes, {
    'catalogue status 200':     (r) => r.status === 200,
    'catalogue has data':       (r) => JSON.parse(r.body).data.length > 0,
    'catalogue under 500ms':    (r) => r.timings.duration < 500,
  });

  sleep(1);

  // View a specific car
  const carRes = http.get(`${BASE_URL}/api/v1/cars/car_01J8K`);
  check(carRes, { 'car detail status 200': (r) => r.status === 200 });

  sleep(2);
}
```

**Authenticated requests:**

```js
export function setup() {
  // Login once and return the token for all VUs
  const res = http.post(`${BASE_URL}/api/v1/auth/login`, JSON.stringify({
    email: 'loadtest@example.com',
    password: __ENV.LOAD_TEST_PASSWORD,
  }), { headers: { 'Content-Type': 'application/json' } });

  return { token: res.json('token') };
}

export default function (data) {
  const headers = { Authorization: `Bearer ${data.token}` };
  const res = http.get(`${BASE_URL}/api/v1/orders`, { headers });
  check(res, { 'orders 200': (r) => r.status === 200 });
  sleep(1);
}
```

**Run against staging, never production:**

```bash
# Smoke test
k6 run --vus 2 --duration 30s load-test.js

# Full load test against staging
BASE_URL=https://staging.yourapp.com k6 run load-test.js

# With output to InfluxDB / Grafana for real-time dashboard
k6 run --out influxdb=http://localhost:8086/k6 load-test.js
```

**What to do after the test:**

1. Review k6 summary — check all thresholds
2. Review DB slow query log — what queries were slow under load?
3. Review error logs — what errors appeared that were not visible under normal load?
4. Check connection pool metrics — were connections exhausted?
5. Document the results: what load the system handled, where it started to struggle, what was fixed

## 5. Load Testing Checklist

**Before the test:**

- [ ] Performance targets defined before running the test (not after seeing results)?
- [ ] Thresholds configured in the test script (`p95`, error rate)?
- [ ] Test script smoke tested with 1–2 VUs to confirm it works?
- [ ] Test running against staging — not production?
- [ ] Staging data representative of production (similar volume, similar data distribution)?
- [ ] Monitoring dashboards open during the test (DB metrics, CPU, memory, error rates)?

**During the test:**

- [ ] DB connection pool usage monitored?
- [ ] Error rate monitored in real time?
- [ ] No test traffic leaking to external services (payment providers, email, SMS)?

**After the test:**

- [ ] All thresholds met?
- [ ] DB slow query log reviewed for queries that slowed under load?
- [ ] Error logs reviewed for new errors?
- [ ] Connection pool metrics reviewed — no exhaustion?
- [ ] Results documented — what load was tested, what passed, what failed, what was fixed?
- [ ] Bottlenecks identified and a fix plan in place before launch?
