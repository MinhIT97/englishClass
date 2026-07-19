# upgrade-roadmap - Draft

status: awaiting-approval
pending action: write .omo/plans/upgrade-roadmap.md (DONE — file written)
intent: UNCLEAR (broad improvement request, adopted best-practice defaults)

## User request (resolved)
"làm toàn bộ cho tôi bỏ phần cache lại làm sau"
(Implement all improvement suggestions, skip cache part for later.)

## Resolved forks (adopted defaults)

| # | Fork | Adopted default | Rationale | Reversible? |
|---|------|-----------------|-----------|-------------|
| 1 | Scope: what "all" means | 10 of 11 suggestions from previous turn (skip cache) | User explicit "bỏ phần cache lại làm sau" | Yes |
| 2 | Migration approach | Incremental — each suggestion as separate todo with own commit | Audit-comparable workflow, easy rollback | Yes |
| 3 | Sanctum vs Passport | **Sanctum for SPA + Passport for OAuth2** (both) | User-facing UX + future mobile app | Yes (config switch) |
| 4 | Langfuse vs Lunary | **Langfuse** | Open-source, self-host option, no vendor lock-in | Yes |
| 5 | Sentry vs Flare | **Sentry** | More integrations, free tier OK | Yes |
| 6 | Testing framework for E2E | **Playwright** (existing playwright skill) | Already in available skills, fast | Yes |
| 7 | Frontend (React Native vs Flutter) | **DEFERRED** — recommend React Native | Most code reuse from existing web (Blade → RN) | Yes (no commitment) |
| 8 | SRS algorithm | **SM-2** (industry standard, Anki uses it) | No reason to invent new algo | Yes |
| 9 | Pronunciation scoring | **Hybrid: Web Speech API for instant + Gemini for final** | Latency vs accuracy tradeoff | Yes |
| 10 | CI/CD platform | **GitHub Actions** | Already have `.github/` dir | Yes |
| 11 | Load testing tool | **K6** (scriptable, free) | Industry standard, Grafana integration | Yes |
| 12 | Horizon vs other queue dashboards | **Horizon** (Laravel-native) | First-class Laravel integration | Yes |
| 13 | Rate limit metrics | **Prometheus exporter + Grafana** | Self-host, no external dep | Yes |
| 14 | Architecture refactor (move app controllers to modules) | **Selective refactor** — only files in wrong location that have a security/coupling reason | Don't over-engineer | Yes |

## Components ledger (10 components = scope)

| ID | Component | Wave | Estimated effort |
|----|-----------|------|------------------|
| C1 | Sanctum + Passport migration | 1 | 8h |
| C2 | Spatie laravel-permission | 1 | 4h |
| C3 | AI queue + caching + streaming (Langfuse integration) | 2 | 12h |
| C4 | Reverb WebSocket + SSE streaming for AI | 2 | 6h |
| C5 | Queue infrastructure (Redis + Horizon) | 2 | 3h |
| C6 | Error tracking (Sentry) + CI/CD (GitHub Actions) | 3 | 5h |
| C7 | E2E tests (Playwright) | 3 | 8h |
| C8 | Load testing (K6) + rate-limit metrics | 3 | 4h |
| C9 | SM-2 algorithm for flashcards | 4 | 4h |
| C10 | Writing rubric (4 criteria × 0-9) + Speaking mock test | 4 | 8h |

## Open assumptions (announced, not asked)

1. **Sanctum + Passport both added** — Sanctum for web SPA auth (replace JWT), Passport for OAuth2 (future mobile/3rd party). Existing JWT-auth kept as fallback for 30 days via feature flag.
2. **AI streaming uses Reverb** — already configured. No new dependency.
3. **Langfuse self-hosted** (Docker) — no external SaaS dependency.
4. **Sentry self-hosted optional** — but default to Sentry.io free tier (avoid ops complexity).
5. **CI/CD targets: phpunit + composer audit + security scan + pint (linter) on every PR**.
6. **E2E tests target 3 critical flows**: register/login, take mock test, view result. Not exhaustive coverage.
7. **Load test scenarios**: 100 concurrent login attempts, 50 concurrent AI tutor, 200 concurrent search.
8. **SM-2 implementation**: pure Laravel implementation, no external library.
9. **Writing rubric**: match official IELTS 4-criteria model (Task Achievement, Coherence & Cohesion, Lexical Resource, Grammatical Range & Accuracy).
10. **Speaking mock test**: timer + audio recording + Gemini scoring, no human review.

## Approval status
- All forks resolved via best-practice defaults (UNCLEAR path)
- User explicit "làm toàn bộ bỏ phần cache" = clear scope
- User confirmed G2 (AI-only cache) + G6 (dual-source roles)
- Metis gap analysis: 25 gaps found (6 Critical, 17 Major, 2 Minor)
- ALL 25 GAPS FOLDED INTO PLAN — see "Metis Gap Analysis" section in plan
- Plan written at .omo/plans/upgrade-roadmap.md — 17 todos + 4 final wave = 21 total
- Status: ready for execution via /start-work

## Plan structure
- 4 waves: Wave 1 (Auth), Wave 2 (AI infra), Wave 3 (Reliability), Wave 4 (Features)
- Each todo: 1 commit, conventional commits format
- Branch: `feature/upgrade-roadmap`, squash merge
- Each todo has: References, Acceptance criteria, QA scenarios (happy + failure)
- TDD where applicable, tests-after for plumbing
- No breaking changes to existing 19 security tests

## Next step
User runs `/start-work upgrade-roadmap` to dispatch Sisyphus-Junior workers on the 4 waves.

## Excluded from scope
- Cache strategy (user explicit defer)
- Mobile app (DEFERRED — recommend React Native but no implementation)
- New languages (vi/en is current scope, no i18n expansion)
- Real-time collaboration features
- Payment/billing (IELTS platform is free)
