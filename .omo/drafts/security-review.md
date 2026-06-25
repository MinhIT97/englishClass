# security-review - Draft

status: awaiting-approval
pending action: write .omo/plans/security-review.md (DONE — file written at .omo/plans/security-review.md)
intent: CLEAR (all 5 forks resolved with user's explicit selection of recommended defaults)

## User request (resolved)
"review toàn bộ project tìm ra lỗi bảo mật cho tôi"

## Resolved forks
| Fork | User choice |
|------|-------------|
| F1 Scope | Chỉ báo cáo read-only (Recommended) |
| F2 Depth | Full audit (Recommended) |
| F3 Output format | 1 file SECURITY_AUDIT.md (Recommended) |
| F4 Tooling | composer audit + grep + php -l + static review (Recommended) |
| F5 Fix snippet | Kèm code diff cho mỗi High/Critical (Recommended) |

## Plan summary
- 11 todos total: 10 attack-surface components + 1 consolidation
- Wave 1 (parallel): C1 Config & secrets, C2 Composer supply chain
- Wave 2 (parallel): C3 Auth, C4 Authz/IDOR, C5 Input validation, C6 File upload, C7 Telegram
- Wave 3 (parallel): C8 AI/Gemini, C9 Rate limit/DoS, C10 Logging/PII
- Wave 4 (sequential): consolidate to SECURITY_AUDIT.md
- Final verification wave (4 sub-tasks parallel)

## Confirmed finding (already verified at ground phase)
- `.env` local chứa 4 secrets thật: Telegram bot token, Gemini API key, JWT secret, INTERNAL_API_TOKEN — [REDACTED: secret prefixes not shown per no-leak policy]
- `.env` KHÔNG được git track (đã verify: gitignore + ls-files + log)
- Mức: High (recommend rotate), không Critical (không qua git history)

## Execution plan
Worker (Sisyphus) sẽ chạy 11 todos. Mỗi todo:
- Đọc files theo References (executor has no interview context)
- Ghi evidence vào `.omo/evidence/task-N-security-review.md`
- Findings có schema đầy đủ: ID/Severity/CWE/OWASP/Location/Evidence/Remediation/Diff
- KHÔNG edit product code
- KHÔNG commit
- KHÔNG exploit thật
- KHÔNG leak secret ra file

Sau Wave 4:
- F1 Plan compliance: tất cả 11 todos done + evidence files tồn tại
- F2 Report quality: format đúng, mỗi High/Critical có diff
- F3 Secret leak check: grep SECURITY_AUDIT.md + evidence files cho full secret
- F4 Scope fidelity: worker không edit code, không commit, không rotate

## Final deliverable
- File `SECURITY_AUDIT.md` ở working tree (untracked, bạn review rồi tự commit)
- Format: Executive Summary + Findings table + Chi tiết theo severity + Positive Findings + Out-of-scope + 3 Appendices

## Worker handoff note
Worker chỉ chạy plan này khi user gõ `$start-work` (hoặc `/start-work`).
Nếu user chỉ đọc plan và OK, worker KHÔNG tự chạy.

## Approval status
User đã chọn tất cả forks + viết plan → APPROVAL GRANTED.
User đã gõ `$start-work` ở turn sau → bắt đầu execution phase (chuyển sang start-work skill).