import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    public_and_dashboard_browse: {
      executor: 'ramping-vus',
      startVUs: 5,
      stages: [
        { duration: '30s', target: 50 },
        { duration: '1m', target: 150 },
        { duration: '30s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<400'],
    http_req_failed: ['rate<0.005'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const TENANT_URL = __ENV.TENANT_URL || 'http://pilot-lgs.localhost:8000';

export default function () {
  const central = http.get(`${BASE_URL}/`);
  check(central, { 'central landing 200': (r) => r.status === 200 });

  const tenant = http.get(`${TENANT_URL}/`);
  check(tenant, { 'tenant portal 200': (r) => r.status === 200 });

  const health = http.get(`${BASE_URL}/up`);
  check(health, { 'health 200': (r) => r.status === 200 });

  sleep(1);
}
