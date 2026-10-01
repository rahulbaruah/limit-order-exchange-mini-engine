# PRD: Limit Order Exchange Mini Engine

## 1. Introduction

Build a small authenticated exchange application where users can place BTC or ETH limit buy and sell orders against other users. The Laravel API manages USD and asset balances, holds funds while orders are open, matches crossing orders using full fills, charges a 1.5% fee on matched USD value, and broadcasts successful matches in real time. A Vue.js frontend provides order entry, wallet visibility, order history, and a selected-symbol order book.

## 2. Goals

- Let authenticated users place and cancel BTC and ETH limit orders with correct balance reservations.
- Match a new order with the first valid resting counter-order when prices cross, filling the orders completely or not at all.
- Settle each match at a deterministic price, update both users' balances and order statuses, and charge a 1.5% fee on matched USD value.
- Notify both matched users immediately through private real-time channels and update their frontend state without a page refresh.
- Provide a compact interface for order entry, wallet balances, order history, and the selected symbol's open order book.

## 3. Users and Stories

### US-001: View my wallet

**Description:** As an authenticated user, I want to see my USD balance and BTC/ETH balances so that I know what I can trade.

**Acceptance Criteria:**

- [ ] `GET /api/profile` returns the authenticated user's USD balance and asset balances.
- [ ] A user cannot read another user's wallet through this endpoint.
- [ ] The frontend displays the balances and refreshes them after a successful match or cancellation.

### US-002: Place a limit buy order

**Description:** As an authenticated user, I want to place a buy limit order so that I can acquire an asset at my chosen maximum price.

**Acceptance Criteria:**

- [ ] The order form accepts a supported symbol, buy/sell side, positive price, and positive amount.
- [ ] For a buy, required USD is calculated as `price × amount` plus the 1.5% fee at the limit price; the order is rejected if available USD is insufficient.
- [ ] On acceptance, the notional and fee are recorded in `locked_balance` and the order is created with `open` status.
- [ ] Invalid symbols, sides, non-positive values, and insufficient balances return a useful validation error and do not create or reserve an order.

### US-003: Place a limit sell order

**Description:** As an authenticated user, I want to place a sell limit order so that I can sell assets at my chosen minimum price.

**Acceptance Criteria:**

- [ ] A sell order is rejected if the user's available asset amount is insufficient.
- [ ] On acceptance, the amount is moved from available assets into `locked_amount` and the order is created with `open` status.
- [ ] Invalid input or insufficient assets do not create an order or change balances.

### US-004: View the order book and my orders

**Description:** As an authenticated user, I want to see open orders for a symbol and my order history so that I can understand the market and track my orders.

**Acceptance Criteria:**

- [ ] `GET /api/orders?symbol=BTC` returns open buy and sell orders for that symbol.
- [ ] The UI provides a selected-symbol order book and the user's past orders, including open, filled, and cancelled orders.
- [ ] Changing the selected symbol refreshes the displayed order book.

### US-005: Cancel an open order

**Description:** As an authenticated user, I want to cancel my open order so that reserved funds or assets become available again.

**Acceptance Criteria:**

- [ ] `POST /api/orders/{id}/cancel` cancels an open order owned by the authenticated user.
- [ ] Cancelling a buy releases its reserved USD; cancelling a sell releases its locked asset amount.
- [ ] A filled or already-cancelled order cannot be cancelled again, and another user's order cannot be cancelled by the caller.
- [ ] A successful cancellation marks the order `cancelled` and updates the wallet and order list.

### US-006: Match crossing orders

**Description:** As a trader, I want crossing orders to execute automatically so that I do not need to arrange a trade manually.

**Acceptance Criteria:**

- [ ] A new BUY matches the first eligible open SELL for the same symbol where `sell.price <= buy.price`.
- [ ] A new SELL matches the first eligible open BUY for the same symbol where `buy.price >= sell.price`.
- [ ] Matching is full-fill-only: if the selected counter-order cannot be filled for the new order's entire amount, the engine does not partially fill either order.
- [ ] On a match, both orders become `filled`, both users' balances are settled, and the `trades` table may record the execution.
- [ ] The matching operation is atomic: an error cannot leave only one order, one wallet, or one reservation updated.
- [ ] The matching engine can be triggered internally or by a job after order creation.

### US-007: Charge and account for the trading fee

**Description:** As the exchange operator, I want every match to incur the required fee so that trading fees are accounted for consistently.

**Acceptance Criteria:**

- [ ] The fee equals 1.5% of the matched USD value (`execution price × matched amount`).
- [ ] The fee is charged once per match and the settlement leaves buyer and seller balances consistent with the selected fee allocation policy.
- [ ] Fee calculations use decimal-safe arithmetic and documented rounding precision.
- [ ] For example, 0.01 BTC at 95,000 USD/BTC has 950 USD matched value and a 14.25 USD fee.

### US-008: Receive live match updates

**Description:** As a matched trader, I want to receive a private match notification so that my wallet and order status update without refreshing.

**Acceptance Criteria:**

- [ ] Every successful match broadcasts an `OrderMatched` event via Pusher/Laravel Broadcasting to both parties' private channels.
- [ ] The event identifies the match and contains the order/trade details needed by the UI, without exposing unrelated users' private data.
- [ ] The frontend subscribes to the authenticated user's private channel and patches trade history, order status, USD balance, and asset balance on receipt.
- [ ] No match event is sent for a failed or rolled-back match.

### US-009: Use the exchange screens

**Description:** As an authenticated user, I want a simple order form and wallet/order overview so that I can trade and monitor my activity in one place.

**Acceptance Criteria:**

