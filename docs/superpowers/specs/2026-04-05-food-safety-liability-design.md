# FeedLink Food Safety & Liability Design Spec

**Date:** 2026-04-05  
**Project:** FeedLink – Food Surplus Redistribution Platform (Nepal)  
**Scope:** Legal framework, illness reporting system, warning labels, and food safety guidance  

---

## 1. Overview

FeedLink adopts a **donor-centric, transparent accountability model** for food safety and liability management. The platform facilitates peer-to-peer food sharing while making clear that donors bear responsibility for food safety and recipients make informed decisions about accepting food.

**Core Principle:** Users sign mutual responsibility terms; the platform documents claims transparently and shows warnings based on reported issues; FeedLink itself does not investigate or verify food safety.

---

## 2. Legal & Terms Framework

### 2.1 Mutual Responsibility Terms

**Two parallel documents** (stored in `resources/legal/`):

1. **Donor Mutual Responsibility Terms**
   - "FeedLink is a platform facilitating peer-to-peer food sharing. Donors are responsible for ensuring food is safe to consume. FeedLink holds no liability for foodborne illness, health claims, or damages arising from food shared on the platform."
   - Donors use the platform at their own risk
   - Version control: filename format `donor-terms-YYYY-MM-DD.md`

2. **Recipient Mutual Responsibility Terms**
   - "FeedLink is a platform facilitating peer-to-peer food sharing. Recipients verify food safety before consuming. FeedLink holds no liability for foodborne illness, health claims, or damages arising from food received on the platform."
   - Recipients use the platform at their own risk
   - Version control: filename format `recipient-terms-YYYY-MM-DD.md`

Both documents include:
- Clear liability disclaimers
- Statement that FeedLink does not verify, inspect, or certify food safety
- Basic food safety guidance (see Section 5)
- Requirement that disputes are governed by Nepal law and jurisdiction is Nepal courts

### 2.2 Onboarding Acceptance Flow

**User Registration Changes:**
- Add required checkbox: "I understand and accept the Mutual Responsibility Terms" with link to view both documents
- Cannot proceed with signup without checking the box
- Displayed after email/password but before role selection

**Data Capture:**
- New table: `user_acceptances`
  - `id` (UUID PK)
  - `user_id` (FK → users)
  - `terms_version` (string, e.g., "2026-04-05")
  - `terms_type` (enum: `donor`, `recipient`, `mutual`)
  - `accepted_at` (timestamp)
  - `ip_address` (string, for audit trail)
  - `created_at`

