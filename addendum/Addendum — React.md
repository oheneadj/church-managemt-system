# Addendum — React

> Read this file alongside `CLAUDE.md` at the start of every session on a React project. This file adds React-specific rules on top of the universal baseline. Where this file and `CLAUDE.md` conflict, `CLAUDE.md` wins unless this file explicitly states otherwise.

## Stale Pattern Watch List — React

AI tools frequently generate outdated React patterns. Before finalising any React code, check this list. If you see any of these in your own output — stop and rewrite.

**Class components — never in new code:**

- ❌ `class MyComponent extends React.Component` — use function components only
- ❌ `componentDidMount`, `componentDidUpdate`, `componentWillUnmount` — use `useEffect`
- ❌ `this.setState()`, `this.props` — use `useState`, `useReducer`, props
- ❌ `static getDerivedStateFromProps` — derive state with `useMemo` or during render

**Data fetching — never manual useEffect:**

- ❌ `useEffect(() => { fetch('/api/orders').then(...) }, [])` — use TanStack Query `useQuery`
- ❌ `const [data, setData] = useState(null)` + `const [loading, setLoading] = useState(true)` for server data — use `useQuery` which provides `data`, `isPending`, `isError` automatically
- ❌ `useEffect` to sync two pieces of state — derive with `useMemo` or `computed` value

**Deprecated APIs:**

- ❌ `React.FC` type annotation — annotate props directly: `function Comp({ prop }: Props)`
- ❌ `defaultProps` on function components — use default parameter values in the function signature
- ❌ `PropTypes` — use TypeScript interfaces
- ❌ `React.createRef()` in function components — use `useRef()`
- ❌ `ReactDOM.render()` — use `createRoot()` (React 18+)
- ❌ `React.StrictMode` wrapping only parts of the tree — wrap the entire app
- ❌ `forwardRef` in many cases (React 19+) — refs can be passed as regular props

**Routing — React Router v6+ syntax:**

- ❌ `<Switch>` — use `<Routes>`
- ❌ `<Redirect>` — use `<Navigate>`
- ❌ `useHistory()` — use `useNavigate()`
- ❌ `component={MyPage}` on `<Route>` — use `element={<MyPage />}`
- ❌ `exact` prop on `<Route>` — not needed in v6, all routes are exact by default

**Forms:**

- ❌ Manual `onChange` + `useState` per field for complex forms — use React Hook Form
- ❌ Yup schemas — prefer Zod with `@hookform/resolvers/zod`

**State management:**

- ❌ Redux with `connect()` HOC — use modern Redux Toolkit with `useSelector`/`useDispatch`, or Zustand
- ❌ Context API for server data — use TanStack Query
- ❌ `useReducer` for simple boolean toggles — `useState` is sufficient

**Imports:**

- ❌ `import React from 'react'` at the top of every file — not required since React 17 (new JSX transform)
- ❌ `import ReactDOM from 'react-dom'` — use `import ReactDOM from 'react-dom/client'` (React 18+)

**Version check rule:** always read `package.json` for React, React Router, and TanStack Query versions at session start. API surface has changed significantly across major versions — verify before using.

## Framework-First — React

> Use React. Not plain JavaScript.

React and its ecosystem provide solutions for every common frontend problem. Reaching for raw DOM manipulation, plain `fetch`, or manual event listeners when React has a built-in solution produces code that fights the framework.

**Use the React ecosystem — always:**

- **Data fetching** → TanStack Query (`useQuery`, `useMutation`) — not `useEffect` + `fetch` + `useState`
- **Forms** → React Hook Form + Zod — not manual controlled inputs for complex forms
- **Routing** → React Router (`<Link>`, `useNavigate`, `useParams`, `useSearchParams`) — not `window.location` or `<a href>`
- **State** → `useState`, `useReducer`, Zustand — not global variables or `window` properties
- **Context** → React Context + `useContext` — not prop drilling past 2 levels
- **Refs** → `useRef` — not `document.getElementById()`
- **Effects** → `useEffect` for external system sync only — not for deriving state
- **Lazy loading** → `React.lazy()` + `<Suspense>` — not manual dynamic imports without Suspense
- **Keys** → stable resource ID as `:key` — not array index for reorderable lists
- **Error boundaries** → `<ErrorBoundary>` component — not try/catch inside render