- [ ] The application includes a limit-order form with symbol (BTC/ETH), side (buy/sell), price, amount, and a Place Order action.
- [ ] The overview includes USD and asset balances, all of the user's orders, and the open order book for the selected symbol.
- [ ] The UI handles validation errors, successful submissions, cancellations, and empty order-book/history states.
- [ ] Match updates appear without a manual page refresh.

## 4. Functional Requirements

### Data and accounts

- **FR-1:** Persist users with the standard Laravel user fields and a USD `balance` represented with decimal-safe storage.
- **FR-2:** Persist per-user asset balances with at least `user_id`, `symbol`, `amount`, and `locked_amount`. Support BTC and ETH initially.
- **FR-3:** Persist orders with at least `user_id`, `symbol`, `side` (`buy` or `sell`), `price`, `amount`, `status` (`open`, `filled`, or `cancelled`), and timestamps.
- **FR-4:** A `trades` table for executed matches should be included if needed to show trade history or support auditability.
- **FR-5:** Store quantities and monetary values with sufficient precision for supported assets. Do not use binary floating-point arithmetic for balance, fee, or order calculations.

### API and order lifecycle

- **FR-6:** Require authentication for wallet, order, and cancellation endpoints; users may only access or mutate their own wallet and orders.
- **FR-7:** `GET /api/profile` returns the current authenticated user's USD and asset balances.
- **FR-8:** `GET /api/orders?symbol={symbol}` returns open buy and sell orders for the requested supported symbol for the order book.
- **FR-9:** `POST /api/orders` validates and creates a limit order, reserves the corresponding USD or asset amount, then invokes matching.
- **FR-10:** `POST /api/orders/{id}/cancel` cancels the caller's open order and releases its remaining reservation.
- **FR-11:** A buy order reserves `price × amount` USD plus the 1.5% fee calculated at its limit price while open. The available USD balance is reduced by the full reservation, which is recorded in `locked_balance`; cancellation releases the full reservation, while settlement consumes the executed notional and fee and refunds unused price improvement or fee overcharge. A sell order moves its amount into the asset's locked amount while open.
- **FR-12:** Match only orders for the same symbol, with opposite sides and crossing limit prices. Select the first valid counter-order according to a consistent and documented ordering rule.
- **FR-13:** Matching is full-fill-only. The engine must not partially fill orders; after a successful match both matched orders are marked filled.
- **FR-14:** Settle balances and release unused price improvement/reservations as appropriate when the execution price is below a buyer's limit price.
- **FR-15:** Apply a 1.5% fee to matched USD value and record enough information to explain the fee and resulting net amounts.
- **FR-16:** Execute reservation, match selection, order status changes, trade creation, balance settlement, and fee accounting atomically and safely against concurrent order submissions/cancellations.
- **FR-17:** Return clear validation and conflict responses for invalid orders, insufficient balances, unavailable/non-owned orders, and orders that are no longer open.

### Real-time and frontend

- **FR-18:** Broadcast `OrderMatched` after a committed match through Laravel Broadcasting/Pusher to private channels for both involved users.
- **FR-19:** Provide authenticated channel authorization so only the channel owner can subscribe.
- **FR-20:** Build the frontend in Vue.js using the Composition API and Tailwind CSS. Include only the two core screens (Limit Order Form, Orders & Wallet Overview), plus authentication flows needed to access them.
- **FR-21:** Update displayed wallet balances, order status/history, and trade information immediately when the authenticated user's match event arrives.
- **FR-22:** Provide a usable order book for the selected symbol, including open buy and sell orders.

## 5. Non-Goals

- Partial fills, order splitting, or matching one incoming order against multiple resting orders.
- Market orders, stop orders, margin, leverage, short selling, or derivatives.
- Deposits, withdrawals, payment processing, or a production custody system.
- A public trading API, admin console, or advanced charting and analytics.
- More symbols than BTC and ETH in the initial scope.

## 6. Design and UX Considerations

- Use two primary views: **Limit Order Form** and **Orders & Wallet Overview**.
- The form has inputs for symbol, side, price, and amount, with a Place Order action.
- The overview presents USD and asset balances, the user's order history, and the selected symbol's open buy/sell book.
- Make reservation behavior visible in labels or helper text so users understand why a balance is unavailable while an order is open.
- Show clear feedback for rejected orders, cancellations, successful submissions, and live matches.

## 7. Technical Considerations

- **Backend:** Laravel API using the current stable release appropriate at implementation time.
- **Frontend:** Vue.js using the Composition API, with Tailwind CSS at its current stable release.
- **Database:** MySQL or PostgreSQL.
- **Real-time:** Pusher through Laravel Broadcasting; use private per-user channels.
- Keep matching and settlement in a transaction and use appropriate row locking or equivalent concurrency control to prevent double spending and duplicate fills.
- Use fixed-precision database decimal columns and decimal-safe calculations. Define currency and asset scale/rounding rules before implementation.
- Make matching order deterministic (for example, oldest eligible order first) and document the chosen tie-breaker.
- Do not rely on the frontend to enforce balance, ownership, order status, or fee rules.

## 8. Success Metrics

- Valid funded orders are accepted and reserve exactly the required USD or asset amount.
- Invalid or underfunded orders leave balances and order records unchanged.
- Every eligible match fills both orders exactly once, settles both users, and records the correct 1.5% fee.
- No partial fills occur.
- Both parties receive a private match update and see their wallet/order state update without refreshing.
- Users cannot read another user's profile or cancel another user's order.

## 9. Optional Enhancements

- Filter the user's order history by symbol, side, and status.
- Toast notifications for submission, cancellation, and match events.
- A volume or order-value calculation preview before order submission.
