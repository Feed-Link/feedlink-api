# Test Scenario: Building a new food listing feature

## Task
Create a new module for food listings with CRUD operations.

## Baseline Test (WITHOUT the skill)
Observe what the agent naturally does when not guided by the modular pattern skill.

### Expected Issues:
1. Might put business logic in controller instead of service
2. Might write raw queries instead of extending BaseRepository
3. Might not use FormRequest for validation
4. Might hardcode enum values instead of using RolesEnum
5. Might not use BaseModel with HasUuids
6. Might not use HasApiResponse trait for consistent responses
7. Might not follow proper file structure (Entities/Repositories/Services/Controllers)
8. Might not use Filterables trait for filtering

---
# Test Scenario: Creating a new recipient request module

## Task
Build recipient food request CRUD with location filtering.

## Baseline Test (WITHOUT the skill)
Create POST /api/recipient/requests endpoint.

### Expected Violations:
1. Controller directly manipulates model instead of using service
2. Repository doesn't extend BaseRepository
3. No FormRequest validation
4. No enum usage for food_type
5. Inconsistent JSON response format
6. No separation of concerns (fat controller, anemic service)
7. Manual pagination instead of using Filterables