**Use Vite, not CRA:**

- New projects use Vite — `create-react-app` is deprecated and must not be used

**The rule in one line:** before reaching for a plain JS solution, ask — *"Does React Router / TanStack Query / React Hook Form already handle this?"* It almost certainly does.

## 1. How This File Works

This file extends `CLAUDE.md` for React projects. Read both files at the start of every session — `CLAUDE.md` for universal principles, this file for React-specific implementation.

This file covers React with TypeScript as the default. A React project without TypeScript is not acceptable for production — if you encounter one, migrate it.

**Also read the relevant pattern file before building these features:**

- State architecture → `docs/patterns/state-management.md`
- File uploads → `docs/patterns/file-uploads.md`
- Real-time features → `docs/patterns/real-time.md`
- Search → `docs/patterns/search.md`
- Pagination strategy → `docs/patterns/pagination-deep.md`
- Caching → `docs/patterns/caching.md`

## 2. Stack & Version Conventions

- **React 18+** minimum for new projects — concurrent features, `useId`, `useDeferredValue`
- **TypeScript strict mode** — `"strict": true` in `tsconfig.json`, no exceptions
- **Vite** as the build tool — not Create React App (deprecated)
- **React Router v6+** for routing
- **TanStack Query (React Query)** for server state — not `useEffect` + `useState` for API calls
- **Zustand or Jotai** for lightweight global client state — not Redux unless the project already uses it
- **React Hook Form** for forms — not manual controlled inputs for complex forms
- **Zod** for schema validation — paired with React Hook Form via `@hookform/resolvers/zod`
- **ESLint** with `eslint-plugin-react`, `eslint-plugin-react-hooks`, `eslint-plugin-jsx-a11y`
- **Prettier** for formatting — run on every save
- **Vitest** for unit and component tests
- **React Testing Library** for component tests — not Enzyme
- **Playwright** for E2E tests

**Never use:**

- `Create React App` — deprecated, use Vite
- Class components — functional components with hooks only
- `PropTypes` — TypeScript covers this
- `defaultProps` on function components — use default parameter values instead
- `React.FC` type — annotate props directly with `function Component({ prop }: Props)`

## 3. Project Structure & File Naming

```
src/
  components/
    ui/                  ← generic design-system pieces (Button, Input, Card)
    layout/              ← app shell, navigation, page wrappers
    [Feature]/           ← feature-specific components (Orders/, Products/)
  hooks/                 ← custom hooks (useAuth, useDebounce, useIntersection)
  pages/ (or views/)     ← page-level components — thin entry points only
  services/
    api/                 ← all HTTP calls grouped by resource
      orders.ts
      products.ts
      auth.ts
    index.ts             ← configured axios/fetch instance
  stores/                ← Zustand stores (client state only)
  types/                 ← shared TypeScript interfaces and enums
  utils/                 ← pure utility functions (formatMoney, formatDate)
  constants/             ← route paths, enum mirrors, config values
  lib/                   ← third-party library config (queryClient, i18n)
  App.tsx
  main.tsx
```

**File naming:**

| Thing | Convention | Example |
| --- | --- | --- |
| Components | PascalCase | `OrderCard.tsx` |
| Hooks | camelCase with `use` prefix | `useOrderStatus.ts` |
| Services | camelCase | `orders.ts`, `auth.ts` |
| Types/interfaces | PascalCase | `Order.ts`, `types.ts` |
| Utility functions | camelCase | `formatMoney.ts` |
| Constants | camelCase file, UPPER\_SNAKE values | `routes.ts` → `ROUTES.ORDERS` |
| Stores | camelCase with `use` prefix | `useCartStore.ts` |
| Test files | Same name + `.test` | `OrderCard.test.tsx` |

**Co-location rule:** keep a component's styles, tests, and sub-components in the same folder:

```
components/Orders/
  OrderCard.tsx
  OrderCard.test.tsx
  OrderCard.module.css   ← only if not using Tailwind
  OrderStatusBadge.tsx   ← sub-component used only by OrderCard
  index.ts               ← re-exports public API of this folder
```

## 4. Component Rules

**Design rules**

- Functional components only — no class components
- One component = one responsibility — if it is doing two things, split it
- Keep components under 200 lines — if longer, extract sub-components
- No business logic in components — components render and handle events only
- No API calls in components — use a custom hook that calls the service layer
- No direct store reads in deeply nested components — pass via props or read in the page-level component

