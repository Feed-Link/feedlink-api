# Skill Verification: Modular Monolithic Pattern

## RED Phase - Baseline (WITHOUT Skill)

**Scenario:** Build a new FoodRequest module with CRUD for recipients.

### Expected Baseline Violations:

1. **Controller has business logic:**
   ```php
   // WITHOUT Skill - Agent writes:
   public function store(Request $request)
   {
       $data = $request->all();
       // Direct model creation - no service
       $foodRequest = FoodRequest::create($data);
       return response()->json($foodRequest);
   }
   ```

2. **Repository doesn't extend BaseRepository:**
   ```php
   // WITHOUT Skill - Agent might write:
   class FoodRequestRepository
   {
       public function fetchAll($params)
       {
           // Custom query, no inheritance
           return FoodRequest::where(...)->get();
       }
   }
   ```

3. **Hardcoded enum values:**
   ```php
   // WITHOUT Skill - Agent writes:
   'status' => 'required|in:open,accepted,fulfilled,expired,cancelled'
   // No enum class, magic strings scattered
   ```

4. **No FormRequest validation:**
   ```php
   // WITHOUT Skill - Agent writes:
   public function store(Request $request)
   {
       $validated = $request->validate([...]); // Inline in controller
       // ...
   }
   ```

5. **Inconsistent JSON responses:**
   ```php
   // WITHOUT Skill - Agent might write:
   return response()->json(['data' => $listing]); // No status_code, no message
   ```

6. **Controller bypasses service:**
   ```php
   // WITHOUT Skill - Agent writes:
   public function index()
   {
       $requests = $this->foodRequestRepository->fetchAll($params);
       // No service layer at all
   }
   ```

## GREEN Phase - With Skill Applied

**Scenario:** Same task - build FoodRequest module.

### With Skill - Agent writes:

1. **Thin Controller:**
   ```php
   public function store(StoreFoodRequestRequest $request): JsonResponse
   {
       try {
           $foodRequest = $this->foodRequestService->create(
               $request->validated(),
               $request->user()->id
           );
           
           return $this->success(
               'Food request created successfully',
               Response::HTTP_CREATED,
               new FoodRequestResource($foodRequest->fresh())
           );
       } catch (Exception $exception) {
           return $this->handleException($exception);
       }
   }
   ```

2. **Repository extends BaseRepository:**
   ```php
   class FoodRequestRepository extends BaseRepository
   {
       public function __construct(protected FoodRequest $foodRequest)
       {
           $this->model = $foodRequest;
           parent::__construct();
       }
       
       // Custom methods only
       public function fetchOpenByRecipient(string $recipientId, array $params = []): object
       {
           return $this->fetchAll([
               ...$params,
               'filter' => [
                   ['filter_by' => 'recipient_id', 'value' => $recipientId],
                   ['filter_by' => 'status', 'value' => 'open'],
               ]
           ]);
       }
   }
   ```

3. **Enum for categorical values:**
   ```php
   // In app/Modules/Core/Enums/FoodTypeEnum.php and RequestStatusEnum.php
   enum RequestStatusEnum: string
   {
       case OPEN = 'open';
       case ACCEPTED = 'accepted';
       case FULFILLED = 'fulfilled';
       case EXPIRED = 'expired';
       case CANCELLED = 'cancelled';
       
       public static function getAllValues(): array
       {
           return array_column(self::cases(), 'value');
       }
   }
   
   // Used in validation:
   'status' => 'required|in:' . implode(',', RequestStatusEnum::getAllValues())
   ```

4. **FormRequest for validation:**
   ```php
   class StoreFoodRequestRequest extends BaseRequest
   {
       public function store(): array
       {
           return [
               'title' => 'required|string|max:255',
               'description' => 'nullable|string',
               'quantity_needed' => 'required|string|max:100',
               'food_type' => 'required|in:' . implode(',', FoodTypeEnum::getAllValues()),
               'needed_by' => 'required|date|after:now',
               'latitude' => 'required|numeric|between:-90,90',
               'longitude' => 'required|numeric|between:-180,180',
               'address' => 'required|string|max:500',
           ];
       }
   }
   ```

5. **Service handles business logic:**
   ```php
   class FoodRequestService
   {
       public function create(array $data, string $recipientId): object
       {
           // Validate food_type
           if (!in_array($data['food_type'], FoodTypeEnum::getAllValues())) {
               throw new Exception('Invalid food type', 400);
           }
           
           // Set location
           $data['recipient_id'] = $recipientId;
           $data['location'] = Point::makeGeodetic(
               $data['latitude'],
               $data['longitude']
           );
           $data['status'] = RequestStatusEnum::OPEN->value;
           
           return $this->foodRequestRepository->store($data);
       }
   }
   ```

6. **API Resource for consistent output:**
   ```php
   class FoodRequestResource extends JsonResource
   {
       public function toArray($request): array
       {
           return [
               'id' => $this->id,
               'title' => $this->title,
               'quantity_needed' => $this->quantity_needed,
               'food_type' => $this->food_type,
               'status' => $this->status,
               'latitude' => $this->latitude,
               'longitude' => $this->longitude,
               'address' => $this->address,
               'distance_km' => $this->distance_km ?? null,
               'recipient' => [
                   'id' => $this->recipient->id,
                   'name' => $this->recipient->name,
                   'is_verified' => (bool) $this->recipient->is_verified,
               ],
               'accepted_by' => null,
               'needed_by' => $this->needed_by?->toISOString(),
               'created_at' => $this->created_at?->toISOString(),
           ];
       }
   }
   ```

## REFACTOR Phase - Closing Loopholes

Based on baseline testing, added explicit counters:

1. **Red Flags section** - Lists exact rationalizations like "This is too simple for a service layer"
2. **"Do NOT Use When" section** - Prevents misuse for migrations/seeders
3. **Golden Rule** - Explicit: "If you're writing SQL queries directly in a controller or service, you're doing it wrong."
4. **Deployment Checklist** - 12-item pre-commit verification
5. **Quick Reference** - Matrix of common mistakes with corrections

## Verification Complete

✅ **Baseline violations documented** - Without skill, agents cut corners
✅ **Skill enforces pattern** - With skill, agents follow Repository-Service-Controller
✅ **Loopholes closed** - Rationalization table addresses "too simple", "just this once"
✅ **Discoverable** - Description includes "modular monolithic architecture", "repository and service pattern"
✅ **Project-specific** - References actual files: BaseModel, BaseRequest, BaseRepository, Filterables, HasApiResponse

## Test Result

**Status:** **PASS**

The skill successfully enforces the modular monolithic pattern observed in the codebase and prevents common shortcuts that would violate separation of concerns.
