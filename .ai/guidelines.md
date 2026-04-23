# FeedLink Laravel Boost AI Guidelines

This file registers custom AI guidelines and skills for the FeedLink Laravel 11 API project.

## Mandatory Skills

### modular-monolithic-pattern

**Trigger:** Before writing any PHP code (new modules, controllers, services, repositories, models, requests)

**Purpose:** Ensures all code follows the modular-monolithic architectural pattern, promoting code organization, reusability, and maintainability.

**When to Use:**
- Creating new controllers, services, or repositories
- Building new modules or features
- Adding models or form requests
- Refactoring existing code

**Key Points:**
- All Form Request classes **must** extend `App\Modules\Core\Requests\BaseRequest`
- Never extend `Illuminate\Foundation\Http\FormRequest` directly
- Override `store()` for POST requests and `update()` for PUT/PATCH requests, not `rules()`
- Follow the existing modular directory structure
- Maintain separation of concerns between controllers, services, and repositories

**Reference:** See `modular-monolithic-pattern` skill for full implementation guidelines.

---

## API Documentation Rule

Whenever any API route is **added, removed, renamed, or updated** in `routes/api.php`, you **must** update `API_DOC.md` in the same task/PR.

Documentation must include:
- Exact HTTP method and path
- Auth requirements and role restrictions
- Request payload fields with validation rules
- Query parameters
- Success response shape and status code
- Known error response cases

---

## Laravel Boost Integration

This project uses:
- **Laravel 12** with streamlined file structure
- **Octane v2** for production performance
- **Passport v13** for API authentication
- **Spatie Permission** for role-based access control
- **PHPUnit v11** for testing (all tests must pass before finalizing)

See `CLAUDE.md` for detailed project specifications and development phases.