**Props**

- Always type props explicitly — never `any`, never untyped objects
- Destructure props in the function signature for clarity
- Use default parameter values, not `defaultProps`
- Required props have no default; optional props have a `?` and a sensible default
- Keep prop interfaces small — if a component takes 8+ props, it is doing too much

```tsx
interface OrderCardProps {
  order: Order;
  onCancel?: (id: string) => void;
  compact?: boolean;
}

function OrderCard({ order, onCancel, compact = false }: OrderCardProps) {
  // ...
}
```

**Composition patterns**

- **Container / Presentational** — page components fetch data via hooks; UI components receive data as props and render
- **Compound components** — for complex UI with shared internal state (Tabs, Accordion, Select)
- **Render props / children as function** — only when composition via props is genuinely more flexible
- Prefer composition over inheritance — always

**Separation of design from logic**

- JSX is the design layer — it reads values, never computes them
- Derived values belong in `useMemo` or a custom hook — not inline in JSX
- Bad: `<span>{(order.amount / 100).toFixed(2)}</span>`
- Good: `<MoneyDisplay amount={order.amount} />`
- Event handler logic longer than one line belongs in a named function — not inline in JSX

**Reusable components**

- Check `src/components/ui/` before building anything new
- Variants via props — never duplicate a component for a different color or size
- Every reusable component must work standalone — no hidden dependencies on global state
- Every reusable component needs a test

## 5. Hooks

**Rules of hooks — always:**

- Only call hooks at the top level — never inside loops, conditions, or nested functions
- Only call hooks from React function components or other custom hooks
- ESLint's `eslint-plugin-react-hooks` enforces this — keep it enabled and passing

**Custom hooks — when to create one:**

- The same stateful logic appears in two or more components → extract it
- A component has complex state logic that obscures what it renders → extract it
- A side effect + state combination is reusable → extract it
- A component file is getting long because of logic → extract the logic

**Custom hook rules:**

- Always prefix with `use` — `useOrderStatus`, `useDebounce`, `useIntersectionObserver`
- A hook returns what the component needs — values, handlers, and state flags
- A hook never returns JSX — that is a component
- Keep hooks focused — `useOrderStatus` handles order status only; not order status + customer data + payment info
- Document what the hook does and what it returns

```tsx
// Good — focused, typed, documented
function useOrderStatus(orderId: string) {
  const { data, isLoading, error } = useQuery({
    queryKey: ['order', orderId],
    queryFn: () => ordersApi.getStatus(orderId),
  });

  return {
    status: data?.status,
    isLoading,
    isError: !!error,
  };
}
```

**Built-in hook rules:**

- `useState` — for local UI state only (modal open/closed, tab selection, input value before submission)
- `useEffect` — for synchronizing with external systems only; never for deriving state from other state (use `useMemo`)
- `useEffect` with an empty dependency array is a code smell — almost always wrong; question every one
- `useMemo` — for expensive computations that should not re-run on every render
- `useCallback` — for functions passed as props to memoized child components
- `useRef` — for DOM references and values that should not trigger re-renders
- `useContext` — for values that are genuinely global (theme, auth user, locale); not for server data
- `useId` — for generating accessible IDs that are stable across server and client renders

## 6. State Management

The most common mistake in React is putting everything in one global store. Different types of state have different homes.

**The four types of state:**

| Type | What it is | Where it lives |
| --- | --- | --- |
| **Server state** | Data fetched from the API | TanStack Query (React Query) |
| **Global client state** | UI state shared across distant components (auth user, theme, cart) | Zustand store |
| **Local UI state** | State that only one component needs (modal open, tab selection) | `useState` in that component |
| **URL state** | Filters, search query, pagination page, selected item | URL params via React Router |

**Server state — TanStack Query:**

- Never `useEffect` + `useState` for API calls — use `useQuery` and `useMutation`
- Every query has a typed `queryKey` — define keys as constants, not inline strings
- Use `staleTime` to avoid redundant refetches on component mount
- Use `useMutation` for writes — it gives you `onSuccess`, `onError`, `onSettled` callbacks
- Invalidate related queries after a successful mutation — keep server state fresh
- `isPending`, `isError`, `isSuccess` flags for loading and error states — never manual boolean state

