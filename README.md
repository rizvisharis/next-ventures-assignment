# **Order Processing & Refund Management System**

This project is a scalable **Laravel application** that manages order processing, notifications, and refunds while maintaining **real-time KPIs and customer leaderboards** using Redis.

It uses **Laravel Horizon** and **Supervisor** for background job management and **queues** to handle large datasets asynchronously.

---

## **Features**

### **Task 1 – CSV Import & Order Workflow**

* Import large order CSVs using `php artisan orders:import file.csv`.
* Asynchronous processing to avoid timeouts.
* **Workflow:**

  1. Reserve Stock
  2. Simulate Payment
  3. Finalize or Rollback
* Update **real-time KPIs** and **leaderboards** using Redis.

### **Task 2 – Order Notifications**

* Send notifications (email or log) for success or failure.
* Store notifications in a dedicated database table (`order_notifications`).
* Notifications are queued to avoid blocking order processing.

### **Task 3 – Refund Handling & Analytics Update**

* Handle **partial or full refunds**.
* Refunds are processed asynchronously via queued jobs.
* **Idempotency** implemented to prevent duplicate refunds.
* Real-time updates to KPIs and leaderboards when refunds are processed.

---

## **Tech Stack**

| Component         | Technology                     |
| ----------------- | ------------------------------ |
| Backend Framework | Laravel 10                     |
| Queues / Jobs     | Laravel Queue, Horizon         |
| Caching & Metrics | Redis                          |
| Database          | MySQL / PostgreSQL             |
| Asynchronous Jobs | Supervisor                     |
| Analytics         | Redis (hashes and sorted sets) |

---

## **Setup Instructions**

### **1. Clone the Repository**

```bash
git clone https://github.com/rizvisharis/next-ventures-assignment.git
cd next-ventures-assignment
```

### **2. Install Dependencies**

```bash
composer install
```

### **3. Configure Environment**

Create a `.env` file:

```bash
cp .env.example .env
```

Update the following variables:

```env
APP_NAME="OrderSystem"
APP_ENV=local
APP_KEY=base64:your-key-here
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=orders_db
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
QUEUE_CONNECTION=redis
```

Generate the application key:

```bash
php artisan key:generate
```

---

### **4. Run Migrations**

```bash
php artisan migrate
```

---

### **5. Start Redis & Supervisor**

Start Redis:

```bash
redis-server
```

Start Supervisor (for queued jobs):

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

Alternatively, you can use:

```bash
php artisan queue:work
```

---

### **6. Start Laravel Horizon**

```bash
php artisan horizon
```

Horizon dashboard: [http://localhost/horizon](http://localhost/horizon)

---

## **Usage**

---

### **1. Import Orders**

Import a large CSV file of orders:

```bash
php artisan orders:import storage/app/orders.csv
```

**Sample CSV format:**

```csv
order_id,customer_id,total,items
ORD-2025-001,1,999.00,"[{""product"":""Laptop"",""qty"":1,""price"":999}]"
ORD-2025-002,2,199.99,"[{""product"":""Mouse"",""qty"":2,""price"":99.99}]"
ORD-2025-003,3,49.99,"[{""product"":""Keyboard"",""qty"":1,""price"":49.99}]"
ORD-2025-004,1,299.50,"[{""product"":""Monitor"",""qty"":1,""price"":299.5}]"
```

---

### **2. View Real-Time Metrics**

Fetch real-time KPIs and leaderboard:

```http
GET /api/metrics
```

**Response Example:**

```json
{
  "date": "2025-09-14",
  "kpi": {
    "revenue": 1548.48,
    "order_count": 4,
    "avg_order_value": 387.12
  },
  "leaderboard": [
    { "customer_id": "1", "revenue": 1298.50 },
    { "customer_id": "2", "revenue": 199.99 },
    { "customer_id": "3", "revenue": 49.99 }
  ]
}
```

---

### **3. Refund an Order**

Refund an order by sending a POST request with a unique `Idempotency-Key`.

**Endpoint:**

```http
POST /api/orders/{orderId}
```

**Headers:**

```
Idempotency-Key: 123e4567-e89b-12d3-a456-426614174000
Content-Type: application/json
```

**Request Body:**

```json
{
  "amount": 100.00
}
```

**Response Example:**

```json
{
  "message": "Refund request queued",
  "idempotency_key": "123e4567-e89b-12d3-a456-426614174000"
}
```

---

### **4. Check Laravel Horizon Dashboard**

Monitor job processing in real-time:

```
http://localhost/horizon
```

---

## **Project Architecture**

```
app/
├── Console/
│   └── Commands/
│       └── OrdersImport.php
├── Http/
│   └── Controllers/
│       ├── MetricsController.php
│       └── OrderRefundController.php
├── Jobs/
│   ├── ImportOrderCsvChunkJob.php
│   ├── ProcessOrderWorkflowJob.php
│   ├── ProcessRefundJob.php
│   └── SendOrderNotificationJob.php
├── Models/
│   ├── Customer.php
│   ├── Order.php
│   ├── Refund.php
│   └── OrderNotification.php
└── Services/
    ├── KPIService.php
    └── LeaderboardService.php
```

---

## **Key Components**

### **KPIService**

* Uses Redis **hashes** to store:

  * `revenue`
  * `order_count`
  * `avg_order_value`

### **LeaderboardService**

* Uses Redis **sorted sets** to rank top customers by revenue.

---

## **API Summary**

| Method | Endpoint           | Description                     |
| ------ | ------------------ | ------------------------------- |
| `POST` | `/api/orders/{id}` | Queue a refund request          |
| `GET`  | `/api/metrics`     | View daily KPIs and leaderboard |

---

## **Best Practices Implemented**

* **Queue jobs** to handle long-running tasks asynchronously.
* **Redis locks** to prevent double order processing.
* **Idempotency keys** for safe refund handling.
* **Real-time analytics** without hitting the database.
* **Horizon dashboard** for monitoring queues.

---

## **Example Workflow**

1. Import orders via CSV → orders queued.
2. Order workflow:

   * Stock reserved
   * Payment simulated
   * Finalized or rolled back
3. Notifications dispatched asynchronously.
4. KPIs and leaderboards updated in Redis.
5. Refund request queued and processed safely.

---


