# Kế hoạch Nâng cấp Bảo mật - englishClass

**Ngày tạo**: 2026-06-18
**Phạm vi**: Quick Wins + Ngắn hạn (Critical/High + Foundation)
**Có test**: PHPUnit tests cho mỗi fix

---

## Tổng quan

Hệ thống có nền tảng tốt (Gate, Policy, JWT) nhưng thiếu defense-in-depth ở authorization layer. Kế hoạch này tập trung sửa các lỗ hổng có tác động cao nhất trong thời gian ngắn, đồng thời thiết lập foundation (audit log, rate limit, role-based middleware) để dễ dàng mở rộng sau này.

---

## Phase 1: Critical Fixes (Ưu tiên cao nhất)

### 1.1. Fix CourseRequest authorization
- **File**: `Modules/Course/Http/Requests/CourseRequest.php`
- **Vấn đề**: `authorize()` trả về `true` - bất kỳ user authenticated nào cũng tạo được course
- **Fix**: Check role admin/teacher + check status active
- **Test**: Student bị 403, teacher được, admin được

### 1.2. Verify AuthController::register không cho set role
- **File**: `Modules/Auth/Http/Controllers/AuthController.php`
- **Fix**: Đảm bảo dùng `$request->only(['name','email','password','target_band'])` chứ không phải `$request->all()`
- **Test**: POST register với `role=admin` → bị ignore, user được tạo với role=student

### 1.3. Fix Telegram webhook secret mandatory ở production
- **File**: `Modules/TelegramBot/Http/Middleware/VerifyTelegramSecret.php`
- **Fix**: Throw 503 nếu production mà secret trống
- **Test**: Production + empty secret → 503; dev + empty secret → pass

### 1.4. Fix TelegramWebhookController duplicate secret check
- **File**: `app/Http/Controllers/TelegramWebhookController.php:24`
- **Fix**: Xóa duplicate check ở controller (middleware đã handle đúng)
- **Test**: Request thiếu header → middleware 403

---

## Phase 2: High Priority Fixes

### 2.1. Thêm rate limit cho login/register
- **File**: `Modules/Auth/routes/web.php` và `routes/api.php`
- **Fix**: `throttle:5,1` cho login/register
- **Test**: 6 lần login sai trong 1 phút → 429

### 2.2. Thêm role check trước quota check ở CourseController
- **File**: `Modules/Course/Http/Controllers/CourseController.php`
- **Fix**: Student không được tạo course (chỉ teacher/admin)
- **Test**: Student → 403

### 2.3. MIME validation cho classroom upload
- **File**: `Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php`
- **Fix**: Validate mimes + size
- **Test**: Upload .php → 422

### 2.4. Cap pagination limit
- **File**: `Modules/Course/Services/CourseService.php`
- **Fix**: `min($limit, 100)`
- **Test**: ?limit=99999999 → chỉ trả 100

---

## Phase 3: Foundation - Audit Logging

### 3.1. Tạo AuditLog model + migration
- **File**: `app/Models/AuditLog.php`, `database/migrations/..._create_audit_logs_table.php`
- **Schema**: id, actor_id, target_id, action, ip, user_agent, metadata(json), created_at
- **Index**: (actor_id, created_at), (action, created_at)

### 3.2. AuditLogger service
- **File**: `app/Services/AuditLogger.php`
- **Methods**: `log(string $action, ?Model $target, array $metadata)`
- **Helper**: `actorFromRequest(Request)`

### 3.3. Audit middleware cho admin routes
- **File**: `app/Http/Middleware/AuditAdminActions.php`
- **Apply**: Tự động ghi log cho mọi route `/admin/*`

### 3.4. Log admin actions quan trọng
- Approve/reject user (AdminUserController)
- Override quota (LessonRequestController::review)
- Reset password (nếu có)

### 3.5. View audit logs trong admin
- **File**: `resources/views/admin/audit-logs/index.blade.php`
- **Route**: `GET /admin/audit-logs`

---

## Phase 4: Foundation - Rate Limiting toàn diện