```tsx
const { data: orders, isPending, isError } = useQuery({
  queryKey: ['orders', { status, page }],
  queryFn: () => ordersApi.list({ status, page }),
  staleTime: 30_000,
});

const { mutate: cancelOrder } = useMutation({
  mutationFn: ordersApi.cancel,
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['orders'] }),
});
```

**Global client state — Zustand:**

- Only truly global, non-server data belongs here: auth user, theme, notification queue, cart
- Keep stores small and focused — one store per domain, not one giant store
- Never put server data in a Zustand store — that is what React Query is for
- Actions are inside the store — not in components

**URL state:**

- Filters, search query, sort order, pagination page, selected tab → URL params
- Why: shareable links, browser back/forward, no state loss on refresh
- Use React Router's `useSearchParams` — not manual `window.location` manipulation
- Serialize complex filter objects to URL-safe strings

## 7. API Layer

**The rule:** no component or hook calls `fetch` or `axios` directly. All HTTP calls go through `src/services/api/`.

**Setup — one configured instance:**

```ts
// src/services/index.ts
import axios from 'axios';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  withCredentials: true, // for httpOnly cookie auth
});

// Request interceptor — attach auth token if using Bearer
api.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Response interceptor — normalize errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) useAuthStore.getState().logout();
    return Promise.reject(normalizeError(error));
  }
);
```

**One file per resource:**

```ts
// src/services/api/orders.ts
export const ordersApi = {
  list: (params: OrderListParams): Promise<PaginatedResponse<Order>> =>
    api.get('/v1/orders', { params }).then(r => r.data),

  get: (id: string): Promise<Order> =>
    api.get(`/v1/orders/${id}`).then(r => r.data),

  create: (data: CreateOrderDTO): Promise<Order> =>
    api.post('/v1/orders', data).then(r => r.data),

  cancel: (id: string): Promise<void> =>
    api.post(`/v1/orders/${id}/cancel`).then(r => r.data),
};
```

**Rules:**

- Every API function is typed — typed input, typed return
- API functions return unwrapped data — not the full Axios response
- Error normalization happens in the interceptor — not in every call site
- Never hardcode API URLs in components or hooks — always via the service layer
- Never put API base URLs in component files — use `import.meta.env.VITE_*`
- All IDs passed to the API are ULIDs/UUIDs — never internal integer IDs

## 8. Forms

**Use React Hook Form + Zod for all non-trivial forms.**

```tsx
const schema = z.object({
  email: z.string().email('Invalid email'),
  amount: z.number().positive('Must be positive').int('Must be a whole number'),
});

type FormValues = z.infer<typeof schema>;

function PaymentForm() {
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
  });

  const { mutate } = useMutation({
    mutationFn: paymentsApi.create,
    onSuccess: () => navigate(ROUTES.PAYMENTS),
  });

  return (
    <form onSubmit={handleSubmit((data) => mutate(data))}>
      <input {...register('email')} />
      {errors.email && <span>{errors.email.message}</span>}
      <button type="submit" disabled={isSubmitting}>Pay</button>
    </form>
  );
}
```

**Form rules:**

- Schema defined with Zod — single source of truth for validation
- `isSubmitting` disables the submit button — prevents double-submission always
- Server validation errors mapped back to form fields — never only shown as a toast
- Every form input has an associated `<label>` — either visible or `aria-label`
- `required` attribute on required fields — plus `aria-required="true"`
- `autoComplete` attribute set correctly — helps password managers and reduces user friction
- Multi-step forms track step in URL state — progress survives a refresh
- File inputs handled separately — see `docs/patterns/file-uploads.md`
- Money inputs: accept display format from user (e.g. `10.00`), convert to minor units before submitting — never ask for minor units

## 9. Routing

**Setup — React Router v6+:**

```tsx
// src/constants/routes.ts — define all paths once
export const ROUTES = {
  HOME: '/',
  LOGIN: '/login',
  DASHBOARD: '/dashboard',
  ORDERS: '/dashboard/orders',
  ORDER: (id: string) => `/dashboard/orders/${id}`,
} as const;
```

