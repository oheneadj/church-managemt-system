# Pattern — State Management

> Read this file before designing the state architecture for any frontend feature.

## 1. When to Read This

Read this file before:

- Deciding where to put state in a new feature
- Choosing between a global store, local state, or a data-fetching library
- Building filters or search that should survive a page refresh
- Experiencing stale data, unnecessary refetches, or state synchronization bugs
- Choosing a state management library for a new project

## 2. The Four Types of State

The most common frontend architecture mistake is treating all state the same. Different state has different characteristics — it lives in different places and is managed with different tools.

| Type | What it is | Where it lives | Tool |
| --- | --- | --- | --- |
| **Server state** | Data that lives on the server and is fetched asynchronously | Data-fetching cache | TanStack Query, SWR |
| **Global client state** | UI state shared across distant components | Global store | Zustand, Pinia |
| **URL state** | State that should be shareable and survive a refresh | URL params | React Router, Vue Router |
| **Local UI state** | State only one component needs | Component | `useState`, `ref()` |
| **Form state** | Input values, validation errors, submission status | Form library | React Hook Form, VeeValidate |

**The rule: put state as close to where it is used as possible.** Only lift state up when two or more components genuinely need to share it.

**The most common mistake:** putting server data in a global store. Server data (orders, users, products) belongs in TanStack Query / SWR — not in Zustand or Pinia. A global store is for client-side UI state that has no server equivalent (theme, notification queue, open modal ID).

**Decision flow:**

1. Is this data from the server? → TanStack Query / SWR
2. Does only one component need this? → `useState` / `ref()`
3. Should this survive a page refresh or be shareable via URL? → URL params
4. Is this form input data? → React Hook Form / VeeValidate
5. Is this truly global UI state shared across distant components? → Zustand / Pinia store

## 3. Server State

**Use TanStack Query (React) or TanStack Query for Vue — not `useEffect` + `useState`.**

Server state is fundamentally different from client state:

- It is asynchronous — you are always waiting for it
- It can be stale — the server may have a newer version
- It can be shared — multiple components may need the same data
- It needs caching — you should not refetch on every render

Managing all of this with `useEffect` + `useState` requires dozens of lines of boilerplate and still handles edge cases poorly. TanStack Query handles all of it with a few lines.

**Core patterns:**

```tsx
// Reading data — useQuery
const { data: orders, isPending, isError } = useQuery({
  queryKey: ['orders', { status, page }],   // cache key — includes all params that affect the result
  queryFn:  () => ordersApi.list({ status, page }),
  staleTime: 30_000,                        // 30s before data is considered stale
});

// Writing data — useMutation
const { mutate: cancelOrder, isPending: isCancelling } = useMutation({
  mutationFn: (orderId: string) => ordersApi.cancel(orderId),
  onSuccess:  () => queryClient.invalidateQueries({ queryKey: ['orders'] }),
  onError:    (err) => toast.error(err.message),
});
```

**Query key rules:**

- Query keys are the cache identifier — same key = same cached data
- Include every variable that affects the result: `['orders', { status, page, search }]`
- Hierarchical keys allow smart invalidation: invalidating `['orders']` invalidates all orders queries
- Use constants for key strings — never raw strings scattered across the codebase

**Invalidation patterns:**

```ts
// Invalidate all orders queries after any mutation
queryClient.invalidateQueries({ queryKey: ['orders'] });

// Invalidate a specific order after updating it
queryClient.invalidateQueries({ queryKey: ['orders', orderId] });

// Optimistically update the cache immediately, sync later
queryClient.setQueryData(['orders', orderId], updatedOrder);
```

**Prefetching for perceived performance:**

```ts
// Prefetch the next page while the user reads the current one
queryClient.prefetchQuery({
  queryKey: ['orders', { page: currentPage + 1 }],
  queryFn:  () => ordersApi.list({ page: currentPage + 1 }),
});
```