**Terms Updates:**
- When terms change, create new dated version file
- All users see modal on next app login: "Terms have been updated. Please review and accept." (required, can't dismiss)
- Record new acceptance with new version number
- Old acceptances remain in audit trail

---

## 3. Illness Claims & Reporting System

### 3.1 Database Schema

**New Table: `illness_claims`**
```
id (UUID PK)
reporter_id (FK → users) – recipient reporting the illness
donor_id (FK → users) – donor being reported
food_listing_id (FK → food_listings, nullable) – if remembered
description (text, max 2000 chars) – symptoms, timing, details
reported_at (timestamp) – when illness occurred (not when reported)
status (enum: pending, reviewed, archived) – for future filtering
created_at (timestamp) – when report was filed
updated_at (timestamp)
```

**New Table: `donor_warnings`** (cached/denormalized for performance)
```
id (UUID PK)
donor_id (FK → users, unique)
claim_count (int) – number of claims in past 12 months
warning_active (boolean) – true if claim_count >= 2
last_claim_at (timestamp) – when most recent claim was filed
updated_at (timestamp)
created_at (timestamp)
```

**Job to Maintain `donor_warnings`:**
- Trigger whenever `illness_claims` record is created
- Query past 12 months of claims for donor
- Update `claim_count` and `warning_active`
- Automatically expire warnings: claims older than 12 months don't count (auto-recalculate)

### 3.2 API Endpoints

**New Route: Report Illness**
```
POST /api/recipient/illness-claims
Content-Type: application/json
Authorization: Bearer {token}

{
  "food_listing_id": "uuid or null",
  "reported_at": "2026-04-05T18:00:00Z",
  "description": "Ate food from listing. Symptoms: nausea, vomiting starting 2 hours later.",
  "contacted_health_authorities": false,
  "photos": [] // optional file uploads
}

Response: 201 Created
{
  "status_code": 201,
  "message": "Illness report submitted",
  "data": {
    "id": "claim-uuid",
    "status": "pending",
    "created_at": "2026-04-05T19:00:00Z"
  }
}
```

**New Route: Get Donor Warning Status**
```
GET /api/donor/warning
Authorization: Bearer {token}

Response: 200 OK
{
  "status_code": 200,
  "message": "Warning status retrieved",
  "data": {
    "warning_active": true,
    "claim_count": 2,
    "last_claim_at": "2026-04-03T10:00:00Z"
  }
}
```
*(Only authenticated donors can view their own warning)*

### 3.3 Claim Processing

**On Claim Submission:**
1. Create `illness_claims` record with status: `pending`
2. Trigger background job to recalculate `donor_warnings`
3. Return confirmation to recipient: "Thank you. Your report has been recorded for pattern monitoring."
4. No email or notification sent to donor

**On Donor Warning Activation (2+ claims):**
1. Update `donor_warnings.warning_active = true` for that donor
2. Warning becomes visible immediately on all subsequent queries
3. No notification to donor; they discover warning when they view their own profile or receive feedback

---

## 4. Warning Label Display

### 4.1 Backend Changes

**Modify Food Listing Response:**
- Add eager load: `->with(['donor.warning'])`
- Include in serialization:
```json
"donor": {
  "id": "...",
  "name": "...",
  "is_verified": false,
  "warning_label": {
    "active": true,
    "message": "⚠️ This donor has received health reports. Verify food safety before claiming."
  }
}
```
- If no warning, include: `"warning_label": null`

### 4.2 iOS Mobile UX

**Three-Level Display:**

1. **In Listing Cards (Feed/Browse):**
   - Red/orange warning badge (⚠️) on donor avatar
   - Badge only; full text hidden (preserve space)
   - Tap badge to see explanation

2. **In Listing Detail View:**
   - Prominent alert box below donor info (yellow/orange background):
     ```
     ⚠️ Health Reports Received
     This donor has received health reports.
     Verify food safety before claiming.
     ```
   - Non-blocking; user can still proceed

3. **On Claim Confirmation (iOS Dialog):**
   ```
   ⚠️ Health Reports on Record
   
   This donor has received health reports.
   Are you sure you want to claim?
   
   [Cancel] [Confirm]
   ```

### 4.3 Web/API Display

- Show warning flag and message in JSON response (client decides display)
- Recommended: similar yellow alert box above claim button

---

## 5. Food Safety Guidelines

### 5.1 Public Guide

**File Location:** `resources/legal/food-safety-guide.md`

**Content:**
```markdown
# Food Safety Guidelines for FeedLink Users

## For Donors

- **Temperature Control**
  - Keep hot foods above 60°C, cold foods below 4°C
  - Use insulated containers for transport
  
- **Labeling**
  - Write date/time prepared on every container
  - Include estimated expiry time (e.g., "Good until 8:00 PM")
  
- **Personal Hygiene**
  - Wash hands before preparing/handling food
  - Don't share if you're coughing, feverish, or have diarrhea
  
- **Cleanliness**
  - Use clean, food-safe containers and utensils
  - Store separately from non-food items
  
- **Transparency**
  - Be honest about food origin: restaurant, homemade, raw ingredients
  - Disclose any special handling (e.g., "reheated from freezer")

## For Recipients

- **Before Accepting**
  - Check food appearance (no mold, discoloration, unusual smell)
  - Verify labels: preparation time and expiry
  
- **Before Consuming**
  - Reheat hot foods if they've cooled
  - Trust your senses: if something seems off, don't eat it
  
- **If You Get Ill**
  - Keep the container (potential evidence)
  - Note time of symptoms
  - Consider reporting to health authorities if serious
  - You can file a report on FeedLink (helps protect others)

## Questions?

For serious food safety concerns, contact Nepal's Department of Health Services
or your local health authority.
```

### 5.2 Link Placement

- Bottom of mutual responsibility terms (during onboarding, after link)
- Accessible from app settings / help menu
- Linked in warning label message on listings
- Not required reading; optional reference

---

## 6. Data Retention & Privacy

### 6.1 Illness Claims

- **Retention:** Permanent (legal defensibility requires audit trail)
- **Access:** 
  - Reporters can view their own claims (read-only)
  - Donors cannot see claims against them (to prevent retaliation)
  - FeedLink admins can access all for legal/compliance purposes
- **Privacy:** Claims are never displayed publicly; only warning count is visible

### 6.2 User Acceptances

- **Retention:** Permanent (proof of informed consent)
- **Access:** Admins only
- **Use:** Legal defense if dispute arises

---

## 7. Implementation Sequence

1. Create migration + models (`illness_claims`, `donor_warnings`, `user_acceptances`)
2. Create background job to recalculate `donor_warnings`
3. Update Food Listing model & repository to eager load warnings
4. Create illness claim controller & routes
5. Update listing response formatting to include warning label
6. Create food safety guide markdown file
7. Update onboarding flow to require terms acceptance
8. Update food listing response formatting (web & mobile)
9. iOS mobile: implement three-level warning display
10. Testing: test claims flow, warning calculation, edge cases (12-month expiry)

---

## 8. Success Criteria

- ✅ Both donors and recipients sign mutual responsibility terms at signup
- ✅ Illness claims can be filed by recipients tied to past listings
- ✅ Warning labels appear on donors with 2+ claims
- ✅ Warnings are visible at three levels on iOS (badge, alert, confirmation)
- ✅ Claims and warnings data is permanently stored and audit-trailed
- ✅ Food safety guidance is accessible and non-mandatory
- ✅ No email notifications to donors (transparent but non-confrontational)
- ✅ Legal position is defensible: terms signed + documentation + transparency

---

## 9. Future Enhancements (Out of Scope)

- Donor dispute/response mechanism (allow donors to contest claims)
- Advanced investigation workflow (photo evidence, structured symptom questionnaire)
- Automatic authority reporting (if claim threshold reached)
- Donor training/certification (video course, quiz)
- Rating system (separate from warnings)
- Analytics dashboard (pattern detection)

---

## 10. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Terms not actually read by users | Make acceptance mandatory checkbox; include summary in modal |
| Donors don't follow food safety rules | Guidance provided; warnings visible; transparency deters repeat offenders |
| False claims filed maliciously | No action taken on single claim; warning only after 2+ claims |
| Donors identify who reported them | Donors never see individual claims; only warning count visible |
| Legal challenge to enforceability | Terms signed at registration with IP capture; versioned; Nepal jurisdiction explicit |
| Claims lost/forgotten by recipients | Recipient can file claim days/weeks later tied to past listing if they remember |

---

## Conclusion

This design positions FeedLink as a **responsible facilitator** without attempting to be a food safety regulator. Users understand their roles, claims are documented transparently, and the warning system incentivizes good behavior without removing donors unilaterally.
