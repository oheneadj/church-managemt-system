# Pattern — Real-Time Features

> Read this file before building WebSocket connections, live updates, notifications, or any feature that pushes data from server to client.

## 1. When to Read This

Read this file before:

- Building live order tracking or shipment updates
- Implementing a live notification feed or activity stream
- Adding real-time collaboration features (shared editing, comments)
- Building a dashboard with live-updating metrics
- Implementing typing indicators, presence, or "user is online" status
- Any feature where the server needs to push data to the client without the client polling

## 2. WebSockets vs SSE vs Long Polling

Choose the right transport for your use case — they are not interchangeable.

**WebSockets**

- Full-duplex — client and server can send messages to each other at any time
- Single persistent TCP connection — efficient for high-frequency bidirectional communication
- Best for: chat, collaborative editing, multiplayer, live cursors, anything where the client also sends data in real time
- Works over `ws://` (insecure) and `wss://` (secure, required in production)
- Requires a WebSocket-aware proxy (nginx, Cloudflare) — HTTP/1.1 upgrade handshake
- Not supported in some corporate firewalls — fall back to SSE or long polling

**Server-Sent Events (SSE)**

- One-directional — server pushes to client only; client sends via regular HTTP requests
- Standard HTTP/1.1 — works through all proxies and firewalls without special configuration
- Automatic reconnection built into the browser EventSource API
- Best for: live notifications, order status updates, dashboards, activity feeds — any server-to-client push where the client does not need to send data in real time
- Simpler to implement than WebSockets — no connection upgrade, no binary framing
- Limited to \~6 concurrent connections per domain in HTTP/1.1 (not an issue with HTTP/2)

**Long Polling**

- Client sends a request; server holds it open until data is available or a timeout occurs
- Client immediately sends another request after each response
- Highest latency and resource overhead of the three
- Best for: legacy environments, maximum compatibility, when WebSockets and SSE are blocked
- Avoid for new projects unless compatibility requires it

**Decision table:**

| Need | Use |
| --- | --- |
| Server pushes updates, client sends via regular HTTP | SSE (simpler, preferred) |
| Bidirectional real-time messaging | WebSockets |
| Maximum firewall compatibility | SSE or Long Polling |
| Maximum performance, low latency | WebSockets |
| Simple notification feed | SSE |
| Chat application | WebSockets |

## 3. Broadcasting Architecture

**The broadcast flow:**

```
Server action occurs (order status changes)
  → Action fires a domain event (OrderStatusUpdated)
    → Event broadcast to a named channel (orders.{orderId})
      → WebSocket/SSE server (Soketi, Pusher, Ably, Laravel Reverb)
        → Delivers to all subscribed clients
          → Frontend updates UI
```

**Channels — the unit of subscription:**

| Channel type | Example | Who can subscribe |
| --- | --- | --- |
| Public | `news`, `prices` | Anyone — no auth required |
| Private | `orders.{id}`, `user.{id}` | Authenticated user must be authorized |
| Presence | `room.{id}` | Authorized users + tracks who is subscribed |

**Channel naming conventions:**

- Use dot notation: `orders.01J8K`, `user.01J8K.notifications`
- Include the resource ULID/UUID — never the integer ID
- Keep names predictable — the frontend should be able to construct the channel name from data it already has
- Tenant-scope channels for multi-tenant apps: `tenant.{tenantId}.orders.{orderId}`

**Broadcasting providers:**

| Provider | Self-hosted | Best for |
| --- | --- | --- |
| **Laravel Reverb** | Yes (PHP) | Laravel projects — first-party, free |
| **Soketi** | Yes (Node) | Self-hosted WebSocket server, any stack |
| **Pusher** | No (SaaS) | Managed, generous free tier, battle-tested |
| **Ably** | No (SaaS) | High-scale, global edge network |
| **Cloudflare Durable Objects** | No (edge) | Cloudflare Workers stack |

**Event payload design:**

- Include only what the client needs to update the UI — not the full model
- Include a `type` field so clients can route to the right handler
- Never include sensitive data in broadcast payloads — the channel authorization protects subscription, not payload content
- Keep payloads small — large payloads degrade WebSocket performance

## 4. Scaling Real-Time Connections

**The scaling problem:** WebSocket connections are stateful and persistent — each connection lives on a specific server process. When you scale to multiple servers, a client connected to Server A cannot receive a message broadcast from Server B.

**Solution: Redis Pub/Sub as the message bus:**

```
Server A broadcasts OrderStatusUpdated
  → Publishes to Redis channel orders.{id}
    → Redis delivers to all subscribed servers (A, B, C)
      → Each server delivers to its connected clients
```

- Every WebSocket server subscribes to the relevant Redis channels
- When any server broadcasts an event, Redis fans it out to all servers
- Every client receives the event regardless of which server they are connected to
- Required for any multi-server / horizontally scaled deployment

**Sticky sessions (alternative, less preferred):**

- Route all connections from the same client to the same server (based on cookie or IP)
- Simpler — no Redis needed
- Fragile — if the server restarts, all connections are lost
- Does not scale cleanly — load imbalance as some servers accumulate more connections
- Only acceptable for small single-server deployments or development

**Connection limits:**