## 4. Global Client State

**Use Zustand (React) or Pinia (Vue) for truly global UI state — not for server data.**

Global client state is UI state that:

- Is needed by many components across the component tree
- Cannot be solved by lifting state up (too many levels)
- Has no server equivalent — it exists only in the client
- Does not need to be cached, refetched, or invalidated

**What belongs in a global store:**

- The authenticated user's session data (read from the server once on load)
- Current theme (light/dark)
- In-app notification queue
- Shopping cart (if not persisted to the server)
- A stack of open modals or toasts
- Sidebar open/collapsed state

**What does NOT belong in a global store:**

- Data from API calls (orders, products, users) → TanStack Query
- Form state → React Hook Form / VeeValidate
- Filter/search values → URL params
- State that only one component needs → local state

**Zustand (React) — keep stores small and focused:**

```ts
// One store per concern — not one giant store
export const useAuthStore = create<AuthStore>((set) => ({
  user: null,
  setUser:  (user)  => set({ user }),
  logout:   ()      => set({ user: null }),
}));

export const useNotificationStore = create<NotificationStore>((set, get) => ({
  notifications: [],
  add:    (n) => set({ notifications: [...get().notifications, n] }),
  remove: (id) => set({ notifications: get().notifications.filter(n => n.id !== id) }),
}));
```

**Pinia (Vue) — setup store syntax:**

```ts
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);
  const isAuthenticated = computed(() => user.value !== null);
  const setUser  = (u: User) => { user.value = u; };
  const logout   = ()        => { user.value = null; };
  return { user, isAuthenticated, setUser, logout };
});
```

**The smell test:** if you are putting an object that came from `await api.getOrders()` into a Zustand or Pinia store, stop — that belongs in TanStack Query.

## 5. URL State

**Any state that should survive a page refresh or be shareable via a URL belongs in URL params.**

This is one of the most underused patterns in frontend development. Developers put filters, search queries, and pagination in local state — then users lose their context on refresh, cannot share a filtered view with a colleague, and the browser's back button does not restore their previous search.

**What belongs in URL params:**

- Search query (`?search=toyota`)
- Active filters (`?make=toyota&year=2022&status=available`)
- Current page number (`?page=3`)
- Sort column and direction (`?sort=price&dir=asc`)
- Selected tab (`?tab=specifications`)
- Selected item ID in a master/detail layout (`?orderId=ord_01J8K`)

**What does NOT belong in URL params:**

- Temporary UI state (modal open/closed, dropdown expanded)
- Sensitive data (tokens, passwords — never in a URL)
- Large data objects — only identifiers

**React Router implementation:**

```tsx
const [searchParams, setSearchParams] = useSearchParams();

const filters = {
  search: searchParams.get('search') ?? '',
  make:   searchParams.get('make') ?? '',
  page:   Number(searchParams.get('page') ?? 1),
};

const updateFilter = (key: string, value: string) => {
  setSearchParams(prev => {
    const next = new URLSearchParams(prev);
    if (value) next.set(key, value);
    else next.delete(key);  // remove empty params
    next.set('page', '1');  // reset to page 1 on filter change
    return next;
  });
};

// These filters feed directly into TanStack Query
const { data } = useQuery({
  queryKey: ['cars', filters],
  queryFn:  () => carsApi.list(filters),
});
```

**Vue Router implementation:**

```ts
const route  = useRoute();
const router = useRouter();

const filters = computed(() => ({
  search: route.query.search as string ?? '',
  page:   Number(route.query.page ?? 1),
}));

const updateFilter = (key: string, value: string) => {
  router.push({ query: { ...route.query, [key]: value || undefined, page: 1 } });
};
```

**Benefits:**

- Browser back/forward works correctly — each filter change is a history entry
- Shareable URLs — send a filtered view to a colleague
- Refresh-safe — state survives a page reload
- Bookmarkable — users can save their preferred view