```tsx
// src/App.tsx — all routes in one place
<Routes>
  <Route path={ROUTES.LOGIN} element={<LoginPage />} />
  <Route element={<AuthGuard />}>
    <Route path={ROUTES.DASHBOARD} element={<DashboardPage />} />
    <Route path={ROUTES.ORDERS} element={<OrdersPage />} />
    <Route path="/dashboard/orders/:id" element={<OrderDetailPage />} />
  </Route>
  <Route path="*" element={<NotFoundPage />} />
</Routes>
```

**Rules:**

- All route paths defined in `ROUTES` constants — never hardcode `/dashboard` in `<Link>` or `navigate()`
- Auth guard is a layout route wrapper — not per-page checks
- Every page is lazy loaded with `React.lazy()` + `<Suspense>` — reduces initial bundle
- Always handle the `*` catch-all — show a proper 404 page
- Route params are always the external identifier (ULID/UUID) — never the integer ID
- Use `useNavigate()` for programmatic navigation — never `window.location`
- Use `<Link>` for navigable links — never `<a href>` for internal routes
- `useParams()` returns strings — always validate and handle the case where a param is missing

**Auth guard pattern:**

```tsx
function AuthGuard() {
  const { user, isLoading } = useAuth();

  if (isLoading) return <PageSpinner />;
  if (!user) return <Navigate to={ROUTES.LOGIN} replace />;

  return <Outlet />;
}
```

## 10. Performance

**Memoization — use sparingly and correctly:**

- `React.memo()` — wrap a component only when its parent re-renders frequently and it is expensive to re-render
- `useMemo()` — for expensive calculations; not for every derived value
- `useCallback()` — for functions passed as props to memoized child components
- Premature memoization adds complexity without benefit — profile first, then memoize
- TanStack Query's caching eliminates most needs for manual data memoization

**Code splitting — always:**

```tsx
const OrdersPage = React.lazy(() => import('./pages/OrdersPage'));
const AdminPage  = React.lazy(() => import('./pages/AdminPage'));

// Wrap with Suspense at the router level
<Suspense fallback={<PageSpinner />}>
  <Routes>...</Routes>
</Suspense>
```

- Every page component is lazy-loaded — not imported directly at the top of `App.tsx`
- Large third-party libraries (chart libraries, rich text editors, PDF viewers) are dynamically imported at the point of use
- Set a bundle size budget in `vite.config.ts` and fail the build if exceeded

**List rendering:**

- Always provide stable `key` props on list items — never use array index as a key for reorderable lists
- Virtualize long lists (1000+ items) with `@tanstack/react-virtual` — never render all rows in the DOM
- Use `useDeferredValue` for filtering large lists — keeps the UI responsive while the filter runs

**Image optimization:**

- Always specify `width` and `height` on images to prevent layout shift
- Use `loading="lazy"` on images below the fold
- Serve images in modern formats (WebP, AVIF) via CDN transformation

**Bundle health:**

- Run `vite build --report` to inspect bundle composition
- Watch for accidental imports of entire libraries — use named imports
- `import { format } from 'date-fns'` not `import * as dateFns from 'date-fns'`

## 11. Security

**Token storage:**

- Auth tokens in httpOnly cookies — never `localStorage` or `sessionStorage`
- `withCredentials: true` on the Axios instance so cookies are sent with every request
- If using Bearer tokens (not cookies), store in memory only — a Zustand store, not `localStorage`
- On app load, validate the session server-side — never trust client-stored auth state alone

**XSS prevention:**

- Never use `dangerouslySetInnerHTML` without explicit sanitization
- If rich text content must be rendered as HTML, sanitize with DOMPurify first:

```tsx
import DOMPurify from 'dompurify';
<div dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(content) }} />
```

- Never trust data from URL params, `localStorage`, or API responses as safe to render directly as HTML
- React's JSX escapes output by default — `{userContent}` is safe; `dangerouslySetInnerHTML` is not

**API keys & secrets:**

- Never put API keys, secrets, or private tokens in frontend code — they are visible to anyone
- Only `VITE_*` prefixed variables are exposed to the browser — use them only for public config (base URL, public keys)
- Private keys (payment secret keys, SMS API keys) never touch the frontend — backend only

**Input handling:**

- Never `eval()` user input — ever
- Validate all user input client-side for UX; validate server-side for security
- URL params are user-controlled — always validate before using in API calls or rendering

**Dependency security:**

- Run `npm audit` before every deploy — `npm audit --fix` only after reviewing changes
- Never install packages from git URLs in production