- Each WebSocket connection holds memory and a file descriptor on the server
- Plan for connection limits: 10,000 concurrent connections requires \~1 GB RAM on a dedicated WebSocket server
- Use a dedicated WebSocket server process (Reverb, Soketi) separate from your HTTP server — do not share the connection pool
- Set maximum connection limits and backpressure handling — reject new connections gracefully when at capacity

**Horizontal scaling with Reverb / Soketi:**

- Both support Redis pub/sub for multi-node deployments out of the box
- Configure `BROADCAST_CONNECTION=redis` and point at your Redis instance
- Run multiple WebSocket server instances behind a load balancer with sticky sessions enabled at the load balancer level

## 5. Presence Channels

Presence channels extend private channels with membership tracking — you know exactly who is subscribed to a channel at any moment.

**Use cases:**

- "3 people are viewing this document"
- Typing indicators in chat ("Alice is typing...")
- "Online now" badges on user profiles
- Participant lists in video calls or collaborative sessions

**How presence works:**

```
User subscribes to presence-room.{id}
  → Server authenticates and returns user data
    → WebSocket server adds user to the channel member list
      → Broadcasts member_added event to all channel subscribers
        → All clients update their participant list
User disconnects
  → WebSocket server broadcasts member_removed
    → All clients update their participant list
```

**What to include in presence member data:**

- The minimum needed to display in the UI — name, avatar URL, ULID
- Never include sensitive data — presence data is visible to all channel subscribers
- Keep the payload small — it is broadcast to every member on every join/leave

**Typing indicators:**

```ts
// Client sends typing event via regular HTTP (not WebSocket)
await api.post(`/channels/room.${roomId}/typing`);

// Server broadcasts to channel with a short TTL
Broadcast::on(`presence-room.${roomId}`)
  ->event(new UserTyping($user))
  ->toOthers(); // exclude the sender

// Client auto-clears the indicator after 3 seconds of no updates
```

**Important:** presence channels are expensive — each join/leave triggers a broadcast to all members. Do not use presence channels for rooms with thousands of members. Use a separate lightweight "online count" mechanism instead.

## 6. Security

**Channel authorization — the most critical security rule:** Every private and presence channel subscription must be authorized server-side before the client is allowed to subscribe. Without this, any authenticated user can subscribe to any private channel — including other users' order updates, messages, and data.

```php
// Laravel Channels authorization
Broadcast::channel('orders.{orderId}', function (User $user, string $orderId) {
    $order = Order::where('ulid', $orderId)->first();
    // Only allow if this order belongs to the authenticated user
    return $order && $order->user_id === $user->id;
});
```

**Authorization rules:**

- Every private channel must have an explicit authorization callback
- The authorization callback must verify the authenticated user has permission to subscribe
- Never return `true` from an authorization callback without checking ownership or role
- Test that User A cannot subscribe to User B's channels
- For multi-tenant apps: verify tenant scope in channel authorization

**Authentication token expiry:**

- WebSocket connections authenticate with a token at connection time
- Long-lived connections may outlive the token's expiry
- Implement token refresh for long-lived connections — or disconnect and reconnect on token expiry
- Handle the disconnect/reconnect gracefully on the frontend — show a reconnecting state, not a broken UI

**Rate limiting WebSocket connections:**

- Limit connection attempts per IP to prevent DDoS
- Limit channel subscription attempts per connection
- Limit the rate of client-sent messages per connection
- Reject connections from known bad actors before the WebSocket upgrade

**Message validation:**

- Validate any data sent by the client over a WebSocket connection — treat it as untrusted input, same as HTTP request body
- Never broadcast a client message to other users without sanitizing it first
- Size-limit incoming messages — prevent memory exhaustion from oversized payloads

## 7. Real-Time Checklist

**Transport choice:**

- [ ] SSE chosen for server-to-client push with no client-to-server real-time data?
- [ ] WebSockets chosen for bidirectional real-time communication?
- [ ] Long polling avoided unless compatibility requires it?

**Architecture:**

- [ ] Broadcasting via a dedicated WebSocket server — not the HTTP server process?
- [ ] Redis pub/sub configured for multi-server deployments?
- [ ] Channel names use ULIDs/UUIDs — not integer IDs?
- [ ] Event payloads contain only display data — not full model dumps?

**Security:**

- [ ] Every private channel has an explicit authorization callback?
- [ ] Authorization callback verifies ownership — not just authentication?
- [ ] Multi-tenant channels verify tenant scope in authorization?
- [ ] User A cannot subscribe to User B's private channels (tested)?
- [ ] Token expiry handled — connections refresh or reconnect on expiry?
- [ ] Rate limiting on connection attempts and client-sent messages?
- [ ] Client-sent messages validated server-side before broadcasting?

**Frontend:**

- [ ] Reconnection handled gracefully — exponential backoff, reconnecting state shown?
- [ ] UI updates optimistically or shows a loading state during reconnect?
- [ ] Old events replayed or fresh data fetched after reconnection?
- [ ] Connection cleaned up on component unmount (unsubscribe, close connection)?

**Scaling:**

- [ ] Connection count estimated — WebSocket server sized accordingly?
- [ ] Redis pub/sub tested with multiple server instances?
- [ ] Presence channels not used for rooms with thousands of members?