### 4.1. Tạo RateLimitService config
- **File**: `app/Providers/RouteServiceProvider.php` (đăng ký limiters)
- **Limits**: login (5/min), register (3/hour), ai/chat (20/min), ai/speaking (10/min)

### 4.2. Apply rate limit cho AI endpoints
- **File**: `routes/web.php`, API routes
- **Test**: Spam AI → 429

### 4.3. Per-user quota request rate limit
- **File**: `app/Http/Middleware/ThrottleLessonRequests.php`
- **Limit**: 3 requests/ngày/user
- **Test**: Spam tạo request → 429

---

## Phase 5: Configuration & Hygiene

### 5.1. Fix .env.example defaults
- **File**: `.env.example`
- **Fix**: APP_DEBUG=false, comment cảnh báo APP_KEY

### 5.2. Tăng classroom invite code entropy
- **File**: `Modules/Classroom/Services/ClassroomService.php`
- **Fix**: `Str::random(10)` thay vì 6

### 5.3. Sanitize logging trong webhook
- **File**: `app/Http/Controllers/TelegramWebhookController.php`
- **Fix**: Không dump full exception, chỉ log message + class

### 5.4. AppServiceProvider dùng enum constant
- **File**: `app/Providers/AppServiceProvider.php`
- **Fix**: `UserRole::Admin->value` thay vì string literal

---

## Phase 6: Advanced Hardening

### 6.1. Security headers middleware
- **File**: `app/Http/Middleware/SecurityHeaders.php`
- **Headers**: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, CSP

### 6.2. Refactor AppServiceProvider gates dùng enum
- **File**: `app/Providers/AppServiceProvider.php`
- **Fix**: Gates dùng `UserRole::Admin->value`

### 6.3. Tạo RoleMiddleware
- **File**: `app/Http/Middleware/EnsureUserHasRole.php`
- **Usage**: `->middleware('role:admin,teacher')`

---

## Phase 7: Tests

### 7.1. Security test suite
- **File**: `tests/Feature/Security/*Test.php`
- **Covers**:
  - `AuthorizationTest` - role-based access
  - `RateLimitTest` - throttle hoạt động
  - `MassAssignmentTest` - không thể set role qua register
  - `FileUploadTest` - MIME validation
  - `TelegramWebhookSecurityTest` - secret check
  - `AuditLogTest` - admin actions được log

### 7.2. Update test database
- **File**: `tests/Feature/` - thêm test mới
- **Run**: `php artisan test --filter=Security`

---

## Phase 8: Documentation

### 8.1. SECURITY.md
- **File**: `SECURITY.md`
- **Content**: Reporting vulnerabilities, security policy

### 8.2. CHANGELOG entry
- **File**: `CHANGELOG.md` (nếu có)
- **Entry**: Security hardening 2026-06-18

---

## Timeline ước tính

| Phase | Effort | Tasks |
|-------|--------|-------|
| Phase 1: Critical | 30 phút | 4 fixes + tests |
| Phase 2: High | 1.5 giờ | 4 fixes + tests |
| Phase 3: Audit Logging | 2 giờ | Model, service, middleware, view |
| Phase 4: Rate Limiting | 1 giờ | Service + middleware + tests |
| Phase 5: Config & Hygiene | 30 phút | 4 fixes |
| Phase 6: Advanced | 1 giờ | Headers + middleware |
| Phase 7: Tests | 1.5 giờ | Test suite |
| Phase 8: Docs | 30 phút | SECURITY.md |
| **Tổng** | **~8 giờ** | |

---

## Cách triển khai

1. Đọc file cần sửa
2. Edit code
3. Lint check `php -l`
4. Viết test
5. Chạy test `php artisan test`
6. Đánh dấu task hoàn thành

Mỗi fix sẽ được commit riêng (commit message rõ ràng) để dễ rollback nếu có vấn đề.

---

## Sau khi hoàn thành

- [ ] Run full test suite
- [ ] Cập nhật .env production
- [ ] Review audit logs
- [ ] Đặt lịch rotate JWT secret
- [ ] Cân nhắc Sanctum migration (Phase dài hạn)