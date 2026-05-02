# DENTAL PRO — Hệ Thống Quản Lý Phòng Khám Nha Khoa

Hệ thống quản lý nội bộ cho phòng khám nha khoa, hỗ trợ quản lý người dùng,
phân quyền, hồ sơ chuyên môn, lịch làm việc, danh mục dịch vụ và gói dịch vụ.

Dự án được tách thành **2 phần** chạy độc lập:

| Thành phần | Công nghệ                                                  | Cổng mặc định          |
| ---------- | ---------------------------------------------------------- | ---------------------- |
| `server/`  | Laravel 12 (PHP 8.2+), Sanctum, Socialite (Google OAuth)   | `http://localhost:8000` |
| `client/`  | React 19, Vite 8, TailwindCSS, Radix UI, Axios             | `http://localhost:5173` |

---

## 1. Yêu Cầu Hệ Thống

Trước khi cài đặt, máy local cần có:

| Phần mềm        | Phiên bản đề nghị | Ghi chú                                          |
| --------------- | ----------------- | ------------------------------------------------ |
| **PHP**         | `>= 8.2`          | Bật các extension: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `gd` |
| **Composer**    | `>= 2.5`          | Trình quản lý package PHP                        |
| **Node.js**     | `>= 20.x`         | Khuyến nghị bản LTS                              |
| **npm**         | `>= 10.x`         | Đi kèm Node.js                                   |
| **MySQL**       | `>= 8.0` (hoặc MariaDB 10.6+) | Hoặc dùng SQLite cho phương án nhanh |
| **Git**         | Bất kỳ            | Để clone mã nguồn                                |

> Khuyến nghị dùng **XAMPP / Laragon / Herd** trên Windows hoặc **Homebrew** trên macOS
> để có sẵn PHP + MySQL.

Kiểm tra nhanh phiên bản:

```bash
php -v
composer -V
node -v
npm -v
mysql --version
```

---

## 2. Tải Mã Nguồn

```bash
git clone https://github.com/lionelmahn/testlionelmahn45.git
cd testlionelmahn45
```

Cấu trúc thư mục sau khi clone:

```
testlionelmahn45/
├── client/        # Frontend React + Vite
├── server/        # Backend Laravel
└── README.md
```

---

## 3. Cài Đặt Backend (Laravel)

### 3.1. Di chuyển vào thư mục backend

```bash
cd server
```

### 3.2. Cài đặt PHP dependencies

```bash
composer install
```

### 3.3. Tạo file cấu hình `.env`

```bash
cp .env.example .env
php artisan key:generate
```

### 3.4. Cấu hình Database

Mở file `server/.env` và chọn **một** trong hai phương án:

#### Phương án A — MySQL (khuyến nghị cho môi trường giảng dạy)

1. Tạo database trống tên `dental_pro` trong MySQL (qua phpMyAdmin, MySQL Workbench
   hoặc CLI):

   ```sql
   CREATE DATABASE dental_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Cập nhật `server/.env`:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=dental_pro
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   > Đổi `DB_USERNAME` / `DB_PASSWORD` cho khớp với MySQL trên máy của bạn.

#### Phương án B — SQLite (chạy nhanh, không cần cài MySQL)

1. Tạo file database rỗng:

   ```bash
   touch database/database.sqlite
   ```

2. Trong `server/.env` chỉ cần để mặc định:

   ```env
   DB_CONNECTION=sqlite
   ```

   (xoá hoặc comment các dòng `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
   `DB_PASSWORD`).

### 3.5. (Tuỳ chọn) Cấu hình Google OAuth

Nếu **không** dùng tính năng đăng nhập bằng Google, có thể bỏ qua bước này — hệ
thống vẫn cho phép đăng nhập bằng tài khoản nội bộ.

Nếu muốn bật Google Login, thêm vào `server/.env`:

```env
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

### 3.6. (Tuỳ chọn) Cấu hình Mail cho OTP

Hệ thống có gửi OTP đăng nhập / quên mật khẩu qua email. Mặc định
`MAIL_MAILER=log` — OTP sẽ được ghi vào `storage/logs/laravel.log` thay vì gửi
email thật, rất tiện cho môi trường demo.

> Khi cần test luồng OTP, mở file `server/storage/logs/laravel.log` để lấy mã OTP.

Nếu muốn gửi mail thật (Mailtrap, Gmail SMTP, …), cập nhật các biến `MAIL_*`
trong `.env` theo tài liệu Laravel.

### 3.7. Chạy Migration và Seeder

Lệnh dưới đây sẽ tạo toàn bộ bảng và **seed dữ liệu mẫu** (roles, permissions,
tài khoản mặc định, chi nhánh, dịch vụ…):

```bash
php artisan migrate:fresh --seed
php artisan config:clear
php artisan route:clear
```

> ⚠️ `migrate:fresh` sẽ **xoá toàn bộ dữ liệu cũ** trong database và tạo lại từ
> đầu. Chỉ dùng cho lần cài đặt đầu tiên hoặc khi muốn reset.

### 3.8. (Tuỳ chọn) Tạo symbolic link cho Storage

Nếu cần upload và phục vụ file (ảnh chứng chỉ chuyên môn, đính kèm dịch vụ…):

```bash
php artisan storage:link
```

### 3.9. Khởi động backend server

```bash
php artisan serve
```

Mặc định backend sẽ chạy tại: **http://localhost:8000**

API base URL: **http://localhost:8000/api**

> Có thể kiểm tra health-check bằng cách mở: http://localhost:8000/up

---

## 4. Cài Đặt Frontend (React + Vite)

Mở **một terminal mới** (giữ nguyên terminal backend đang chạy).

### 4.1. Di chuyển vào thư mục frontend

```bash
cd client
```

### 4.2. Cài đặt npm dependencies

```bash
npm install
```

### 4.3. Tạo file `.env` cho frontend

Tạo mới file `client/.env` với nội dung:

```env
# URL backend API (phải khớp với cổng php artisan serve ở bước 3.9)
VITE_API_URL=http://localhost:8000/api

