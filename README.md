# UNWAHAS RabbitMQ Broadcast Library

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.1-blue.svg)](https://php.net)
[![Laravel Support](https://img.shields.io/badge/Laravel-9.x%20%7C%2010.x%20%7C%2011.x%20%7C%2012.x-red.svg)](https://laravel.com)

Package library Laravel untuk integrasi RabbitMQ Broadcast (Fanout Exchange & Direct Queue) serta Multi-Queue Consumer antar service di lingkungan UNWAHAS.

---

## 📦 Fitur Utama

- **Dependency Injection Ready**: Langsung inject `RabbitMQService` ke Controller, Command, atau Service.
- **Standarisasi Payload Broadcast**: Format seragam `{ data: [...], from: 'sikawan', item: 'Biodata' }` untuk pertukaran data antar sistem (SIKAWAN, SIKADU, SIMAWA, dll).
- **Helper Standar**: Method `sendDosen($data)` untuk broadcast data master Dosen / Biodata.
- **Fanout Exchange & Direct Queue Support**: Mendukung pengiriman via Fanout `exchange()` ataupun Direct `queue()`.
- **Multi-Queue Fanout Consumer**: Mendengarkan beberapa antrean RabbitMQ secara bersamaan dalam satu command daemon dengan heartbeat auto-negotiation.
- **Auto-discovery Laravel**: Langsung aktif saat package diinstall.

---

## 🚀 Instalasi

### 1. Tambahkan ke `composer.json` project Laravel:

Jika menggunakan local path repo:
```json
"repositories": [
    {
        "type": "path",
        "url": "../broadcast-unwahas"
    }
]
```
Lalu jalankan:
```bash
composer require unwahas/broadcast
# Atau jika menggunakan path repository lokal:
composer require unwahas/broadcast:@dev
```

### 2. Publish Konfigurasi
```bash
php artisan vendor:publish --tag=rabbitmq-broadcast-config
```

### 3. Konfigurasi `.env`
```env
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_HEARTBEAT=30
RABBITMQ_APP_NAME=sikawan
RABBITMQ_EXCHANGE=laravel_exchange_sikawan
RABBITMQ_QUEUE=laravel_queue_dosen_sikawan
```

---

## 📤 Mengirim Pesan (Broadcasting / Publishing)

### 1. Menggunakan Dependency Injection di Controller / Service

```php
namespace App\Http\Controllers\Experiment;

use App\Http\Controllers\Controller;
use Unwahas\Broadcast\Services\RabbitMQService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BroadcastController extends Controller
{
    public function __construct(
        protected RabbitMQService $rabbitMQService
    ) {}

    public function sendData(Request $request)
    {
        $validated = $request->validate([
            'email'          => 'required|email',
            'nama'           => 'required|string',
            'gelar_depan'    => 'nullable|string',
            'gelar_belakang' => 'nullable|string',
        ]);

        // Kirim broadcast standar data Dosen
        $this->rabbitMQService->sendDosen([
            'id'             => $request->input('id', (string) Str::uuid()),
            'email'          => $validated['email'],
            'nama'           => $validated['nama'],
            'gelar_depan'    => $validated['gelar_depan'],
            'gelar_belakang' => $validated['gelar_belakang'],
            'sent_at'        => now()->toDateTimeString(),
        ], from: 'sikawan');

        return response()->json([
            'status'  => 'success',
            'message' => 'Data broadcasted successfully!',
        ]);
    }
}
```

### 2. Method `exchange()` (Fanout Exchange)

Kirim payload ke Fanout Exchange sehingga semua queue yang bind ke exchange tersebut akan menerima copy pesan:

```php
$this->rabbitMQService->exchange(
    data: ['id' => 1, 'nama' => 'Budi'],
    from: 'sikawan',
    item: 'Biodata',
    exchange: 'laravel_exchange_sikawan' // opsional, default ke config
);
```

### 3. Method `queue()` (Direct Queue)

Kirim payload langsung ke Queue tertentu:

```php
$this->rabbitMQService->queue(
    data: ['id' => 1, 'nama' => 'Budi'],
    from: 'sikawan',
    item: 'Biodata',
    queue: 'laravel_queue_dosen_sikawan' // opsional, default ke config
);
```

### 4. Menggunakan Facade `RabbitMqBroadcast`

```php
use Unwahas\Broadcast\Facades\RabbitMqBroadcast;

RabbitMqBroadcast::sendDosen([
    'id'    => (string) Str::uuid(),
    'email' => 'dosen@unwahas.ac.id',
    'nama'  => 'Dr. Budi Santoso, M.Kom',
]);
```

---

## 🎧 Menjalankan Consumer

### Konfigurasi Routing Consumer (`config/rabbitmq_broadcast.php`)
```php
'consumers' => [
    [
        'from' => 'sikawan',
        'exchange' => 'laravel_exchange_sikawan',
        'queue' => 'laravel_queue_dosen_sikawan',
        'items' => [
            'Biodata' => [\App\Services\DosenService::class, 'updateDosenConsumer'],
        ],
    ],
    [
        'from' => 'simawa',
        'exchange' => 'laravel_exchange_simawa',
        'queue' => 'laravel_queue_sikawan_simawa',
        'items' => [
            'Mahasiswa' => [\App\Services\MahasiswaService::class, 'updateMahasiswaConsumer'],
        ],
    ],
],
```

### Contoh Service Consumer:
```php
namespace App\Services;

class DosenService
{
    public function updateDosenConsumer(array $data, array $rawPayload = []): void
    {
        // $data berisi ['id' => '...', 'email' => '...', 'nama' => '...']
        \Log::info('Dosen received:', $data);
    }
}
```

### Jalankan Consumer Daemon:
```bash
# Menjalankan seluruh antrean yang terdaftar
php artisan rabbitmq:consume

# Menjalankan sender tertentu saja
php artisan rabbitmq:consume --from=sikawan
```

---

## 🛠️ Production Deployment (Supervisor)

Buat `/etc/supervisor/conf.d/rabbitmq-consumer.conf`:

```ini
[program:rabbitmq-consumer]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan rabbitmq:consume
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/rabbitmq-consumer.log
stopwaitsecs=3600
```

---

## 📄 Lisensi

MIT License. Dikelola oleh UNWAHAS Dev Team.
