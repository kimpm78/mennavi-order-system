# Mennavi Order System Project Rules

Source definition:
`/Users/kimsoosang/Desktop/work/02_プロジェクト/2026_project/mennvai_order_System/01.定義書/mennvai_order_sysyem_定義書.xlsx`

## Product Scope

- System name: Mennavi Order System.
- Stack: Laravel, Vue.js, PostgreSQL.
- Architecture: API-connected Laravel backend and Vue frontend.
- Purpose: Let customers browse menu items, add them to cart, confirm orders, and create order records.
- Target users:
  - Customer users use product browsing, cart, order confirmation, order completion, and order history.
  - Admin users manage products, categories, orders, and order statuses.

## User-Side Feature Priority

High priority:
- Product list
- Product category filtering
- Product detail
- Quantity selection
- Add to cart
- Order confirmation
- Order creation
- Order completion page with order number

Medium priority:
- Cart review
- Cart quantity update
- Cart item deletion
- Order history for logged-in users only

## Admin-Side Feature Priority

High priority:
- Admin login
- Product list management
- Product creation
- Product editing
- Order list management
- Order detail
- Order status update

Medium priority:
- Product deletion or hidden status
- Category management

## Non-Functional Rules

- Mobile-first usability matters for the customer flow.
- Keep Laravel responsibilities separated by Controller, Model, Request, and Service when behavior grows.
- Maintain data consistency between orders and order_items.
- Save order-time product names and prices in order_items.
- Protect admin screens with authenticated admin-only access.
- Keep the initial implementation simple, but leave room for payment, order history, and coupon features.

## Database Rules

Use PostgreSQL as the main development database.

Core tables from the definition:
- users
- categories
- products
- carts
- cart_items
- orders
- order_items

Users:
- `email` is the login ID and must be unique.
- `password` must be hashed before storage.
- `role` must distinguish `user` and `admin`.
- `phone`, `postal_code`, and `address` are optional.
- `deleted_at` is allowed for logical deletes.
- Do not make `password` unique, even though the spreadsheet notes it; password uniqueness is not useful or appropriate.

Categories:
- `display_order` controls display order.
- `is_active` controls visibility.

Products:
- Products belong to categories.
- `status` should support `active`, `sold_out`, and `hidden`.
- `price` is an integer tax-included amount.

Orders:
- `order_number` is unique and shown to users.
- `user_id` can be nullable for guest orders.
- `total_amount` must match order_items subtotals.
- `order_status` should support received, cooking, completed, and canceled.

Order items:
- Store product name and unit price at order time.
- `subtotal` is `unit_price * quantity`.

Carts:
- Support both logged-in users and guest sessions.
- cart_items must belong to carts and products.

## Current Implementation Direction

- Use Docker Compose for local development because Laravel, Vue, and PostgreSQL need stable versions and networking.
- Laravel API base URL for the Vue app: `http://127.0.0.1:8000/api`.
- Vue dev URL: `http://127.0.0.1:5173`.
- PostgreSQL host port: `5433`, container port: `5432`.