# Google OAuth Client ID (chỉ cần nếu bật Google Login)
VITE_GOOGLE_CLIENT_ID=your-google-client-id

# WebSocket URL (placeholder, dùng cho tính năng real-time tương lai)
VITE_SOCKET_URL=ws://localhost:6001
```

> Nếu **không** dùng Google Login, có thể để `VITE_GOOGLE_CLIENT_ID=` rỗng,
> tính năng Google sẽ bị vô hiệu hoá nhưng đăng nhập nội bộ vẫn hoạt động.

### 4.4. Khởi động dev server

```bash
npm run dev
```

Mặc định frontend sẽ chạy tại: **http://localhost:5173**

> Backend đã cấu hình CORS sẵn cho `http://localhost:5173` và
> `http://localhost:5174` trong `server/config/cors.php`. Nếu Vite chạy ở cổng
> khác, cần thêm origin tương ứng vào `allowed_origins`.

---

## 5. Tài Khoản Mặc Định Để Đăng Nhập

Sau khi chạy seeder ở bước 3.7, hệ thống đã có sẵn các tài khoản sau (tất cả
đều dùng cùng một mật khẩu):

| Vai trò          | Username        | Email                | Mật khẩu      |
| ---------------- | --------------- | -------------------- | ------------- |
| Quản trị viên    | `admin`         | `admin@dental.com`   | `Dental@123`  |
| Bác sĩ           | `bacsi_a`       | `bacsi@dental.com`   | `Dental@123`  |
| Lễ tân           | `letan_b`       | `letan@dental.com`   | `Dental@123`  |
| Kế toán          | `ketoan_c`      | `ketoan@dental.com`  | `Dental@123`  |
| Lễ tân (đã khoá) | `letan_locked`  | `locked@dental.com`  | `Dental@123`  |

> Tài khoản `letan_locked` được tạo sẵn ở trạng thái `locked` để kiểm tra luồng
> chặn đăng nhập với tài khoản bị khoá.

Truy cập http://localhost:5173/login → đăng nhập bằng `username` (hoặc `email`)
và mật khẩu ở bảng trên.

---

## 6. Quy Trình Khởi Động Nhanh (TL;DR)

Mở **2 terminal** song song:

**Terminal 1 — Backend:**

```bash
cd server
composer install
cp .env.example .env
php artisan key:generate
# (chỉnh sửa .env -> chọn DB MySQL hoặc SQLite)
php artisan migrate:fresh --seed
php artisan serve
```

**Terminal 2 — Frontend:**

```bash
cd client
npm install
echo "VITE_API_URL=http://localhost:8000/api" > .env
npm run dev
```

Mở trình duyệt: **http://localhost:5173** → đăng nhập bằng `admin / Dental@123`.

---

## 7. Tổng Quan Tính Năng

Hệ thống đang cung cấp các module sau (truy cập theo phân quyền của user):

- **Quản lý người dùng & vai trò** — tạo, sửa, khoá user; gán quyền theo vai trò.
- **Quản lý nhân sự (Staff)** — hồ sơ nhân viên, chi nhánh, đổi trạng thái,
  reset mật khẩu, lịch sử thay đổi.
- **Hồ sơ chuyên môn (Professional Profile)** — bác sĩ tự cập nhật → admin duyệt /
  từ chối / vô hiệu, kèm chứng chỉ và chuyên khoa.
- **Lịch làm việc** — admin xếp ca, sao chép tuần, xem thống kê theo chi nhánh;
  nhân viên xem ca cá nhân, gửi đơn nghỉ phép, xin đổi ca.
- **Danh mục dịch vụ (Service Catalog)** — CRUD dịch vụ, đính kèm tài liệu, đổi
  trạng thái, xem audit log.
- **Gói dịch vụ (Service Package)** — quản lý gói, phiên bản, clone, thông báo
  dịch vụ ngừng cung cấp trong gói.