## 6. Form State & Optimistic UI

**Form state is its own category** — it has unique characteristics: real-time validation, field-level errors, submission status, dirty state tracking. Use a form library rather than manual `useState` for any non-trivial form.

**React Hook Form + Zod (React):**

```tsx
const schema = z.object({
  email:    z.string().email(),
  password: z.string().min(8),
});

const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm({
  resolver: zodResolver(schema),
});

const onSubmit = handleSubmit(async (data) => {
  await authApi.login(data); // throws on error — caught by form library
});
```

**VeeValidate + Zod (Vue):**

```vue
<script setup lang="ts">
const { handleSubmit, errors, isSubmitting } = useForm({
  validationSchema: toTypedSchema(z.object({ email: z.string().email() })),
});
</script>
```

**Optimistic UI — update the UI before the server responds:**

Optimistic UI makes the app feel instant. The user takes an action, the UI updates immediately, and the server is updated in the background. If the server fails, the UI rolls back.

```tsx
const { mutate: likePost } = useMutation({
  mutationFn: (postId: string) => postsApi.like(postId),

  // Optimistically update the cache before the server responds
  onMutate: async (postId) => {
    await queryClient.cancelQueries({ queryKey: ['post', postId] });
    const previousPost = queryClient.getQueryData(['post', postId]);

    queryClient.setQueryData(['post', postId], (old: Post) => ({
      ...old,
      likeCount: old.likeCount + 1,
      likedByMe: true,
    }));

    return { previousPost }; // context for onError rollback
  },

  // If the server fails, roll back to the previous state
  onError: (err, postId, context) => {
    queryClient.setQueryData(['post', postId], context?.previousPost);
  },

  // Always refetch after success or error
  onSettled: (data, err, postId) => {
    queryClient.invalidateQueries({ queryKey: ['post', postId] });
  },
});
```

**When to use optimistic UI:**

- Like/unlike, follow/unfollow — high frequency, low risk if it briefly shows wrong state
- Reordering items in a list — drag-and-drop should feel instant
- Toggling a boolean setting — the user expects immediate feedback

**When NOT to use optimistic UI:**

- Payment actions — never show "Payment successful" before the server confirms
- Destructive actions — never show "Deleted" before the server confirms
- Actions with complex side effects — if rollback would confuse the user

## 7. State Management Checklist

**State placement:**

- [ ] Server data in TanStack Query — not in Zustand/Pinia?
- [ ] Only truly global UI state in Zustand/Pinia — not every piece of shared data?
- [ ] Filters, search, and pagination in URL params — not in local state?
- [ ] Form state in a form library — not manual `useState` / `ref()` for complex forms?
- [ ] State placed as close to where it is used as possible?

**Server state (TanStack Query):**

- [ ] Query keys include all variables that affect the result?
- [ ] Query key constants defined — not raw strings scattered across the codebase?
- [ ] `staleTime` set appropriately — not zero (refetch on every mount)?
- [ ] Mutations invalidate related queries on success?
- [ ] `isPending`, `isError`, `isSuccess` flags used for loading/error states — not manual boolean state?

**Global store:**

- [ ] One store per concern — not one giant store with everything?
- [ ] No server data in the store — only pure client-side UI state?
- [ ] Store actions are the only way to mutate store state?

**URL state:**

- [ ] Filter/search/pagination state in URL params?
- [ ] Empty params removed from URL — not `?search=&page=1`?
- [ ] Page resets to 1 when filters change?
- [ ] Browser back/forward works correctly for filter changes?

**Form state:**

- [ ] Form library used for any form with more than 2 fields?
- [ ] Zod schema as single source of truth for validation?
- [ ] Submit button disabled while submitting — no double-submit possible?
- [ ] Server validation errors mapped back to individual fields?
- [ ] Optimistic UI only used for low-risk reversible actions — never for payments or destructive actions?