## 12. Testing

**Stack:** Vitest + React Testing Library + Playwright

**What to test with React Testing Library:**

- Test what the user sees and does — not implementation details
- Query by role, label, text — not by class name or component internals
- `getByRole('button', { name: /cancel order/i })` not `getByTestId('cancel-btn')`
- Test user interactions: `userEvent.click()`, `userEvent.type()` — not `fireEvent`
- Assert what appears in the DOM after an interaction — not internal state

**Component test pattern:**

```tsx
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { OrderCard } from './OrderCard';

test('shows cancel button for pending orders', async () => {
  const user = userEvent.setup();
  const onCancel = vi.fn();

  render(<OrderCard order={pendingOrder} onCancel={onCancel} />);

  await user.click(screen.getByRole('button', { name: /cancel/i }));

  expect(onCancel).toHaveBeenCalledWith(pendingOrder.id);
});
```

**Mocking:**

- Mock the API service layer — not the network (`vi.mock('../services/api/orders')`)
- Mock TanStack Query with a wrapper or `QueryClientProvider` in tests
- Use `vi.fn()` for callbacks; `vi.spyOn()` for module functions
- Never mock React hooks directly — test the component that uses them

**Custom hook tests:**

- Use `renderHook()` from React Testing Library
- Wrap with required providers (QueryClientProvider, Router)
- Test the hook's return values and how they change after actions

**E2E with Playwright:**

- Test every critical user flow end-to-end
- Use Playwright's `page.getByRole()` and `page.getByLabel()` — same accessible queries as RTL
- Seed test data via API calls before each test — never assume DB state
- Run E2E against the staging environment — not localhost

**Coverage:**

- Every reusable component has at least one test
- Every custom hook has at least one test
- Every API service function is covered by integration or E2E tests
- Aim for meaningful coverage — not 100% line coverage with trivial tests

## 13. React-Specific Checklist

Run this alongside the universal checklist in `CLAUDE.md` Section 26.

**TypeScript**

- [ ] `"strict": true` in `tsconfig.json`?
- [ ] No `any` types without a comment explaining why?
- [ ] All props interfaces defined and typed?
- [ ] All API service functions typed — input and return?
- [ ] All custom hook return values typed?

**Components**

- [ ] Functional components only — no class components?
- [ ] No business logic or API calls inside components?
- [ ] Props destructured in function signature?
- [ ] No inline JSX logic — derived values in hooks or `useMemo`?
- [ ] `MoneyDisplay` (or equivalent) used for all money rendering — no inline `/ 100`?
- [ ] Existing `ui/` components checked before creating new ones?
- [ ] Components under 200 lines — extracted if longer?

**State**

- [ ] Server state in TanStack Query — no `useEffect` + `useState` for API calls?
- [ ] Global client state in Zustand — not server data?
- [ ] Filter/search/pagination state in URL params?
- [ ] Local-only state in `useState` in the component?
- [ ] No unnecessary `useMemo` / `useCallback` without profiling?

**API Layer**

- [ ] No `fetch` or `axios` calls in components or hooks — all via `src/services/api/`?
- [ ] All API functions typed with input and return types?
- [ ] Error normalization in the interceptor — not at each call site?
- [ ] No hardcoded API URLs — all via `import.meta.env.VITE_*`?

**Routing**

- [ ] All paths defined in `ROUTES` constants — no hardcoded strings?
- [ ] All pages lazy-loaded with `React.lazy()`?
- [ ] Auth guard wrapping all private routes?
- [ ] `*` catch-all not-found route present?

**Performance**

- [ ] Stable `key` props on all list items — no array index for reorderable lists?
- [ ] Long lists (1000+ items) virtualized?
- [ ] Bundle size budget set and passing?

**Security**

- [ ] Auth tokens in httpOnly cookies — not localStorage?
- [ ] No `dangerouslySetInnerHTML` without DOMPurify sanitization?
- [ ] No secrets or private keys in frontend code?
- [ ] `npm audit` passing?

**Testing**

- [ ] Every reusable component has at least one test?
- [ ] Tests use `getByRole`/`getByLabel` — not `getByTestId` or class names?
- [ ] API service layer mocked in component tests?
- [ ] Full Vitest suite green?
- [ ] Playwright E2E tests covering critical user flows?
