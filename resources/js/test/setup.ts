import '@testing-library/jest-dom/vitest';
import { cleanup, configure } from '@testing-library/react';
import { afterEach } from 'vitest';

// findBy and waitFor wait 1 second by default. With every test file running in parallel on a busy machine a
// screen can take longer than that to appear, which showed up as random failures, so they wait up to 5 seconds
// (and still return the moment the thing appears).
configure({ asyncUtilTimeout: 5000 });

afterEach(() => cleanup());