- **Đăng nhập** — username/email + password, OTP qua email cho tài khoản nhạy
  cảm, đăng nhập bằng Google (nếu cấu hình).
- **Quên mật khẩu** — gửi OTP qua email → xác minh → đặt lại mật khẩu.
- **Dashboard** — view khác nhau cho từng vai trò: Admin / Bác sĩ / Lễ tân /
  Kế toán / Bệnh nhân.

---

## 8. Cấu Trúc Mã Nguồn Tóm Tắt

```
server/
├── app/
│   ├── Http/Controllers/Api/   # Toàn bộ controller REST API
│   ├── Models/                 # Eloquent models (User, Staff, Service, ...)
│   ├── Services/               # Business logic (AuthService, OtpService, ...)
│   └── Http/Middleware/        # RoleMiddleware (kiểm tra role)
├── database/
│   ├── migrations/             # Schema database
│   └── seeders/                # Seed dữ liệu mẫu
├── routes/
│   ├── api.php                 # Toàn bộ endpoint REST
│   └── web.php                 # Trang welcome (không dùng SPA)
└── config/                     # Sanctum, CORS, services (Google), ...

client/
├── src/
│   ├── api/                    # Axios client + các API service
│   ├── components/             # Component UI tái sử dụng (Radix + Tailwind)
│   ├── features/               # Module nghiệp vụ (staff, service, ...)
│   ├── hooks/                  # Custom hooks (useAuth, ...)
│   ├── layout/                 # Layout chính có sidebar
│   ├── page/                   # Các trang theo vai trò
│   ├── route/AppRouter.jsx     # Khai báo route + bảo vệ theo permission
│   ├── service/                # Auth service, lưu token, ...
│   ├── store/                  # State management
│   └── main.jsx                # Entry point (bọc GoogleOAuthProvider)
└── vite.config.js              # Alias `@` → `./src`
```

---

## 9. Các Lệnh Hữu Ích

### Backend

```bash
php artisan migrate:fresh --seed   # Reset toàn bộ DB và seed lại
php artisan migrate                # Chạy migration mới (giữ dữ liệu cũ)
php artisan db:seed                # Chạy lại seeder
php artisan route:list             # Liệt kê toàn bộ route
php artisan tinker                 # REPL tương tác với Eloquent
php artisan test                   # Chạy test suite (PHPUnit)
php artisan config:clear           # Xoá cache config
php artisan cache:clear            # Xoá cache app
```

### Frontend

```bash
npm run dev       # Dev server (Vite, hot reload)
npm run build     # Build production -> client/dist
npm run preview   # Xem thử bản build production
npm run lint      # Chạy ESLint
```

---

## 10. Xử Lý Sự Cố Thường Gặp

| Lỗi                                                            | Cách khắc phục                                                                                                                                |
| -------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `SQLSTATE[HY000] [2002] Connection refused`                    | MySQL chưa chạy hoặc sai `DB_HOST` / `DB_PORT`. Bật MySQL service hoặc chuyển sang SQLite (mục 3.4 — Phương án B).                            |
| `could not find driver` khi migrate                            | Thiếu extension PHP `pdo_mysql` (hoặc `pdo_sqlite`). Bật trong `php.ini` rồi restart terminal.                                                |
| `Failed to load resource: net::ERR_CONNECTION_REFUSED` từ FE   | Backend chưa chạy `php artisan serve`, hoặc `VITE_API_URL` không khớp với cổng backend.                                                       |
| Frontend gọi API bị chặn vì CORS                               | Vite đang chạy ở cổng khác `5173/5174`. Sửa `server/config/cors.php` → thêm origin mới vào `allowed_origins` rồi `php artisan config:clear`.   |
| Đăng nhập trả 401 "Tài khoản bị khoá"                          | Đang dùng tài khoản `letan_locked` (cố ý khoá để test). Đăng nhập bằng các tài khoản khác ở mục 5.                                            |
| Không nhận được email OTP khi test                             | `MAIL_MAILER=log` (mặc định) — mở `server/storage/logs/laravel.log` để xem mã OTP.                                                            |
| `npm install` lỗi peer dependency                              | Thử `npm install --legacy-peer-deps`. Đảm bảo Node.js >= 20.                                                                                  |
| `php artisan key:generate` báo `No application encryption key` | Đảm bảo file `.env` đã được tạo từ `.env.example` trước khi chạy lệnh này.                                                                    |
| Quên mật khẩu admin sau khi nghịch DB                          | Chạy lại `php artisan migrate:fresh --seed` để reset toàn bộ dữ liệu về trạng thái mặc định (mọi tài khoản đều về mật khẩu `Dental@123`).     |

---

## 11. Liên Hệ

- **Tác giả**: lionelmahn (`nanhtrang45@gmail.com`)
- **Repository**: https://github.com/lionelmahn/testlionelmahn45

Mọi thắc mắc trong quá trình cài đặt, vui lòng tạo issue trên repository hoặc
liên hệ trực tiếp tác giả qua email.
